<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\AccessRequestItem;
use App\Models\Person;
use App\Services\SystemAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestController extends Controller
{
    public function index(): Response
    {
        $requests = AccessRequest::with(['items.system', 'items.decidedBy'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AccessRequest $accessRequest) {
                return [
                    'id' => $accessRequest->id,
                    'name' => $accessRequest->name,
                    'email' => $accessRequest->email,
                    'created_at' => $accessRequest->created_at,
                    'items' => $accessRequest->items->map(fn (AccessRequestItem $item) => [
                        'id' => $item->id,
                        'system_id' => $item->system_id,
                        'system_name' => $item->system?->name ?? '(sistema eliminado)',
                        'role_name' => $item->role_name,
                        'alias' => $item->alias,
                        'extra_fields' => $item->extra_fields,
                        'status' => $item->status,
                        'outcome_status' => $item->outcome_status,
                        'outcome_message' => $item->outcome_message,
                        'decided_by_name' => $item->decidedBy?->name,
                        'decided_at' => $item->decided_at,
                    ]),
                ];
            });

        return Inertia::render('AccessRequests/Index', ['requests' => $requests]);
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

        return back()->with('success', "Acceso a \"{$item->system->name}\" aprobado.");
    }

    public function reject(AccessRequestItem $item): RedirectResponse
    {
        if ($item->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $item->update([
            'status' => 'rejected',
            'decided_by' => request()->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', "Acceso a \"{$item->system->name}\" rechazado.");
    }
}
