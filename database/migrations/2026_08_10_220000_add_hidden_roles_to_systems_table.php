<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // Nombres de rol (tal cual están en la tabla de roles del sistema
            // remoto) cuyas cuentas se excluyen de todo el IAM: listados,
            // detalle de persona, conteos del dashboard. Pensado para roles
            // "no persona" como cuentas de empresa/cliente.
            $table->json('hidden_roles')->nullable()->after('alias_required');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('hidden_roles');
        });
    }
};
