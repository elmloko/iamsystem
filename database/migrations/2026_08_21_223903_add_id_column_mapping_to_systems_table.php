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
            // Casi todos los sistemas usan una PK entera llamada "id" (por
            // eso el resto del código la da por sentada), pero algunos
            // sistemas legados (ej. CDS de la UPU) usan una PK de texto con
            // otro nombre (USER_CD). Nullable: NULL sigue significando "id",
            // como hasta ahora.
            $table->string('id_column')->nullable()->after('users_table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('id_column');
        });
    }
};
