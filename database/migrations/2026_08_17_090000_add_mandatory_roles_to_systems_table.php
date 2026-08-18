<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // Nombres de rol (tal cual están en la tabla de roles del sistema
            // remoto) que TODA cuenta necesita para poder entrar, además del
            // rol funcional que elige el admin. Ej: SIGEC exige el rol "login"
            // aparte de "usuario"/"jefe"/etc; sin él la contraseña es correcta
            // pero el sistema rechaza el acceso.
            $table->json('mandatory_roles')->nullable()->after('hidden_roles');
        });

        DB::table('systems')
            ->where('key', 'sigec')
            ->update(['mandatory_roles' => json_encode(['login'])]);
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('mandatory_roles');
        });
    }
};
