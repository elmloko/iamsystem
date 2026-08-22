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
            // Permite representar dos "sistemas" del IAM sobre la misma
            // tabla física (ej. IPS Escritorio / IPSWeb ambos en L_USERS,
            // discriminados por la columna IPSWEB), agregando un
            // WHERE filter_column = filter_value a todas las consultas.
            $table->string('filter_column')->nullable()->after('users_table');
            $table->string('filter_value')->nullable()->after('filter_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['filter_column', 'filter_value']);
        });
    }
};
