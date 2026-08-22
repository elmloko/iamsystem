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
            // Algunos sistemas legados (ej. CDS) no tienen columna de email
            // real: email_column apunta al usuario de login (ej. USER_CD).
            // Sin esto, el formulario "Editar cuenta" exige formato de
            // correo válido y rechaza esos valores.
            $table->boolean('email_is_login')->default(false)->after('email_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('email_is_login');
        });
    }
};
