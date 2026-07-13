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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('start_date'); // Start date of leave
            $table->date('end_date'); // End date of leave (can be same as start_date for single day)
            $table->integer('total_days'); // Total leave days (calculated)
            $table->decimal('lat', 10, 8); // Latitude where leave was marked
            $table->decimal('lng', 11, 8); // Longitude where leave was marked
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('leave_type', ['casual', 'sick', 'annual', 'emergency', 'other'])->default('casual');
            $table->text('reason')->nullable(); // Reason for leave
            $table->text('rejection_reason')->nullable(); // Reason for rejection (if rejected)
            $table->unsignedBigInteger('approved_by')->nullable(); // Who approved/rejected
            $table->timestamp('approved_at')->nullable(); // When approved/rejected
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index(['user_id', 'start_date']);
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            
            // Ensure end_date is not before start_date
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};