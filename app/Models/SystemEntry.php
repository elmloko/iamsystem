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
        'name_column', 'last_name_column', 'email_column', 'password_column',
        'role_pivot_table', 'role_pivot_user_column', 'role_pivot_role_column',
        'role_column', 'role_json_column',
        'active_column', 'active_type', 'active_values',
        'alias_column',
        'db_driver', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        'extra_fields',
    ];

    protected $casts = [
        'db_password' => 'encrypted',
        'extra_fields' => 'array',
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
}
