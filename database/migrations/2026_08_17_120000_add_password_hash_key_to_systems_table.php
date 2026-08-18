<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // Clave HMAC para sistemas cuyo hash de contraseña no es un
            // digest simple sino hash_hmac(algo, password, clave) — ej.
            // SIGEC usa el driver Auth de Kohana, que hashea con
            // hash_hmac('sha256', $password, config('auth.hash_key')).
            // Sin la clave correcta, la cuenta se crea pero su contraseña
            // nunca coincide con la que espera el sistema real.
            $table->text('password_hash_key')->nullable()->after('password_hash_algo');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('password_hash_key');
        });
    }
};
