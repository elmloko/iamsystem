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
            // Igual que id_column pero para roles_table: la mayoría de
            // sistemas usan "id"/"name" (por eso son nullable y caen a ese
            // default), pero algunos legados (ej. CDS/IPS de la UPU) usan
            // otros nombres (USER_GROUP_CD/USER_GROUP_NM).
            $table->string('roles_id_column')->nullable()->after('roles_table');
            $table->string('roles_name_column')->nullable()->after('roles_id_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['roles_id_column', 'roles_name_column']);
        });
    }
};
