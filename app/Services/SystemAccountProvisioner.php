<?php

namespace App\Services;

use App\Models\Person;
use App\Models\SystemEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemAccountProvisioner
{
    /**
     * Roles existentes en el sistema remoto (no los crea, solo los lista).
     * Algunos sistemas no tienen tabla de roles: el rol es una columna de
     * texto libre en la propia fila del usuario (ej. sistema_documentos).
     * En ese caso se listan los valores distintos ya en uso, para no dejar
     * que el admin invente roles nuevos a mano.
     */
    public function fetchRoles(SystemEntry $system): Collection
    {
        if (! $system->isActive()) {
            return collect();
        }

        if ($system->role_column) {
            try {
                return DB::connection($system->connection)
                    ->table($system->users_table ?: 'users')
                    ->whereNotNull($system->role_column)
                    ->where($system->role_column, '!=', '')
                    ->distinct()
                    ->orderBy($system->role_column)
                    ->pluck($system->role_column)
                    ->map(fn ($value) => (object) ['id' => $value, 'name' => $value]);
            } catch (Throwable $e) {
                Log::warning("No se pudo leer valores de rol en [{$system->key}]: {$e->getMessage()}");

                return collect();
            }
        }

        if ($system->role_json_column) {
            try {
                $values = DB::connection($system->connection)
                    ->table($system->users_table ?: 'users')
                    ->whereNotNull($system->role_json_column)
                    ->pluck($system->role_json_column);

                return $values
                    ->flatMap(fn ($json) => $this->decodeRoleArray($json))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(fn ($value) => (object) ['id' => $value, 'name' => $value]);
            } catch (Throwable $e) {
                Log::warning("No se pudo leer roles JSON en [{$system->key}]: {$e->getMessage()}");

                return collect();
            }
        }

        if (! $system->roles_table) {
            return collect();
        }

        try {
            return DB::connection($system->connection)
                ->table($system->roles_table)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        } catch (Throwable $e) {
            Log::warning("No se pudo leer roles de [{$system->key}]: {$e->getMessage()}");

            return collect();
        }
    }

    /**
     * Crea la cuenta de {$person} en {$system}, respetando el mapeo real de
     * columnas de ese sistema (algunos separan nombre/apellidos, otros usan
     * password_hash en vez de password, etc). $roleId puede ser un id
     * numérico (tabla de roles + pivote) o un valor de texto (sistemas que
     * guardan el rol como columna directa, ej. sistema_documentos). Si el
     * sistema no tiene forma de asignar rol, el usuario igual queda creado.
     */
    public function createAccount(SystemEntry $system, Person $person, string $password, int|string|null $roleId, ?string $roleName): array
    {
        if (! $system->isActive()) {
            return ['status' => 'failed', 'message' => 'Sistema no configurado (conexión pendiente).'];
        }

        $connection = $system->connection;
        $usersTable = $system->users_table ?: 'users';

        try {
            $existing = DB::connection($connection)->table($usersTable)
                ->where($system->email_column, $person->email)
                ->first();

            if ($existing) {
                return [
                    'status' => 'exists',
                    'remote_user_id' => $existing->id,
                    'message' => 'Ya existía un usuario con ese email en este sistema.',
                ];
            }

            $row = [
                $system->email_column => $person->email,
                $system->password_column => Hash::make($password),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($system->last_name_column) {
                [$firstName, $lastName] = $this->splitName($person->name);
                $row[$system->name_column] = $firstName;
                $row[$system->last_name_column] = $lastName;
            } else {
                $row[$system->name_column] = $person->name;
            }

            if ($system->role_column && $roleId) {
                $row[$system->role_column] = $roleId;
            }

            if ($system->role_json_column && $roleId) {
                $row[$system->role_json_column] = json_encode([$roleId]);
            }

            $remoteId = DB::connection($connection)->table($usersTable)->insertGetId($row);

            $roleMessage = null;

            if ($system->role_column || $system->role_json_column) {
                // el rol ya quedó en la fila insertada, nada más que hacer
            } elseif ($roleId && $system->role_pivot_table) {
                try {
                    $pivotRow = [
                        $system->role_pivot_user_column => $remoteId,
                        $system->role_pivot_role_column => $roleId,
                    ];

                    if ($system->role_pivot_user_column === 'model_id') {
                        $pivotRow['model_type'] = $system->model_type;
                    }

                    DB::connection($connection)->table($system->role_pivot_table)->insert($pivotRow);
                } catch (Throwable $e) {
                    $roleMessage = 'Usuario creado, pero no se pudo asignar el rol automáticamente: '.$e->getMessage();
                    Log::warning("Fallo asignando rol en [{$system->key}]: {$e->getMessage()}");
                }
            } elseif ($roleId) {
                $roleMessage = 'Usuario creado. Este sistema no tiene pivote de roles configurado, así que el rol no se pudo asignar automáticamente.';
            }

            return [
                'status' => 'created',
                'remote_user_id' => $remoteId,
                'role_name' => $roleName,
                'message' => $roleMessage,
            ];
        } catch (Throwable $e) {
            Log::error("Fallo creando usuario en [{$system->key}]: {$e->getMessage()}");

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Busca por nombre EN VIVO en todos los sistemas activos (no solo los
     * creados desde este IAM), agrupado por sistema.
     */
    public function searchByName(string $query): Collection
    {
        $systems = SystemEntry::where('status', 'active')->whereNotNull('connection')->get();

        return $systems->map(function (SystemEntry $system) use ($query) {
            try {
                $nameExpr = $system->last_name_column
                    ? "concat({$system->name_column}, ' ', {$system->last_name_column})"
                    : $system->name_column;

                $results = DB::connection($system->connection)
                    ->table($system->users_table ?: 'users')
                    ->where($system->name_column, 'like', "%{$query}%")
                    ->when($system->last_name_column, fn ($q) => $q->orWhere($system->last_name_column, 'like', "%{$query}%"))
                    ->orWhere($system->email_column, 'like', "%{$query}%")
                    ->selectRaw("id, {$nameExpr} as name, {$system->email_column} as email")
                    ->limit(20)
                    ->get();

                return [
                    'system' => ['key' => $system->key, 'name' => $system->name],
                    'results' => $results,
                    'error' => null,
                ];
            } catch (Throwable $e) {
                Log::warning("Fallo buscando en [{$system->key}]: {$e->getMessage()}");

                return [
                    'system' => ['key' => $system->key, 'name' => $system->name],
                    'results' => collect(),
                    'error' => 'No se pudo consultar este sistema.',
                ];
            }
        })->filter(fn ($entry) => $entry['results']->isNotEmpty() || $entry['error'])->values();
    }

    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Decodifica una columna JSON de roles (ej. ["admin","qa"]). El driver
     * pgsql puede devolverlo ya como string JSON o, según el caso, como
     * valor nativo; se cubren ambos.
     */
    private function decodeRoleArray(mixed $json): Collection
    {
        if (is_array($json)) {
            return collect($json);
        }

        if (! is_string($json) || $json === '') {
            return collect();
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? collect($decoded) : collect();
    }

    /**
     * Detalle completo por sistema para una persona (por nombre exacto,
     * normalizado): email, fecha de creación y roles reales asignados en
     * cada sistema donde tiene cuenta. Se usa para el modal "Ver".
     */
    public function getPersonDetail(string $name): Collection
    {
        $normalized = mb_strtolower(trim($name));
        $systems = SystemEntry::where('status', 'active')->whereNotNull('connection')->get();

        $out = collect();

        foreach ($systems as $system) {
            try {
                $usersTable = $system->users_table ?: 'users';

                $matchExpr = $system->last_name_column
                    ? "LOWER(TRIM(CONCAT({$system->name_column}, ' ', {$system->last_name_column})))"
                    : "LOWER(TRIM({$system->name_column}))";

                $selectCols = "id, {$system->email_column} as email, created_at";
                if ($system->role_column) {
                    $selectCols .= ", {$system->role_column} as inline_role";
                }
                if ($system->role_json_column) {
                    $selectCols .= ", {$system->role_json_column} as inline_roles_json";
                }

                $rows = DB::connection($system->connection)
                    ->table($usersTable)
                    ->whereRaw("{$matchExpr} = ?", [$normalized])
                    ->selectRaw($selectCols)
                    ->get();

                foreach ($rows as $row) {
                    $roles = match (true) {
                        (bool) $system->role_column => array_values(array_filter([$row->inline_role ?? null])),
                        (bool) $system->role_json_column => $this->decodeRoleArray($row->inline_roles_json ?? null)->values()->all(),
                        default => $this->fetchRolesForUser($system, $row->id),
                    };

                    $out->push([
                        'system_key' => $system->key,
                        'system_name' => $system->name,
                        'email' => $row->email,
                        'created_at' => $row->created_at,
                        'roles' => $roles,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning("Fallo trayendo detalle en [{$system->key}]: {$e->getMessage()}");
            }
        }

        return $out;
    }

    private function fetchRolesForUser(SystemEntry $system, int $remoteUserId): array
    {
        if (! $system->role_pivot_table || ! $system->roles_table) {
            return [];
        }

        try {
            $builder = DB::connection($system->connection)
                ->table($system->role_pivot_table)
                ->join(
                    $system->roles_table,
                    "{$system->role_pivot_table}.{$system->role_pivot_role_column}",
                    '=',
                    "{$system->roles_table}.id"
                )
                ->where("{$system->role_pivot_table}.{$system->role_pivot_user_column}", $remoteUserId);

            // pivote polimórfico estilo spatie/laravel-permission (model_has_roles)
            if ($system->role_pivot_user_column === 'model_id') {
                $builder->where("{$system->role_pivot_table}.model_type", $system->model_type);
            }

            return $builder->pluck("{$system->roles_table}.name")->toArray();
        } catch (Throwable $e) {
            Log::warning("Fallo trayendo roles de usuario en [{$system->key}]: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Trae usuarios reales EN VIVO de todos los sistemas activos (o de uno
     * solo si se filtra por $systemKey) y los agrupa por nombre, para que
     * una misma persona con cuenta en varios sistemas aparezca en una sola
     * fila con una etiqueta por sistema. Sin $query trae hasta $perSystemCap
     * filas por sistema (ordenadas alfabéticamente) para no cargar tablas
     * enteras en cada visita a la pantalla.
     */
    public function listGroupedByName(?string $query = null, ?string $systemKey = null, int $perSystemCap = 300): Collection
    {
        $systems = SystemEntry::where('status', 'active')
            ->whereNotNull('connection')
            ->when($systemKey, fn ($q) => $q->where('key', $systemKey))
            ->get();

        $rows = collect();

        foreach ($systems as $system) {
            try {
                $nameExpr = $system->last_name_column
                    ? "concat({$system->name_column}, ' ', {$system->last_name_column})"
                    : $system->name_column;

                $builder = DB::connection($system->connection)
                    ->table($system->users_table ?: 'users')
                    ->selectRaw("id, {$nameExpr} as name, {$system->email_column} as email");

                if ($query) {
                    $builder->where(function ($q) use ($system, $query) {
                        $q->where($system->name_column, 'like', "%{$query}%")
                            ->when($system->last_name_column, fn ($qq) => $qq->orWhere($system->last_name_column, 'like', "%{$query}%"))
                            ->orWhere($system->email_column, 'like', "%{$query}%");
                    });
                }

                $results = $builder->orderBy($system->name_column)->limit($perSystemCap)->get();

                foreach ($results as $row) {
                    $rows->push([
                        'name' => trim((string) $row->name) ?: '(sin nombre)',
                        'email' => $row->email,
                        'system_key' => $system->key,
                        'system_name' => $system->name,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning("Fallo listando usuarios de [{$system->key}]: {$e->getMessage()}");
            }
        }

        return $rows
            ->groupBy(fn ($row) => mb_strtolower($row['name']))
            ->map(function ($group) {
                return [
                    'name' => $group->first()['name'],
                    'accounts' => $group->map(fn ($row) => [
                        'system_key' => $row['system_key'],
                        'system_name' => $row['system_name'],
                        'email' => $row['email'],
                    ])->values(),
                ];
            })
            ->sortBy(fn ($group) => mb_strtolower($group['name']))
            ->values();
    }
}
