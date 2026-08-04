<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * role_id ya no es siempre numérico: algunos sistemas guardan el rol
     * como un valor de texto directo en la fila del usuario. Se recrea la
     * columna como string en vez de usar ->change() para evitar depender
     * de doctrine/dbal.
     */
    public function up(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });

        Schema::table('system_accounts', function (Blueprint $table) {
            $table->string('role_id')->nullable()->after('remote_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('system_accounts', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });

        Schema::table('system_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('remote_user_id');
        });
    }
};
