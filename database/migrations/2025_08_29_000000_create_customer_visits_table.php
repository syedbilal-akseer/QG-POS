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
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // User who made the visit
            $table->string('customer_id'); // Customer ID (string to match customers table)
            $table->decimal('latitude', 10, 8)->nullable(); // Latitude with precision
            $table->decimal('longitude', 11, 8)->nullable(); // Longitude with precision
            $table->timestamp('visit_start_time'); // Visit start timestamp
            $table->timestamp('visit_end_time')->nullable(); // Visit end timestamp (nullable for ongoing visits)
            $table->text('comments')->nullable(); // Visit comments/notes
            $table->json('images')->nullable(); // Store multiple image paths as JSON array
            $table->enum('status', ['ongoing', 'completed', 'cancelled'])->default('ongoing');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');

            // Indexes for better performance
            $table->index(['user_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('status');
            $table->index('visit_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};