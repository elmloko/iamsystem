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
     */
    public function fetchRoles(SystemEntry $system): Collection
    {
        if (! $system->isActive() || ! $system->roles_table) {
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
     * password_hash en vez de password, etc). Si se indica rol y el sistema
     * tiene tabla de pivote configurada, intenta asignarlo; si esa tabla no
     * existe o los nombres de columna no calzan, el usuario igual queda
     * creado, solo sin rol asignado.
     */
    public function createAccount(SystemEntry $system, Person $person, string $password, ?int $roleId, ?string $roleName): array
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

            $remoteId = DB::connection($connection)->table($usersTable)->insertGetId($row);

            $roleMessage = null;

            if ($roleId && $system->role_pivot_table) {
                try {
                    DB::connection($connection)->table($system->role_pivot_table)->insert([
                        $system->role_pivot_user_column => $remoteId,
                        $system->role_pivot_role_column => $roleId,
                    ]);
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
}
