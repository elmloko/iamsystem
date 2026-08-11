<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

class SystemEntry extends Model
{
    protected $table = 'systems';

    protected $fillable = [
        'key', 'name', 'connection', 'users_table',
        'roles_table', 'model_type', 'status', 'notes',
        'repo_url', 'url_internal', 'url_external',
        'name_column', 'last_name_column', 'email_column', 'password_column', 'password_hash_algo',
        'role_pivot_table', 'role_pivot_user_column', 'role_pivot_role_column',
        'role_column', 'role_json_column',
        'active_column', 'active_type', 'active_values',
        'alias_column', 'alias_required', 'hidden_roles',
        'created_at_column', 'created_at_format',
        'db_driver', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        'extra_fields', 'visible_in_public_form', 'public_form_restrictions',
    ];

    protected $casts = [
        'db_password' => 'encrypted',
        'extra_fields' => 'array',
        'public_form_restrictions' => 'array',
        'hidden_roles' => 'array',
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

            Config::set("database.connections.{$name}", [
                'driver' => $driver,
                'host' => $this->db_host,
                'port' => $this->db_port ?: ($driver === 'mysql' ? 3306 : 5432),
                'database' => $this->db_database,
                'username' => $this->db_username,
                'password' => $this->db_password,
                'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
                'collation' => $driver === 'mysql' ? 'utf8mb4_unicode_ci' : null,
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
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
            return $builder->whereNotIn('id', function ($q) {
                $q->select($this->role_pivot_user_column)
                    ->from($this->role_pivot_table)
                    ->join($this->roles_table, "{$this->roles_table}.id", '=', "{$this->role_pivot_table}.{$this->role_pivot_role_column}")
                    ->whereIn("{$this->roles_table}.name", $this->hidden_roles);

                if ($this->role_pivot_user_column === 'model_id') {
                    $q->where("{$this->role_pivot_table}.model_type", $this->model_type);
                }
            });
        }

        return $builder;
    }
}
