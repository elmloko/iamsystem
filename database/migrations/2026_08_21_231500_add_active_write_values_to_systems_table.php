<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // Para active_type = 'text': active_values (ya existente) sirve
            // para LEER el estado (puede tener varios valores considerados
            // "activo"), pero para ESCRIBIR un alta/baja desde el IAM hace
            // falta un valor exacto y único a grabar en cada caso.
            $table->string('active_write_value')->nullable()->after('active_values');
            $table->string('inactive_write_value')->nullable()->after('active_write_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['active_write_value', 'inactive_write_value']);
        });
    }
};
