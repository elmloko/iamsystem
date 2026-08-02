<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->unsignedBigInteger('remote_user_id')->nullable();
            $table->string('role_name')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'system_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_accounts');
    }
};
