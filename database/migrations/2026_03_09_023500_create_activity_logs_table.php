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
        Schema::create('activity_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('user_id')->nullable();
            $blueprint->string('user_name')->nullable();
            $blueprint->string('action'); // e.g., 'login', 'create', 'update', 'delete'
            $blueprint->string('module'); // e.g., 'orders', 'receipts', 'promotions'
            $blueprint->string('description');
            $blueprint->string('ip_address')->nullable();
            $blueprint->json('properties')->nullable(); // Store details about the change
            $blueprint->timestamps();

            $blueprint->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
