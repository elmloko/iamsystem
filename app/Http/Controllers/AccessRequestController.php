<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\AccessRequestItem;
use App\Models\Person;
use App\Models\SystemEntry;
use App\Services\SystemAccountProvisioner;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestController extends Controller
{
    public function index(): Response
    {
        // Se agrupa por persona (correo), no por cada envío del formulario:
        // si alguien pidió acceso más de una vez, todo aparece en una sola
        // tarjeta en vez de repetirse.
        $items = AccessRequestItem::with(['accessRequest', 'system', 'decidedBy'])
            ->get()
            ->sortByDesc(fn (AccessRequestItem $item) => $item->created_at);

        $people = $items
            ->groupBy(fn (AccessRequestItem $item) => mb_strtolower(trim($item->accessRequest->email)))
            ->map(function ($items) {
                $latest = $items->sortByDesc(fn ($item) => $item->accessRequest->created_at)->first();
                $sortedItems = $items->sortBy(fn ($item) => [
                    $item->status === 'pending' ? 0 : 1,
                    -$item->created_at->timestamp,
                ])->values();

                return [
                    'name' => $latest->accessRequest->name,
                    'email' => $latest->accessRequest->email,
                    'pending_count' => $items->where('status', 'pending')->count(),
                    'access_request_ids' => $items->pluck('access_request_id')->unique()->values(),
                    'items' => $sortedItems->map(fn (AccessRequestItem $item) => [
                        'id' => $item->id,
                        'access_request_id' => $item->access_request_id,
                        'system_id' => $item->system_id,
                        'system_name' => $item->system?->name ?? '(sistema eliminado)',
                        'role_id' => $item->role_id,
                        'role_name' => $item->role_name,
                        'alias' => $item->alias,
                        'extra_fields' => $item->extra_fields,
                        'status' => $item->status,
                        'outcome_status' => $item->outcome_status,
                        'outcome_message' => $item->outcome_message,
                        'decided_by_name' => $item->decidedBy?->name,
                        'decided_at' => $item->decided_at,
                        'requested_at' => $item->created_at,
                        'requester_name' => $item->accessRequest->name,
                        'requester_email' => $item->accessRequest->email,
                    ]),
                ];
            })
            ->sortBy(fn ($group) => [-$group['pending_count'], -$group['items']->first()['requested_at']->timestamp])
            ->values();

        return Inertia::render('AccessRequests/Index', [
            'people' => $people,
            'systems' => SystemEntry::where('status', 'active')
                ->connectable()
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'alias_column', 'alias_required']),
        ]);
    }

    /**
     * Corrige lo que la persona llenó mal en el formulario público antes de
     * aprobar: nombre/email de la solicitud y rol/alias/campos extra del
     * ítem. Solo tiene sentido en solicitudes todavía pendientes — una vez
     * aprobada o rechazada, la decisión y (si aplica) la cuenta ya están
     * tomadas, corregir acá no las actualiza retroactivamente.
     */
    public function updateItem(Request $request, AccessRequestItem $item): RedirectResponse
    {
        if ($item->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya fue procesada, no se puede editar.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['nullable'],
            'role_name' => ['nullable', 'string'],
            'alias' => ['nullable', 'string', 'max:255'],
            'extra_fields' => ['nullable', 'array'],
        ]);

        $system = $item->system;

        if ($system?->alias_required && blank($data['alias'] ?? null)) {
            throw ValidationException::withMessages([
                'alias' => "El alias de acceso es obligatorio para {$system->name}.",
            ]);
        }

        foreach ($system?->extra_fields ?? [] as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            $value = $data['extra_fields'][$field['column']] ?? null;

            if ($value === null || $value === '' || $value === []) {
                throw ValidationException::withMessages([
                    'extra_fields' => "\"{$field['label']}\" es obligatorio para {$system->name}.",
                ]);
            }
        }

        $item->accessRequest->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $item->update([
            'role_id' => $data['role_id'] ?? null,
            'role_name' => $data['role_name'] ?? null,
            'alias' => $data['alias'] ?? null,
            'extra_fields' => $data['extra_fields'] ?? [],
        ]);

        Audit::log(
            'access_requests.edited',
            "Corrigió la solicitud de \"{$data['name']}\" ({$data['email']}) para \"{$system?->name}\".",
            $item,
            ['system' => $system?->name]
        );

        return back()->with('success', 'Solicitud corregida.');
    }

    public function approve(AccessRequestItem $item, SystemAccountProvisioner $provisioner): RedirectResponse
    {
        if ($item->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $accessRequest = $item->accessRequest;

        $person = Person::firstOrCreate(
            ['email' => $accessRequest->email],
            ['name' => $accessRequest->name]
        );

        $outcome = $provisioner->createAccount(
            $item->system,
            $person,
            $accessRequest->password,
            $item->role_id,
            $item->role_name,
            $item->alias,
            $item->extra_fields ?? [],
        );

        $person->accounts()->updateOrCreate(
            ['system_id' => $item->system_id],
            [
                'remote_user_id' => $outcome['remote_user_id'] ?? null,
                'role_name' => $outcome['role_name'] ?? $item->role_name,
                'role_id' => $item->role_id,
                'status' => $outcome['status'],
                'message' => $outcome['message'] ?? null,
            ]
        );

        $item->update([
            'status' => 'approved',
            'remote_user_id' => $outcome['remote_user_id'] ?? null,
            'outcome_status' => $outcome['status'],
            'outcome_message' => $outcome['message'] ?? null,
            'decided_by' => request()->user()->id,
            'decided_at' => now(),
        ]);

        Audit::log(
            'access_requests.approved',
            "Aprobó el acceso de \"{$accessRequest->name}\" ({$accessRequest->email}) a \"{$item->system->name}\".",
            $item,
            ['person_email' => $accessRequest->email, 'system' => $item->system->name]
        );

        return back()->with('success', "Acceso a \"{$item->system->name}\" aprobado.");
    }

    public function reject(AccessRequestItem $item): RedirectResponse
    {
        if ($item->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $accessRequest = $item->accessRequest;

        $item->update([
            'status' => 'rejected',
            'decided_by' => request()->user()->id,
            'decided_at' => now(),
        ]);

        Audit::log(
            'access_requests.rejected',
            "Rechazó el acceso de \"{$accessRequest->name}\" ({$accessRequest->email}) a \"{$item->system->name}\".",
            $item,
            ['person_email' => $accessRequest->email, 'system' => $item->system->name]
        );

        return back()->with('success', "Acceso a \"{$item->system->name}\" rechazado.");
    }

    /**
     * Borra por completo el registro de la(s) solicitud(es) de una persona
     * (todas las que aparecen agrupadas en su tarjeta). No toca las cuentas
     * ya creadas en los sistemas remotos si algún ítem ya fue aprobado —
     * solo elimina el historial de la solicitud en el IAM.
     */
    public function destroyForPerson(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'access_request_ids' => ['required', 'array', 'min:1'],
            'access_request_ids.*' => ['integer', 'exists:access_requests,id'],
        ]);

        $requests = AccessRequest::whereIn('id', $data['access_request_ids'])->get(['id', 'name', 'email']);
        $person = $requests->first();

        AccessRequest::whereIn('id', $data['access_request_ids'])->delete();

        if ($person) {
            Audit::log(
                'access_requests.deleted',
                "Eliminó el historial de solicitudes de \"{$person->name}\" ({$person->email}).",
                null,
                ['person_email' => $person->email, 'access_request_ids' => $data['access_request_ids']]
            );
        }

        return back()->with('success', 'Solicitud eliminada.');
    }
}
