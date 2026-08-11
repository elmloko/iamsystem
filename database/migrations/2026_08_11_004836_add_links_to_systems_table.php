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
            $table->string('repo_url')->nullable()->after('notes');
            $table->string('url_internal')->nullable()->after('repo_url');
            $table->string('url_external')->nullable()->after('url_internal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['repo_url', 'url_internal', 'url_external']);
        });
    }
};
