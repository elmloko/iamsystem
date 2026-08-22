<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Models\Person;
use App\Models\SystemEntry;
use App\Services\SystemAccountProvisioner;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

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
            'systems' => SystemEntry::orderBy('name')->get(['id', 'key', 'name', 'status', 'alias_column', 'alias_required']),
            'filters' => $request->only(['q', 'system', 'status']),
        ]);
    }

    public function exportPdf(Request $request, SystemAccountProvisioner $provisioner)
    {
        $search = $request->string('q')->trim()->value() ?: null;
        $systemKey = $request->string('system')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;

        $accounts = $provisioner->listDetailedForExport($search, $systemKey, $status);
        $systemName = $systemKey ? SystemEntry::where('key', $systemKey)->value('name') : null;

        $pdf = Pdf::loadView('exports.users-pdf', [
            'accounts' => $accounts,
            'search' => $search,
            'systemName' => $systemName,
            'status' => $status,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('usuarios-'.now()->format('Y-m-d_His').'.pdf');
    }

    public function exportExcel(Request $request, SystemAccountProvisioner $provisioner)
    {
        $search = $request->string('q')->trim()->value() ?: null;
        $systemKey = $request->string('system')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;

        $accounts = $provisioner->listDetailedForExport($search, $systemKey, $status);

        return Excel::download(new UsersExport($accounts), 'usuarios-'.now()->format('Y-m-d_His').'.xlsx');
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'systems' => SystemEntry::where('status', 'active')
                ->connectable()
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'alias_column', 'alias_required']),
        ]);
    }

    public function rolesFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        return response()->json([
            'roles' => $provisioner->fetchRoles($system),
        ]);
    }

    public function extraFieldsFor(SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        return response()->json([
            'fields' => $provisioner->resolveExtraFields($system),
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
            'systems.*.alias' => ['nullable', 'string', 'max:255'],
            'systems.*.extra_fields' => ['nullable', 'array'],
        ]);

        // Los campos extra varían por sistema (ver systems.extra_fields), así
        // que los obligatorios se validan aparte en vez de con reglas fijas.
        foreach ($data['systems'] as $entry) {
            $system = SystemEntry::find($entry['system_id']);

            if ($system?->alias_required && blank($entry['alias'] ?? null)) {
                throw ValidationException::withMessages([
                    'systems' => "El alias de acceso es obligatorio para {$system->name}.",
                ]);
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
                $entry['alias'] ?? null,
                $entry['extra_fields'] ?? [],
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

            Audit::log(
                'accounts.created',
                "Creó la cuenta de \"{$data['name']}\" ({$data['email']}) en \"{$system->name}\".",
                $system,
                ['person_name' => $data['name'], 'person_email' => $data['email'], 'remote_user_id' => $outcome['remote_user_id'] ?? null, 'role_name' => $outcome['role_name'] ?? ($entry['role_name'] ?? null)]
            );

            $results[] = ['system' => $system->name, ...$outcome];
        }

        return redirect()->route('users.index')->with('provisionResults', $results);
    }

    public function updateAccount(Request $request, SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        $data = $request->validate([
            'remote_user_id' => ['required'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            // Algunos sistemas legados (ej. CDS) no tienen columna de email
            // real: email_column apunta al usuario de login, que no tiene
            // formato de correo.
            'email' => $system->email_is_login
                ? ['required', 'string', 'max:255']
                : ['required', 'email', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $result = $provisioner->updateAccountFields(
            $system,
            $data['remote_user_id'],
            $data['first_name'],
            $data['last_name'] ?? null,
            $data['email'],
            $data['alias'] ?? null,
            $data['password'] ?? null,
        );

        Audit::log(
            'accounts.updated',
            "Editó la cuenta (ID remoto {$data['remote_user_id']}) en \"{$system->name}\".",
            $system,
            ['remote_user_id' => $data['remote_user_id'], 'email' => $data['email']]
        );

        return response()->json($result);
    }

    public function updateAccountStatus(Request $request, SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        $data = $request->validate([
            'remote_user_id' => ['required'],
            'active' => ['required', 'boolean'],
        ]);

        $result = $provisioner->setAccountActive($system, $data['remote_user_id'], $data['active']);

        $verb = $data['active'] ? 'Activó' : 'Desactivó';
        Audit::log(
            'accounts.status_changed',
            "{$verb} la cuenta (ID remoto {$data['remote_user_id']}) en \"{$system->name}\".",
            $system,
            ['remote_user_id' => $data['remote_user_id'], 'active' => $data['active']]
        );

        return response()->json($result);
    }

    public function addAccountRole(Request $request, SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        $data = $request->validate([
            'remote_user_id' => ['required'],
            'role_id' => ['required'],
            'role_name' => ['nullable', 'string'],
        ]);

        $result = $provisioner->addAccountRole($system, $data['remote_user_id'], $data['role_id'], $data['role_name'] ?? null);

        Audit::log(
            'accounts.role_added',
            "Agregó el rol \"{$data['role_name']}\" a la cuenta (ID remoto {$data['remote_user_id']}) en \"{$system->name}\".",
            $system,
            ['remote_user_id' => $data['remote_user_id'], 'role_id' => $data['role_id'], 'role_name' => $data['role_name'] ?? null]
        );

        return response()->json($result);
    }

    public function removeAccountRole(Request $request, SystemEntry $system, SystemAccountProvisioner $provisioner)
    {
        $data = $request->validate([
            'remote_user_id' => ['required'],
            'role_id' => ['required'],
        ]);

        $result = $provisioner->removeAccountRole($system, $data['remote_user_id'], $data['role_id']);

        Audit::log(
            'accounts.role_removed',
            "Quitó un rol de la cuenta (ID remoto {$data['remote_user_id']}) en \"{$system->name}\".",
            $system,
            ['remote_user_id' => $data['remote_user_id'], 'role_id' => $data['role_id']]
        );

        return response()->json($result);
    }
}
