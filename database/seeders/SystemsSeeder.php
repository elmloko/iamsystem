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
     *  - trackpak quedó "pending": el host nuevo (172.65.10.51) rechaza
     *    las credenciales agbc/Correos.2026 (Access denied) — hay que
     *    verificar la contraseña real con el equipo antes de activarlo.
     */
    public function run(): void
    {
        $active = [
            [
                'key' => 'bolipost', 'name' => 'Bolipost', 'connection' => 'sys_bolipost',
                'roles_table' => 'roles',
            ],
            [
                'key' => 'apifacturacion', 'name' => 'API Facturación AGBC', 'connection' => 'sys_apifacturacion',
                'users_table' => 'usuarios', 'roles_table' => 'roles',
                'role_pivot_table' => 'usuario_role', 'role_pivot_user_column' => 'usuario_id', 'role_pivot_role_column' => 'role_id',
            ],
            [
                'key' => 'back_atencion', 'name' => 'Atención al Cliente (Back)', 'connection' => 'sys_back_atencion',
                'roles_table' => 'roles',
            ],
            [
                'key' => 'backgescon', 'name' => 'Gescon', 'connection' => 'sys_backgescon',
                'name_column' => 'nombre', 'last_name_column' => 'apellidos',
                'roles_table' => null, // este sistema no tiene tablas de roles/permisos
                'notes' => 'Sin tabla de roles detectada en este sistema; solo se puede crear el usuario, sin rol.',
            ],
            [
                'key' => 'sistema_documentos', 'name' => 'Sistema de Documentos', 'connection' => 'sys_sistema_documentos',
                'roles_table' => 'roles',
            ],
            [
                'key' => 'filatelia', 'name' => 'Filatelia', 'connection' => 'sys_filatelia',
                'name_column' => 'full_name', 'password_column' => 'password_hash',
                'roles_table' => 'roles',
                'role_pivot_table' => 'user_roles', 'role_pivot_user_column' => 'user_id', 'role_pivot_role_column' => 'role_id',
            ],
            [
                'key' => 'helpdesk', 'name' => 'Helpdesk', 'connection' => 'sys_helpdesk',
                'roles_table' => 'roles',
            ],
            [
                'key' => 'sitra', 'name' => 'Sitra', 'connection' => 'sys_sitra',
                'roles_table' => 'roles',
            ],
            [
                'key' => 'backcasillas', 'name' => 'Back Casillas', 'connection' => 'sys_backcasillas',
                'name_column' => 'nombre', 'last_name_column' => 'apellidos',
                'roles_table' => 'roles',
                'role_pivot_table' => 'role_user', 'role_pivot_user_column' => 'user_id', 'role_pivot_role_column' => 'role_id',
            ],
            [
                'key' => 'calcupost', 'name' => 'Calcupost', 'connection' => 'sys_calcupost',
                'roles_table' => 'roles',
            ],
            [
                'key' => 'sysreclamos', 'name' => 'Sysreclamos', 'connection' => 'sys_sysreclamos',
                'roles_table' => 'roles',
            ],
        ];

        $pending = [
            ['key' => 'sgdb', 'name' => 'SGDB', 'notes' => 'Falta verificar credenciales/host en el .env real del sistema.'],
            ['key' => 'integracion', 'name' => 'Integración API', 'notes' => 'Falta verificar credenciales/host en el .env real del sistema.'],
            ['key' => 'intranetagencia', 'name' => 'Intranet Agencia', 'notes' => 'Falta verificar credenciales/host en el .env real del sistema.'],
            ['key' => 'miexpress', 'name' => 'Mi Express', 'notes' => 'Falta verificar credenciales/host en el .env real del sistema.'],
            ['key' => 'apiweb', 'name' => 'API Web', 'notes' => 'Falta verificar credenciales/host en el .env real del sistema.'],
            [
                'key' => 'trackpak', 'name' => 'Trackpak', 'connection' => 'sys_trackpak',
                'notes' => 'Host nuevo (172.65.10.51) rechaza la contraseña agbc/Correos.2026 (Access denied). Verificar credenciales reales antes de activar.',
            ],
        ];

        foreach ($active as $system) {
            SystemEntry::updateOrCreate(
                ['key' => $system['key']],
                array_merge([
                    'name' => $system['name'],
                    'connection' => $system['connection'],
                    'users_table' => $system['users_table'] ?? 'users',
                    'name_column' => $system['name_column'] ?? 'name',
                    'last_name_column' => $system['last_name_column'] ?? null,
                    'email_column' => $system['email_column'] ?? 'email',
                    'password_column' => $system['password_column'] ?? 'password',
                    'roles_table' => $system['roles_table'] ?? null,
                    'role_pivot_table' => $system['role_pivot_table'] ?? null,
                    'role_pivot_user_column' => $system['role_pivot_user_column'] ?? 'user_id',
                    'role_pivot_role_column' => $system['role_pivot_role_column'] ?? 'role_id',
                    'status' => 'active',
                    'notes' => $system['notes'] ?? null,
                ])
            );
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
