<?php

namespace App\Http\Controllers;

use App\Models\SystemEntry;
use App\Support\Audit;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use Throwable;

class SystemController extends Controller
{
    public function index(): Response
    {
        $systems = SystemEntry::orderBy('name')->get([
            'id', 'key', 'name', 'status', 'connection', 'notes', 'repo_url', 'url_internal', 'url_external', 'visible_in_public_form',
            'users_table', 'name_column', 'last_name_column', 'email_column', 'password_column', 'password_hash_algo', 'model_type',
            'roles_table', 'role_column', 'role_json_column',
            'role_pivot_table', 'role_pivot_user_column', 'role_pivot_role_column',
            'active_column', 'active_type', 'active_values',
            'alias_column', 'hidden_roles',
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
            $data['hidden_roles_text'] = $system->hidden_roles ? implode(', ', $system->hidden_roles) : '';
            unset($data['db_password']);

            return $data;
        });

        return Inertia::render('Systems/Index', ['systems' => $systems]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPayload($request, isCreate: true);

        $system = SystemEntry::create($data);

        Audit::log('systems.created', "Creó el sistema \"{$system->name}\".", $system);

        return redirect()->route('systems.index')->with('success', "Sistema \"{$system->name}\" creado.");
    }

    public function update(Request $request, SystemEntry $system): RedirectResponse
    {
        $data = $this->validatedPayload($request);

        if (blank($data['db_password'] ?? null)) {
            unset($data['db_password']);
        }

        $changes = collect($data)
            ->except(['db_password'])
            ->filter(fn ($value, $key) => $system->{$key} != $value)
            ->keys()
            ->all();

        $system->update($data);

        // Los datos de conexión pudieron cambiar: se descarta la conexión
        // dinámica ya registrada en runtime para que la próxima consulta
        // use los datos nuevos.
        DB::purge("sysentry_{$system->id}");

        Audit::log('systems.updated', "Editó el sistema \"{$system->name}\".", $system, ['campos_cambiados' => $changes]);

        return redirect()->route('systems.index')->with('success', "Sistema \"{$system->name}\" actualizado.");
    }

    public function destroy(SystemEntry $system): RedirectResponse
    {
        $name = $system->name;

        // No borra nada en la base de datos remota del sistema: solo quita
        // el registro/config de este sistema en el IAM. Las cuentas y
        // solicitudes locales asociadas se borran en cascada (FK), pero las
        // cuentas reales del sistema remoto quedan intactas.
        DB::purge("sysentry_{$system->id}");
        $system->delete();

        Audit::log('systems.deleted', "Eliminó el sistema \"{$name}\" del IAM.");

        return redirect()->route('systems.index')->with('success', "Sistema \"{$name}\" eliminado del IAM.");
    }

    public function toggleVisibility(SystemEntry $system): RedirectResponse
    {
        $system->update(['visible_in_public_form' => ! $system->visible_in_public_form]);

        $verb = $system->visible_in_public_form ? 'visible' : 'oculto';

        Audit::log('systems.visibility_toggled', "Puso \"{$system->name}\" como {$verb} en el formulario público de solicitud.", $system);

        return back()->with('success', "\"{$system->name}\" ahora está {$verb} en el formulario público de solicitud.");
    }

    private function validatedPayload(Request $request, bool $isCreate = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'pending'])],
            'notes' => ['nullable', 'string'],
            'repo_url' => ['nullable', 'string', 'max:255'],
            'url_internal' => ['nullable', 'string', 'max:255'],
            'url_external' => ['nullable', 'string', 'max:255'],

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

            'hidden_roles_text' => ['nullable', 'string'],
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

        $hiddenRoles = array_values(array_filter(array_map('trim', explode(',', $data['hidden_roles_text'] ?? ''))));
        $data['hidden_roles'] = $hiddenRoles ?: null;
        unset($data['hidden_roles_text']);

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

    public function testUrl(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:255'],
        ]);

        try {
            $response = Http::timeout(6)->withoutVerifying()->get($data['url']);

            return response()->json([
                'status' => 'ok',
                'message' => "Responde con HTTP {$response->status()}.",
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Sin respuesta: '.$e->getMessage()]);
        }
    }

    /**
     * Revisa la conexión a BD y las URLs de todos los sistemas en una sola
     * petición. El servidor de desarrollo (php artisan serve) en Windows es
     * de un solo hilo, así que disparar decenas de peticiones sueltas desde
     * el frontend las serializa; acá las URLs se resuelven con un pool HTTP
     * concurrente y las conexiones a BD usan un timeout corto para no
     * colgarse con hosts caídos.
     */
    public function verifyAll(): \Illuminate\Http\JsonResponse
    {
        $systems = SystemEntry::all();

        $urlTargets = [];
        foreach ($systems as $system) {
            foreach (['url_internal', 'url_external'] as $field) {
                if (filled($system->{$field})) {
                    $urlTargets["{$system->id}_{$field}"] = $system->{$field};
                }
            }
        }

        $urlResults = [];
        if ($urlTargets) {
            $responses = Http::pool(fn (Pool $pool) => collect($urlTargets)
                ->map(fn (string $url, string $key) => $pool->as($key)->timeout(5)->withoutVerifying()->get($url))
                ->all());

            foreach ($urlTargets as $key => $url) {
                $response = $responses[$key] ?? null;

                $urlResults[$key] = $response instanceof \Illuminate\Http\Client\Response
                    ? ['status' => 'ok', 'message' => "Responde con HTTP {$response->status()}."]
                    : ['status' => 'error', 'message' => 'Sin respuesta: '.($response?->getMessage() ?? 'error desconocido')];
            }
        }

        $connectionResults = [];
        foreach ($systems as $system) {
            if (! $system->hasOwnConnectionData()) {
                $connectionResults[$system->id] = ['status' => 'unavailable'];

                continue;
            }

            $tempName = "sysverify_{$system->id}";
            $driver = $system->db_driver ?: 'pgsql';

            Config::set("database.connections.{$tempName}", [
                'driver' => $driver,
                'host' => $system->db_host,
                'port' => $system->db_port ?: ($driver === 'mysql' ? 3306 : 5432),
                'database' => $system->db_database,
                'username' => $system->db_username,
                'password' => $system->db_password,
                'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
                'options' => [PDO::ATTR_TIMEOUT => 3],
            ]);

            try {
                DB::connection($tempName)->getPdo();
                $connectionResults[$system->id] = ['status' => 'ok', 'message' => 'Conexión exitosa.'];
            } catch (Throwable $e) {
                $connectionResults[$system->id] = ['status' => 'error', 'message' => 'No se pudo conectar: '.$e->getMessage()];
            } finally {
                DB::purge($tempName);
            }
        }

        return response()->json(['connections' => $connectionResults, 'urls' => $urlResults]);
    }
}
