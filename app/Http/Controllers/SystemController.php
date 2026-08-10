<?php

namespace App\Http\Controllers;

use App\Models\SystemEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemController extends Controller
{
    public function index(): Response
    {
        $systems = SystemEntry::orderBy('name')->get([
            'id', 'key', 'name', 'status', 'connection', 'notes', 'visible_in_public_form',
            'users_table', 'name_column', 'last_name_column', 'email_column', 'password_column', 'password_hash_algo', 'model_type',
            'roles_table', 'role_column', 'role_json_column',
            'role_pivot_table', 'role_pivot_user_column', 'role_pivot_role_column',
            'active_column', 'active_type', 'active_values',
            'alias_column',
            'db_driver', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        ])->map(function (SystemEntry $system) {
            $data = $system->toArray();
            $data['has_password'] = filled($system->db_password);
            $data['role_mechanism'] = match (true) {
                (bool) $system->role_column => 'column',
                (bool) $system->role_json_column => 'json',
                (bool) $system->role_pivot_table => 'pivot',
                default => 'none',
            };
            $data['active_values_text'] = $system->active_values
                ? implode(', ', json_decode($system->active_values, true) ?? [])
                : '';
            unset($data['db_password']);

            return $data;
        });

        return Inertia::render('Systems/Index', ['systems' => $systems]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPayload($request, isCreate: true);

        $system = SystemEntry::create($data);

        return redirect()->route('systems.index')->with('success', "Sistema \"{$system->name}\" creado.");
    }

    public function update(Request $request, SystemEntry $system): RedirectResponse
    {
        $data = $this->validatedPayload($request);

        if (blank($data['db_password'] ?? null)) {
            unset($data['db_password']);
        }

        $system->update($data);

        // Los datos de conexión pudieron cambiar: se descarta la conexión
        // dinámica ya registrada en runtime para que la próxima consulta
        // use los datos nuevos.
        DB::purge("sysentry_{$system->id}");

        return redirect()->route('systems.index')->with('success', "Sistema \"{$system->name}\" actualizado.");
    }

    public function toggleVisibility(SystemEntry $system): RedirectResponse
    {
        $system->update(['visible_in_public_form' => ! $system->visible_in_public_form]);

        $verb = $system->visible_in_public_form ? 'visible' : 'oculto';

        return back()->with('success', "\"{$system->name}\" ahora está {$verb} en el formulario público de solicitud.");
    }

    private function validatedPayload(Request $request, bool $isCreate = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'pending'])],
            'notes' => ['nullable', 'string'],

            'db_driver' => ['required', Rule::in(['pgsql', 'mysql', 'sqlsrv'])],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string'],

            'users_table' => ['required', 'string', 'max:255'],
            'name_column' => ['required', 'string', 'max:255'],
            'last_name_column' => ['nullable', 'string', 'max:255'],
            'email_column' => ['required', 'string', 'max:255'],
            'password_column' => ['required', 'string', 'max:255'],
            'password_hash_algo' => ['nullable', Rule::in(['bcrypt', 'sha256', 'md5', 'sha1', 'plain'])],
            'model_type' => ['nullable', 'string', 'max:255'],

            'role_mechanism' => ['required', Rule::in(['none', 'column', 'json', 'pivot'])],
            'role_column' => ['nullable', 'string', 'max:255'],
            'role_json_column' => ['nullable', 'string', 'max:255'],
            'roles_table' => ['nullable', 'string', 'max:255'],
            'role_pivot_table' => ['nullable', 'string', 'max:255'],
            'role_pivot_user_column' => ['nullable', 'string', 'max:255'],
            'role_pivot_role_column' => ['nullable', 'string', 'max:255'],

            'active_type' => ['nullable', Rule::in(['boolean', 'soft_delete', 'text'])],
            'active_column' => ['nullable', 'string', 'max:255'],
            'active_values_text' => ['nullable', 'string'],

            'alias_column' => ['nullable', 'string', 'max:255'],
        ];

        if ($isCreate) {
            $rules['key'] = ['required', 'string', 'max:255', 'alpha_dash', 'unique:systems,key'];
        }

        $data = $request->validate($rules);

        // Solo dejamos activo el bloque de columnas correspondiente al
        // mecanismo de roles elegido, para no dejar datos viejos colgando.
        [$data['role_column'], $data['role_json_column'], $data['roles_table'], $data['role_pivot_table']] = match ($data['role_mechanism']) {
            'column' => [$data['role_column'] ?? null, null, null, null],
            'json' => [null, $data['role_json_column'] ?? null, null, null],
            'pivot' => [null, null, $data['roles_table'] ?? null, $data['role_pivot_table'] ?? null],
            default => [null, null, null, null],
        };
        unset($data['role_mechanism']);

        if (! $data['active_type']) {
            $data['active_column'] = null;
            $data['active_values'] = null;
        } else {
            $values = array_values(array_filter(array_map('trim', explode(',', $data['active_values_text'] ?? ''))));
            $data['active_values'] = $values ? json_encode($values) : null;
        }
        unset($data['active_values_text']);

        return $data;
    }

    public function testConnection(Request $request)
    {
        $data = $request->validate([
            'system_id' => ['nullable', 'exists:systems,id'],
            'db_driver' => ['required', Rule::in(['pgsql', 'mysql', 'sqlsrv'])],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string'],
        ]);

        $password = $data['db_password'] ?? null;

        if (blank($password) && ! empty($data['system_id'])) {
            $password = SystemEntry::find($data['system_id'])?->db_password;
        }

        $tempName = 'systest_'.uniqid();

        Config::set("database.connections.{$tempName}", [
            'driver' => $data['db_driver'],
            'host' => $data['db_host'],
            'port' => $data['db_port'] ?: ($data['db_driver'] === 'mysql' ? 3306 : 5432),
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $password,
            'charset' => $data['db_driver'] === 'mysql' ? 'utf8mb4' : 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        try {
            DB::connection($tempName)->getPdo();

            return response()->json(['status' => 'ok', 'message' => 'Conexión exitosa.']);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'No se pudo conectar: '.$e->getMessage()]);
        } finally {
            DB::purge($tempName);
        }
    }
}
