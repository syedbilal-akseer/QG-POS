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
        Schema::create('visit_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->enum('expense_type', [
                'business_meal',
                'fuel',
                'tools',
                'travel',
                'license_fee',
                'mobile_cards',
                'courier',
                'stationery',
                'legal_fees',
                'other'
            ]);
            $table->json('expense_details'); // Will contain date, description, amount, and details
            $table->decimal('total', 10, 2); // Total amount
            $table->longText('comments')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('line_manager_approval')->nullable();
            $table->boolean('hod_approval')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_expenses');
    }
};
