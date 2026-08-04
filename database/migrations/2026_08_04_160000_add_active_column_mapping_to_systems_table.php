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
            $table->string('active_column')->nullable()->after('role_json_column');
            $table->string('active_type')->nullable()->after('active_column'); // boolean | soft_delete | text
            $table->text('active_values')->nullable()->after('active_type'); // JSON con los valores de texto que cuentan como "activo"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['active_column', 'active_type', 'active_values']);
        });
    }
};
