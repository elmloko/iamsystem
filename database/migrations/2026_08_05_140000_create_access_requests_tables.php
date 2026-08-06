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
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('password'); // cifrada (cast encrypted), se usa recién al aprobar cada sistema
            $table->timestamps();
        });

        Schema::create('access_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->string('role_id')->nullable();
            $table->string('role_name')->nullable();
            $table->string('alias')->nullable();
            $table->json('extra_fields')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('remote_user_id')->nullable();
            $table->string('outcome_status')->nullable(); // created | exists | failed
            $table->text('outcome_message')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_request_items');
        Schema::dropIfExists('access_requests');
    }
};
