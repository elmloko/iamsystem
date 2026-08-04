<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\SystemEntry;
use App\Services\SystemAccountProvisioner;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request, SystemAccountProvisioner $provisioner): Response
    {
        $search = $request->string('q')->trim()->value() ?: null;
        $systemKey = $request->string('system')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        $grouped = $provisioner->listGroupedByName($search, $systemKey, $status);

        $people = new LengthAwarePaginator(
            $grouped->forPage($page, $perPage)->values(),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Users/Index', [
            'people' => $people,
            'systems' => SystemEntry::orderBy('name')->get(['id', 'key', 'name', 'status']),
            'filters' => $request->only(['q', 'system', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'systems' => SystemEntry::where('status', 'active')
                ->whereNotNull('connection')
                ->orderBy('name')
                ->get(['id', 'key', 'name']),
        ]);
    }

    public function rolesFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        return response()->json([
            'roles' => $provisioner->fetchRoles($system),
        ]);
    }

    public function detail(Request $request, SystemAccountProvisioner $provisioner)
    {
        $name = $request->string('name')->trim()->value();

        if (! $name) {
            return response()->json(['accounts' => []]);
        }

        return response()->json([
            'accounts' => $provisioner->getPersonDetail($name),
        ]);
    }

    public function store(Request $request, SystemAccountProvisioner $provisioner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'systems' => ['required', 'array', 'min:1'],
            'systems.*.system_id' => ['required', 'exists:systems,id'],
            'systems.*.role_id' => ['nullable'], // int (tabla+pivote) o string (rol como columna directa)
            'systems.*.role_name' => ['nullable', 'string'],
        ]);

        $person = Person::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name']]
        );

        $results = [];

        foreach ($data['systems'] as $entry) {
            $system = SystemEntry::findOrFail($entry['system_id']);

            $outcome = $provisioner->createAccount(
                $system,
                $person,
                $data['password'],
                $entry['role_id'] ?? null,
                $entry['role_name'] ?? null,
            );

            $person->accounts()->updateOrCreate(
                ['system_id' => $system->id],
                [
                    'remote_user_id' => $outcome['remote_user_id'] ?? null,
                    'role_name' => $outcome['role_name'] ?? ($entry['role_name'] ?? null),
                    'role_id' => $entry['role_id'] ?? null,
                    'status' => $outcome['status'],
                    'message' => $outcome['message'] ?? null,
                ]
            );

            $results[] = ['system' => $system->name, ...$outcome];
        }

        return redirect()->route('users.index')->with('provisionResults', $results);
    }
}
