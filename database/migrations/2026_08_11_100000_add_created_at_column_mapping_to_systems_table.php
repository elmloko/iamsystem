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
            // Algunos sistemas no siguen la convención created_at/updated_at de
            // Laravel y usan su propia columna de fecha de alta (ej. SIGEC usa
            // fecha_creacion como timestamp Unix). Estas columnas permiten
            // mapearla igual que el resto de columnas del sistema remoto.
            $table->string('created_at_column')->nullable()->after('alias_column');
            $table->string('created_at_format')->nullable()->after('created_at_column'); // datetime | unix
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['created_at_column', 'created_at_format']);
        });
    }
};
