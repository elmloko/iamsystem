<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemEntry;

class SystemsSeeder extends Seeder
{
    /**
     * Mapeo de columnas verificado contra el esquema REAL de cada BD el
     * 2026-08-02 (información_schema / SHOW COLUMNS), no asumido. Cada
     * sistema fue construido por separado y varios difieren del patrón
     * users/name/email/password:
     *  - apifacturacion usa tabla "usuarios" (columnas sí estándar) y
     *    pivote de roles "usuario_role".
     *  - backgescon y backcasillas separan nombre/apellidos y NO tienen
     *    tabla de roles (backgescon) o usan pivote "role_user" (backcasillas).
     *  - filatelia usa "full_name" y "password_hash" en vez de
     *    name/password, y pivote "user_roles".
     *  - bolipost/helpdesk/sitra/calcupost/sysreclamos usan el pivote
     *    polimórfico de spatie/laravel-permission ("model_has_roles" con
     *    model_id + model_type, no user_id).
     *  - sistema_documentos NO tiene tabla de roles: el rol es una columna
     *    de texto directa en users.role (ver role_column).
     *  - trackpak quedó "pending": el host nuevo (172.65.10.51) rechaza
     *    las credenciales agbc/Correos.2026 (Access denied) — hay que
     *    verificar la contraseña real con el equipo antes de activarlo.
     *
     * Columna de estado (activo/de baja) verificada el 2026-08-04 contra el
     * esquema real de cada sistema (information_schema). Tres patrones:
     *  - soft_delete: soft-delete estándar de Laravel, activo = deleted_at
     *    IS NULL (bolipost, sitra, calcupost, sysreclamos, trackpak).
     *  - boolean: columna booleana propia (is_active/activo/us_estado).
     *  - text: columna de texto (estado/status); apifacturacion, backgescon
     *    y filatelia no tenían filas para confirmar los valores reales al
     *    momento de mapear, así que usan la lista de valores por defecto.
     */
    public function run(): void
    {
        $active = [
            [
                'key' => 'bolipost', 'name' => 'Bolipost', 'connection' => 'sys_bolipost',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'deleted_at', 'active_type' => 'soft_delete',
                'alias_column' => 'alias', // único sistema con columna "alias" en users
            ],
            [
                'key' => 'apifacturacion', 'name' => 'API Facturación AGBC', 'connection' => 'sys_apifacturacion',
                'users_table' => 'usuarios', 'roles_table' => 'roles',
                'role_pivot_table' => 'usuario_role', 'role_pivot_user_column' => 'usuario_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'estado', 'active_type' => 'text',
            ],
            [
                'key' => 'back_atencion', 'name' => 'Atención al Cliente (Back)', 'connection' => 'sys_back_atencion',
                'roles_table' => 'roles',
                'role_pivot_table' => 'user_roles', 'role_pivot_user_column' => 'user_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'is_active', 'active_type' => 'boolean',
            ],
            [
                'key' => 'backgescon', 'name' => 'Gescon', 'connection' => 'sys_backgescon',
                'name_column' => 'nombre', 'last_name_column' => 'apellidos',
                'roles_table' => null, // este sistema no tiene tablas de roles/permisos
                'notes' => 'Sin tabla de roles detectada en este sistema; solo se puede crear el usuario, sin rol.',
                'active_column' => 'estado', 'active_type' => 'text',
            ],
            [
                'key' => 'sistema_documentos', 'name' => 'Sistema de Documentos', 'connection' => 'sys_sistema_documentos',
                'roles_table' => null, 'role_column' => 'role', // el rol es una columna de texto en users, no una tabla aparte
                'active_column' => 'is_active', 'active_type' => 'boolean',
            ],
            [
                'key' => 'filatelia', 'name' => 'Filatelia', 'connection' => 'sys_filatelia',
                'name_column' => 'full_name', 'password_column' => 'password_hash',
                'roles_table' => 'roles',
                'role_pivot_table' => 'user_roles', 'role_pivot_user_column' => 'user_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'status', 'active_type' => 'text',
            ],
            [
                'key' => 'helpdesk', 'name' => 'Helpdesk', 'connection' => 'sys_helpdesk',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'activo', 'active_type' => 'boolean',
            ],
            [
                'key' => 'sitra', 'name' => 'Sitra', 'connection' => 'sys_sitra',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'deleted_at', 'active_type' => 'soft_delete',
            ],
            [
                'key' => 'backcasillas', 'name' => 'Back Casillas', 'connection' => 'sys_backcasillas',
                'name_column' => 'nombre', 'last_name_column' => 'apellidos',
                'roles_table' => 'roles',
                'role_pivot_table' => 'role_user', 'role_pivot_user_column' => 'user_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'estado', 'active_type' => 'text',
            ],
            [
                'key' => 'calcupost', 'name' => 'Calcupost', 'connection' => 'sys_calcupost',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'deleted_at', 'active_type' => 'soft_delete',
            ],
            [
                'key' => 'sysreclamos', 'name' => 'Sysreclamos', 'connection' => 'sys_sysreclamos',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'deleted_at', 'active_type' => 'soft_delete',
            ],
            [
                'key' => 'trackpak', 'name' => 'Trackpak', 'connection' => 'sys_trackpak',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'deleted_at', 'active_type' => 'soft_delete',
            ],
            [
                'key' => 'sgdb', 'name' => 'SGDB', 'connection' => 'sys_sgdb',
                'role_json_column' => 'rol_global', // arreglo JSON de roles en la propia fila del usuario
                'active_column' => 'activo', 'active_type' => 'boolean',
            ],
            [
                'key' => 'integracion', 'name' => 'Integración API', 'connection' => 'sys_integracion',
                'role_column' => 'role', // rol como columna de texto directa
                'active_column' => 'status', 'active_type' => 'text',
            ],
            [
                'key' => 'apiweb', 'name' => 'API Web', 'connection' => 'sys_apiweb',
                'roles_table' => 'roles',
                'role_pivot_table' => 'model_has_roles', 'role_pivot_user_column' => 'model_id', 'role_pivot_role_column' => 'role_id',
                'active_column' => 'is_active', 'active_type' => 'boolean',
            ],
            [
                'key' => 'intranetagencia', 'name' => 'Intranet Agencia', 'connection' => 'sys_intranetagencia',
                'name_column' => 'us_nombrecompleto', 'email_column' => 'us_email',
                'role_column' => 'us_tipo', // solo 2 valores reales: USUARIO / ADMINISTRADOR
                'active_column' => 'us_estado', 'active_type' => 'boolean',
            ],
        ];

        $pending = [
            [
                'key' => 'miexpress', 'name' => 'Mi Express',
                'notes' => 'Host distinto a los demás: el .env real dentro del contenedor usa 172.65.10.24, usuario postgres (no agbc). Confirmar credenciales antes de activar.',
            ],
        ];

        foreach ($active as $system) {
            $existing = SystemEntry::where('key', $system['key'])->first();

            $payload = [
                'name' => $system['name'],
                'connection' => $system['connection'],
                'users_table' => $system['users_table'] ?? 'users',
                'name_column' => $system['name_column'] ?? 'name',
                'last_name_column' => $system['last_name_column'] ?? null,
                'email_column' => $system['email_column'] ?? 'email',
                'password_column' => $system['password_column'] ?? 'password',
                'model_type' => $system['model_type'] ?? 'App\\Models\\User',
                'roles_table' => $system['roles_table'] ?? null,
                'role_column' => $system['role_column'] ?? null,
                'role_json_column' => $system['role_json_column'] ?? null,
                'role_pivot_table' => $system['role_pivot_table'] ?? null,
                'role_pivot_user_column' => $system['role_pivot_user_column'] ?? 'user_id',
                'role_pivot_role_column' => $system['role_pivot_role_column'] ?? 'role_id',
                'active_column' => $system['active_column'] ?? null,
                'active_type' => $system['active_type'] ?? null,
                'active_values' => $system['active_values'] ?? null,
                'alias_column' => $system['alias_column'] ?? null,
                'status' => 'active',
                'notes' => $system['notes'] ?? null,
            ];

            // Los datos de conexión solo se rellenan la primera vez, a partir
            // del bloque fijo en config/database.php (que a su vez lee el
            // .env). Si el sistema ya tiene sus propios datos (editados desde
            // el IAM), no los pisamos al re-sembrar.
            if (! $existing || ! $existing->db_host) {
                $connConfig = config("database.connections.{$system['connection']}", []);

                $payload['db_driver'] = $connConfig['driver'] ?? null;
                $payload['db_host'] = $connConfig['host'] ?? null;
                $payload['db_port'] = $connConfig['port'] ?? null;
                $payload['db_database'] = $connConfig['database'] ?? null;
                $payload['db_username'] = $connConfig['username'] ?? null;
                $payload['db_password'] = $connConfig['password'] ?? null;
            }

            SystemEntry::updateOrCreate(['key' => $system['key']], $payload);
        }

        foreach ($pending as $system) {
            SystemEntry::updateOrCreate(
                ['key' => $system['key']],
                [
                    'name' => $system['name'],
                    'connection' => $system['connection'] ?? null,
                    'status' => 'pending',
                    'notes' => $system['notes'],
                ]
            );
        }
    }
}
