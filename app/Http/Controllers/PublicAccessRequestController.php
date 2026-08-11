<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\SystemEntry;
use App\Services\SystemAccountProvisioner;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicAccessRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/RequestAccess', [
            'systems' => SystemEntry::where('status', 'active')
                ->where('visible_in_public_form', true)
                ->connectable()
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'alias_column', 'alias_required']),
        ]);
    }

    public function rolesFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        abort_unless($system->visible_in_public_form, 404);

        return response()->json([
            'roles' => $provisioner->fetchPublicRoles($system),
        ]);
    }

    public function extraFieldsFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        abort_unless($system->visible_in_public_form, 404);

        return response()->json([
            'fields' => $provisioner->resolvePublicExtraFields($system),
        ]);
    }

    public function store(Request $request, SystemAccountProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'systems' => ['required', 'array', 'min:1'],
            'systems.*.system_id' => ['required', 'exists:systems,id'],
            'systems.*.role_id' => ['nullable'],
            'systems.*.role_name' => ['nullable', 'string'],
            'systems.*.alias' => ['nullable', 'string', 'max:255'],
            'systems.*.extra_fields' => ['nullable', 'array'],
        ]);

        // Los campos extra varían por sistema; los obligatorios se validan
        // aparte (igual que en la creación interna).
        foreach ($data['systems'] as $entry) {
            $system = SystemEntry::find($entry['system_id']);

            if (! $system || ! $system->visible_in_public_form) {
                throw ValidationException::withMessages([
                    'systems' => 'Uno de los sistemas seleccionados ya no está disponible para solicitar acceso.',
                ]);
            }

            if ($system->alias_required && blank($entry['alias'] ?? null)) {
                throw ValidationException::withMessages([
                    'systems' => "El alias de acceso es obligatorio para {$system->name}.",
                ]);
            }

            // Defensa contra manipular el formulario a mano: si el rol o un
            // campo tiene una sola opción permitida en público, el valor
            // enviado tiene que ser justo esa (no cualquier id que exista en
            // el sistema real).
            if (filled($entry['role_id'] ?? null)) {
                $allowedRoles = $provisioner->fetchPublicRoles($system)->pluck('id')->map(fn ($id) => (string) $id);

                if (! $allowedRoles->contains((string) $entry['role_id'])) {
                    throw ValidationException::withMessages([
                        'systems' => "Ese rol no está disponible para {$system->name}.",
                    ]);
                }
            }

            foreach ($provisioner->resolvePublicExtraFields($system) as $field) {
                if (empty($field['options'])) {
                    continue;
                }

                $submitted = $entry['extra_fields'][$field['column']] ?? null;
                if ($submitted === null || $submitted === '') {
                    continue;
                }

                $allowedValues = collect($field['options'])->map(fn ($opt) => (string) ((array) $opt)['value']);
                $submittedValues = is_array($submitted) ? $submitted : [$submitted];

                foreach ($submittedValues as $v) {
                    if (! $allowedValues->contains((string) $v)) {
                        throw ValidationException::withMessages([
                            'systems' => "\"{$field['label']}\" tiene un valor no permitido para {$system->name}.",
                        ]);
                    }
                }
            }

            foreach ($system?->extra_fields ?? [] as $field) {
                if (! ($field['required'] ?? false)) {
                    continue;
                }

                $value = $entry['extra_fields'][$field['column']] ?? null;

                if ($value === null || $value === '' || $value === []) {
                    throw ValidationException::withMessages([
                        'systems' => "\"{$field['label']}\" es obligatorio para {$system->name}.",
                    ]);
                }
            }
        }

        $accessRequest = AccessRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $systemNames = [];

        foreach ($data['systems'] as $entry) {
            $accessRequest->items()->create([
                'system_id' => $entry['system_id'],
                'role_id' => $entry['role_id'] ?? null,
                'role_name' => $entry['role_name'] ?? null,
                'alias' => $entry['alias'] ?? null,
                'extra_fields' => $entry['extra_fields'] ?? [],
                'status' => 'pending',
            ]);

            $systemNames[] = SystemEntry::find($entry['system_id'])?->name ?? '(sistema eliminado)';
        }

        Audit::logAs(
            $data['name'],
            $data['email'],
            'access_requests.submitted',
            "\"{$data['name']}\" ({$data['email']}) pidió acceso a: ".implode(', ', $systemNames).'.',
            $accessRequest,
            ['systems' => $systemNames]
        );

        return redirect()->route('access-requests.sent');
    }

    public function sent(): Response
    {
        return Inertia::render('Public/RequestSent');
    }
}
