<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SGDB guarda los roles como un arreglo JSON en una columna de la
     * propia fila del usuario (ej. users.rol_global = ["admin","qa"]),
     * a diferencia de role_column (un solo valor de texto plano).
     */
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->string('role_json_column')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('role_json_column');
        });
    }
};
