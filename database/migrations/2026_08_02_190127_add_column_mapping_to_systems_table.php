<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada sistema fue construido por separado y no todos siguen el mismo
     * esquema de tabla users (algunos usan nombre+apellidos en vez de name,
     * password_hash en vez de password, tablas de pivote de roles distintas,
     * etc). Estos campos permiten describir esa forma real por sistema.
     */
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->string('name_column')->default('name');
            $table->string('last_name_column')->nullable();
            $table->string('email_column')->default('email');
            $table->string('password_column')->default('password');
            $table->string('role_pivot_table')->nullable();
            $table->string('role_pivot_user_column')->default('user_id');
            $table->string('role_pivot_role_column')->default('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn([
                'name_column', 'last_name_column', 'email_column',
                'password_column', 'role_pivot_table',
                'role_pivot_user_column', 'role_pivot_role_column',
            ]);
        });
    }
};
