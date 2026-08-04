<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Algunos sistemas (ej. sistema_documentos) no tienen tabla de roles
     * separada: el rol vive como una columna de texto directa en la fila
     * del usuario (ej. users.role). Este campo permite describir ese caso
     * sin forzar el patrón de tabla+pivote.
     */
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->string('role_column')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('role_column');
        });
    }
};
