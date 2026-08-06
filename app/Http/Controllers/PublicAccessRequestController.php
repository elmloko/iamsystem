<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\SystemEntry;
use App\Services\SystemAccountProvisioner;
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
                ->connectable()
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'alias_column']),
        ]);
    }

    public function rolesFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        return response()->json([
            'roles' => $provisioner->fetchPublicRoles($system),
        ]);
    }

    public function extraFieldsFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        return response()->json([
            'fields' => $provisioner->resolveExtraFields($system),
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        foreach ($data['systems'] as $entry) {
            $accessRequest->items()->create([
                'system_id' => $entry['system_id'],
                'role_id' => $entry['role_id'] ?? null,
                'role_name' => $entry['role_name'] ?? null,
                'alias' => $entry['alias'] ?? null,
                'extra_fields' => $entry['extra_fields'] ?? [],
                'status' => 'pending',
            ]);
        }

        return redirect()->route('access-requests.sent');
    }

    public function sent(): Response
    {
        return Inertia::render('Public/RequestSent');
    }
}
