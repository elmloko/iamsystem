<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

class SystemEntry extends Model
{
    protected $table = 'systems';

    protected $fillable = [
        'key', 'name', 'connection', 'users_table', 'id_column', 'filter_column', 'filter_value',
        'roles_table', 'roles_id_column', 'roles_name_column', 'model_type', 'status', 'notes',
        'repo_url', 'url_internal', 'url_external',
        'name_column', 'last_name_column', 'email_column', 'email_is_login', 'password_column', 'password_hash_algo', 'password_hash_key',
        'role_pivot_table', 'role_pivot_user_column', 'role_pivot_role_column',
        'role_column', 'role_json_column',
        'active_column', 'active_type', 'active_values', 'active_write_value', 'inactive_write_value',
        'alias_column', 'alias_required', 'hidden_roles', 'mandatory_roles',
        'created_at_column', 'created_at_format',
        'db_driver', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        'extra_fields', 'visible_in_public_form', 'public_form_restrictions',
    ];

    protected $casts = [
        'email_is_login' => 'boolean',
        'db_password' => 'encrypted',
        'password_hash_key' => 'encrypted',
        'extra_fields' => 'array',
        'public_form_restrictions' => 'array',
        'hidden_roles' => 'array',
        'mandatory_roles' => 'array',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(SystemAccount::class, 'system_id');
    }

    /**
     * Sistemas que tienen alguna forma de conectarse: el nombre de conexión
     * fijo legado (connection) o sus propios datos de conexión editados
     * desde el IAM (db_host + db_database).
     */
    public function scopeConnectable($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('connection')
                ->orWhere(function ($qq) {
                    $qq->whereNotNull('db_host')->whereNotNull('db_database');
                });
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && filled($this->remoteConnectionName());
    }

    public function hasOwnConnectionData(): bool
    {
        return filled($this->db_host) && filled($this->db_database);
    }

    /**
     * Columna que hace de PK en users_table. La mayoría de sistemas usan
     * "id" (por eso es el default), pero algunos legados (ej. CDS de la
     * UPU) usan una PK de texto con otro nombre (USER_CD).
     */
    public function idColumn(): string
    {
        return $this->id_column ?: 'id';
    }

    /**
     * Columnas de PK/nombre en roles_table (mecanismo "pivot"). Igual que
     * idColumn(), la mayoría de sistemas usan "id"/"name" (default), pero
     * algunos legados (ej. CDS/IPS de la UPU) usan otros nombres.
     */
    public function rolesIdColumn(): string
    {
        return $this->roles_id_column ?: 'id';
    }

    public function rolesNameColumn(): string
    {
        return $this->roles_name_column ?: 'name';
    }

    /**
     * Aplica el filtro fijo de fila configurado (filter_column = filter_value)
     * para sistemas que comparten una misma tabla física entre dos entradas
     * del IAM (ej. IPS Escritorio / IPSWeb, ambos en L_USERS discriminados
     * por la columna IPSWEB).
     */
    public function applyRowFilter($builder)
    {
        if ($this->filter_column) {
            $builder->where($this->filter_column, $this->filter_value);
        }

        return $builder;
    }

    /**
     * Devuelve el nombre de conexión a usar con DB::connection(). Si el
     * sistema tiene sus propios datos de conexión (host/usuario/password
     * editados desde el IAM), registra una conexión dinámica en runtime;
     * si no, cae al nombre de conexión fijo definido en config/database.php
     * (comportamiento legado, para sistemas que aún no se migraron).
     */
    public function remoteConnectionName(): ?string
    {
        if (! $this->hasOwnConnectionData()) {
            return $this->connection;
        }

        $name = "sysentry_{$this->id}";

        if (! Config::has("database.connections.{$name}")) {
            $driver = $this->db_driver ?: 'pgsql';
            $defaultPort = match ($driver) {
                'mysql' => 3306,
                'sqlsrv' => 1433,
                default => 5432,
            };

            Config::set("database.connections.{$name}", [
                'driver' => $driver,
                'host' => $this->db_host,
                'port' => $this->db_port ?: $defaultPort,
                'database' => $this->db_database,
                'username' => $this->db_username,
                'password' => $this->db_password,
                'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
                'collation' => $driver === 'mysql' ? 'utf8mb4_unicode_ci' : null,
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
                'trust_server_certificate' => $driver === 'sqlsrv' ? true : null,
            ]);
        }

        return $name;
    }

    /**
     * Excluye del builder las cuentas cuyo rol esté en hidden_roles (para
     * cuentas "no persona" como accesos de empresa/cliente que no se quieren
     * ver en el IAM). Solo soporta los mecanismos "pivot" y "column"; con
     * "json" no se aplica ningún filtro porque el rol está embebido en una
     * columna JSON del sistema remoto y no hay forma genérica de filtrarlo
     * en SQL sin conocer su estructura.
     */
    public function excludeHiddenRoles($builder)
    {
        if (empty($this->hidden_roles)) {
            return $builder;
        }

        if ($this->role_column) {
            return $builder->whereNotIn($this->role_column, $this->hidden_roles);
        }

        if ($this->role_pivot_table && $this->roles_table) {
            return $builder->whereNotIn($this->idColumn(), function ($q) {
                $q->select($this->role_pivot_user_column)
                    ->from($this->role_pivot_table)
                    ->join($this->roles_table, "{$this->roles_table}.{$this->rolesIdColumn()}", '=', "{$this->role_pivot_table}.{$this->role_pivot_role_column}")
                    ->whereIn("{$this->roles_table}.{$this->rolesNameColumn()}", $this->hidden_roles);

                if ($this->role_pivot_user_column === 'model_id') {
                    $q->where("{$this->role_pivot_table}.model_type", $this->model_type);
                }
            });
        }

        return $builder;
    }
}
