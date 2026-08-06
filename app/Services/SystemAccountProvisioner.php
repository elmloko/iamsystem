<?php

namespace App\Services;

use App\Models\Person;
use App\Models\SystemEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
                return DB::connection($system->remoteConnectionName())
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
                $values = DB::connection($system->remoteConnectionName())
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
            return DB::connection($system->remoteConnectionName())
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
     * Igual que fetchRoles(), pero para el formulario público de solicitud
     * de acceso: nunca se debe poder pedir un rol de administrador desde
     * ahí. Filtra por nombre (heurística: contiene "admin"), ya que ningún
     * sistema marca explícitamente qué rol es de administrador.
     */
    public function fetchPublicRoles(SystemEntry $system): Collection
    {
        return $this->fetchRoles($system)
            ->reject(fn ($role) => str_contains(mb_strtolower((string) $role->name), 'admin'))
            ->values();
    }

    /**
     * Campos adicionales configurados para {$system} (ver systems.extra_fields),
     * con las opciones de los selects resueltas en vivo contra la tabla de
     * referencia real (ej. "entidades", "oficinas") cuando corresponde. Los
     * campos con opciones fijas (definidas en la config) se devuelven tal
     * cual.
     */
    public function resolveExtraFields(SystemEntry $system): array
    {
        if (! $system->isActive() || empty($system->extra_fields)) {
            return [];
        }

        return array_map(function (array $field) use ($system) {
            if (in_array($field['type'], ['select', 'multiselect'], true) && ! empty($field['lookup_table'])) {
                try {
                    $field['options'] = DB::connection($system->remoteConnectionName())
                        ->table($field['lookup_table'])
                        ->select([
                            "{$field['lookup_value_column']} as value",
                            "{$field['lookup_label_column']} as label",
                        ])
                        ->orderBy($field['lookup_label_column'])
                        ->get()
                        ->toArray();
                } catch (Throwable $e) {
                    Log::warning("No se pudo leer opciones de [{$field['column']}] en [{$system->key}]: {$e->getMessage()}");
                    $field['options'] = [];
                }
            }

            return $field;
        }, $system->extra_fields);
    }

    /**
     * Crea la cuenta de {$person} en {$system}, respetando el mapeo real de
     * columnas de ese sistema (algunos separan nombre/apellidos, otros usan
     * password_hash en vez de password, etc). $roleId puede ser un id
     * numérico (tabla de roles + pivote) o un valor de texto (sistemas que
     * guardan el rol como columna directa, ej. sistema_documentos). Si el
     * sistema no tiene forma de asignar rol, el usuario igual queda creado.
     */
    public function createAccount(SystemEntry $system, Person $person, string $password, int|string|null $roleId, ?string $roleName, ?string $alias = null, array $extraFields = []): array
    {
        if (! $system->isActive()) {
            return ['status' => 'failed', 'message' => 'Sistema no configurado (conexión pendiente).'];
        }

        $connection = $system->remoteConnectionName();
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
                $system->password_column => $this->hashPassword($system, $password),
            ];

            // No todos los sistemas siguen la convención created_at/updated_at
            // de Laravel (ej. SIGEC usa fecha_creacion como timestamp Unix).
            if (Schema::connection($connection)->hasColumn($usersTable, 'created_at')) {
                $row['created_at'] = now();
            }
            if (Schema::connection($connection)->hasColumn($usersTable, 'updated_at')) {
                $row['updated_at'] = now();
            }

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

            if ($system->alias_column && filled($alias)) {
                $row[$system->alias_column] = $alias;
            }

            foreach ($system->extra_fields ?? [] as $field) {
                $value = $extraFields[$field['column']] ?? null;

                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                $row[$field['column']] = match ($field['type']) {
                    'multiselect' => ($field['store_as'] ?? 'json') === 'csv'
                        ? implode(',', (array) $value)
                        : json_encode(array_values((array) $value)),
                    default => $value,
                };
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
     * Actualiza nombre(s) y correo de una cuenta ya existente en {$system}.
     * Valida que el nuevo correo no choque con otra fila de ese mismo
     * sistema antes de escribir.
     */
    public function updateAccountFields(SystemEntry $system, int $remoteUserId, string $firstName, ?string $lastName, string $email, ?string $alias = null, ?string $password = null): array
    {
        if (! $system->isActive()) {
            return ['status' => 'failed', 'message' => 'Sistema no configurado (conexión pendiente).'];
        }

        $connection = $system->remoteConnectionName();
        $usersTable = $system->users_table ?: 'users';

        try {
            $duplicate = DB::connection($connection)->table($usersTable)
                ->where($system->email_column, $email)
                ->where('id', '!=', $remoteUserId)
                ->exists();

            if ($duplicate) {
                return ['status' => 'failed', 'message' => 'Ese correo ya está en uso por otra cuenta en este sistema.'];
            }

            $row = [
                $system->name_column => $firstName,
                $system->email_column => $email,
            ];

            if ($system->last_name_column) {
                $row[$system->last_name_column] = $lastName ?? '';
            }

            if ($system->alias_column) {
                $row[$system->alias_column] = $alias;
            }

            if (filled($password)) {
                $row[$system->password_column] = $this->hashPassword($system, $password);
            }

            DB::connection($connection)->table($usersTable)->where('id', $remoteUserId)->update($row);

            return ['status' => 'updated'];
        } catch (Throwable $e) {
            Log::error("Fallo actualizando cuenta en [{$system->key}]: {$e->getMessage()}");

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Da de alta/baja una cuenta. Solo soportado en sistemas con columna de
     * estado inequívoca (booleana o soft-delete); en sistemas con columna de
     * texto (active_type = 'text') no se sabe con certeza qué valor escribir
     * para "de baja", así que se rechaza en vez de arriesgar datos.
     */
    public function setAccountActive(SystemEntry $system, int $remoteUserId, bool $active): array
    {
        if (! $system->isActive()) {
            return ['status' => 'failed', 'message' => 'Sistema no configurado (conexión pendiente).'];
        }

        if (! in_array($system->active_type, ['boolean', 'soft_delete'], true)) {
            return ['status' => 'failed', 'message' => 'Este sistema no permite cambiar el estado desde el IAM.'];
        }

        try {
            $value = $system->active_type === 'soft_delete'
                ? ($active ? null : now())
                : $active;

            DB::connection($system->remoteConnectionName())
                ->table($system->users_table ?: 'users')
                ->where('id', $remoteUserId)
                ->update([$system->active_column => $value]);

            return ['status' => 'updated'];
        } catch (Throwable $e) {
            Log::error("Fallo cambiando estado en [{$system->key}]: {$e->getMessage()}");

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Agrega un rol adicional a una cuenta ya existente. En sistemas con
     * pivote (tabla de roles) inserta la relación si no existe todavía; en
     * sistemas con rol como columna JSON, lo agrega al arreglo; en sistemas
     * con rol como columna de texto simple solo se puede reemplazar (no
     * soportan más de un rol por usuario).
     */
    public function addAccountRole(SystemEntry $system, int $remoteUserId, int|string $roleId, ?string $roleName): array
    {
        if (! $system->isActive()) {
            return ['status' => 'failed', 'message' => 'Sistema no configurado (conexión pendiente).'];
        }

        $connection = $system->remoteConnectionName();
        $usersTable = $system->users_table ?: 'users';

        try {
            if ($system->role_column) {
                DB::connection($connection)->table($usersTable)
                    ->where('id', $remoteUserId)
                    ->update([$system->role_column => $roleId]);

                return ['status' => 'updated', 'message' => 'Este sistema solo admite un rol por usuario; se reemplazó el anterior.'];
            }

            if ($system->role_json_column) {
                $current = DB::connection($connection)->table($usersTable)->where('id', $remoteUserId)->value($system->role_json_column);
                $roles = $this->decodeRoleArray($current);

                if ($roles->contains($roleId)) {
                    return ['status' => 'exists', 'message' => 'La cuenta ya tiene ese rol.'];
                }

                $roles->push($roleId);

                DB::connection($connection)->table($usersTable)
                    ->where('id', $remoteUserId)
                    ->update([$system->role_json_column => json_encode($roles->values()->all())]);

                return ['status' => 'updated'];
            }

            if ($system->role_pivot_table && $system->roles_table) {
                $existsQuery = DB::connection($connection)->table($system->role_pivot_table)
                    ->where($system->role_pivot_user_column, $remoteUserId)
                    ->where($system->role_pivot_role_column, $roleId);

                if ($system->role_pivot_user_column === 'model_id') {
                    $existsQuery->where('model_type', $system->model_type);
                }

                if ($existsQuery->exists()) {
                    return ['status' => 'exists', 'message' => 'La cuenta ya tiene ese rol.'];
                }

                $pivotRow = [
                    $system->role_pivot_user_column => $remoteUserId,
                    $system->role_pivot_role_column => $roleId,
                ];

                if ($system->role_pivot_user_column === 'model_id') {
                    $pivotRow['model_type'] = $system->model_type;
                }

                DB::connection($connection)->table($system->role_pivot_table)->insert($pivotRow);

                return ['status' => 'updated'];
            }

            return ['status' => 'failed', 'message' => 'Este sistema no tiene un mecanismo de roles reconocible.'];
        } catch (Throwable $e) {
            Log::error("Fallo agregando rol en [{$system->key}]: {$e->getMessage()}");

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Quita un rol de una cuenta. En sistemas con rol como columna de texto
     * simple, "quitar" deja la columna en null (no hay otro rol al que
     * volver). En pivote/JSON, elimina solo esa relación/valor puntual.
     */
    public function removeAccountRole(SystemEntry $system, int $remoteUserId, int|string $roleId): array
    {
        if (! $system->isActive()) {
            return ['status' => 'failed', 'message' => 'Sistema no configurado (conexión pendiente).'];
        }

        $connection = $system->remoteConnectionName();
        $usersTable = $system->users_table ?: 'users';

        try {
            if ($system->role_column) {
                DB::connection($connection)->table($usersTable)
                    ->where('id', $remoteUserId)
                    ->update([$system->role_column => null]);

                return ['status' => 'updated'];
            }

            if ($system->role_json_column) {
                $current = DB::connection($connection)->table($usersTable)->where('id', $remoteUserId)->value($system->role_json_column);
                $roles = $this->decodeRoleArray($current)
                    ->reject(fn ($value) => (string) $value === (string) $roleId)
                    ->values();

                DB::connection($connection)->table($usersTable)
                    ->where('id', $remoteUserId)
                    ->update([$system->role_json_column => json_encode($roles->all())]);

                return ['status' => 'updated'];
            }

            if ($system->role_pivot_table && $system->roles_table) {
                $query = DB::connection($connection)->table($system->role_pivot_table)
                    ->where($system->role_pivot_user_column, $remoteUserId)
                    ->where($system->role_pivot_role_column, $roleId);

                if ($system->role_pivot_user_column === 'model_id') {
                    $query->where('model_type', $system->model_type);
                }

                $query->delete();

                return ['status' => 'updated'];
            }

            return ['status' => 'failed', 'message' => 'Este sistema no tiene un mecanismo de roles reconocible.'];
        } catch (Throwable $e) {
            Log::error("Fallo quitando rol en [{$system->key}]: {$e->getMessage()}");

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * No todos los sistemas guardan contraseñas con bcrypt (el hash de
     * Laravel): varios sistemas legados usan un algoritmo simple propio.
     * Sin esto, la cuenta se crea pero el login del sistema real nunca
     * reconoce la contraseña porque el formato del hash no coincide.
     */
    private function hashPassword(SystemEntry $system, string $password): string
    {
        return match ($system->password_hash_algo) {
            'sha256' => hash('sha256', $password),
            'md5' => md5($password),
            'sha1' => sha1($password),
            'plain' => $password,
            default => Hash::make($password),
        };
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
        $systems = SystemEntry::where('status', 'active')->connectable()->get();

        $out = collect();

        foreach ($systems as $system) {
            try {
                $connection = $system->remoteConnectionName();
                $usersTable = $system->users_table ?: 'users';
                $hasCreatedAt = Schema::connection($connection)->hasColumn($usersTable, 'created_at');

                $matchExpr = $system->last_name_column
                    ? "LOWER(TRIM(CONCAT({$system->name_column}, ' ', {$system->last_name_column})))"
                    : "LOWER(TRIM({$system->name_column}))";

                $selectCols = "id, {$system->name_column} as first_name, {$system->email_column} as email";
                if ($hasCreatedAt) {
                    $selectCols .= ", created_at";
                }
                if ($system->last_name_column) {
                    $selectCols .= ", {$system->last_name_column} as last_name";
                }
                if ($system->role_column) {
                    $selectCols .= ", {$system->role_column} as inline_role";
                }
                if ($system->role_json_column) {
                    $selectCols .= ", {$system->role_json_column} as inline_roles_json";
                }
                if ($system->active_column) {
                    $selectCols .= ", {$system->active_column} as inline_active";
                }
                if ($system->alias_column) {
                    $selectCols .= ", {$system->alias_column} as inline_alias";
                }

                $rows = DB::connection($connection)
                    ->table($usersTable)
                    ->whereRaw("{$matchExpr} = ?", [$normalized])
                    ->selectRaw($selectCols)
                    ->get();

                foreach ($rows as $row) {
                    $roles = match (true) {
                        (bool) $system->role_column => array_values(array_filter([$row->inline_role ?? null]))
                            ? [['id' => $row->inline_role, 'name' => $row->inline_role]]
                            : [],
                        (bool) $system->role_json_column => $this->decodeRoleArray($row->inline_roles_json ?? null)
                            ->map(fn ($value) => ['id' => $value, 'name' => $value])
                            ->values()->all(),
                        default => $this->fetchRolesForUser($system, $row->id),
                    };

                    $out->push([
                        'system_id' => $system->id,
                        'system_key' => $system->key,
                        'system_name' => $system->name,
                        'remote_user_id' => $row->id,
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name ?? null,
                        'has_last_name' => (bool) $system->last_name_column,
                        'alias' => $row->inline_alias ?? null,
                        'has_alias' => (bool) $system->alias_column,
                        'email' => $row->email,
                        'created_at' => $row->created_at ?? null,
                        'roles' => $roles,
                        'active' => $this->resolveActiveStatus($system, $row->inline_active ?? null),
                        'active_editable' => in_array($system->active_type, ['boolean', 'soft_delete'], true),
                        'roles_editable' => (bool) ($system->role_pivot_table && $system->roles_table) || (bool) $system->role_json_column || (bool) $system->role_column,
                        'single_role' => (bool) $system->role_column,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning("Fallo trayendo detalle en [{$system->key}]: {$e->getMessage()}");
            }
        }

        return $out;
    }

    /**
     * Valores de texto que se consideran "activo" cuando el sistema no
     * define una lista propia en active_values (systems sin filas al
     * momento de mapear, ej. apifacturacion/backgescon/filatelia).
     */
    private const DEFAULT_ACTIVE_TEXT_VALUES = [
        'activo', 'active', 'habilitado', 'enabled', 'si', 'sí', 'true', '1', 'a',
    ];

    /**
     * Normaliza el valor crudo de la columna de estado de cada sistema a un
     * booleano (true = activo, false = de baja, null = sistema sin columna
     * de estado mapeada, no se sabe).
     */
    private function resolveActiveStatus(SystemEntry $system, mixed $rawValue): ?bool
    {
        if (! $system->active_column || ! $system->active_type) {
            return null;
        }

        return match ($system->active_type) {
            'soft_delete' => $rawValue === null,
            'boolean' => (bool) $rawValue,
            'text' => in_array(
                mb_strtolower(trim((string) $rawValue)),
                $system->active_values
                    ? json_decode($system->active_values, true)
                    : self::DEFAULT_ACTIVE_TEXT_VALUES,
                true
            ),
            default => null,
        };
    }

    private function fetchRolesForUser(SystemEntry $system, int $remoteUserId): array
    {
        if (! $system->role_pivot_table || ! $system->roles_table) {
            return [];
        }

        try {
            $builder = DB::connection($system->remoteConnectionName())
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

            return $builder
                ->select("{$system->roles_table}.id", "{$system->roles_table}.name")
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                ->all();
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
    public function listGroupedByName(?string $query = null, ?string $systemKey = null, ?string $status = null, int $perSystemCap = 300): Collection
    {
        $systems = SystemEntry::where('status', 'active')
            ->connectable()
            ->when($systemKey, fn ($q) => $q->where('key', $systemKey))
            ->get();

        $rows = collect();

        foreach ($systems as $system) {
            try {
                $nameExpr = $system->last_name_column
                    ? "concat({$system->name_column}, ' ', {$system->last_name_column})"
                    : $system->name_column;

                $selectCols = "id, {$nameExpr} as name, {$system->email_column} as email";
                if ($system->active_column) {
                    $selectCols .= ", {$system->active_column} as inline_active";
                }

                $builder = DB::connection($system->remoteConnectionName())
                    ->table($system->users_table ?: 'users')
                    ->selectRaw($selectCols);

                if ($query) {
                    $builder->where(function ($q) use ($system, $query) {
                        $q->where($system->name_column, 'like', "%{$query}%")
                            ->when($system->last_name_column, fn ($qq) => $qq->orWhere($system->last_name_column, 'like', "%{$query}%"))
                            ->orWhere($system->email_column, 'like', "%{$query}%");
                    });
                }

                $results = $builder->orderBy($system->name_column)->limit($perSystemCap)->get();

                foreach ($results as $row) {
                    $active = $this->resolveActiveStatus($system, $row->inline_active ?? null);

                    if ($status === 'active' && $active !== true) {
                        continue;
                    }
                    if ($status === 'inactive' && $active !== false) {
                        continue;
                    }

                    $rows->push([
                        'name' => trim((string) $row->name) ?: '(sin nombre)',
                        'email' => $row->email,
                        'system_key' => $system->key,
                        'system_name' => $system->name,
                        'active' => $active,
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
                        'active' => $row['active'],
                    ])->values(),
                ];
            })
            ->sortBy(fn ($group) => mb_strtolower($group['name']))
            ->values();
    }
}
