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
        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id');
            $table->string('receipt_number')->unique();
            $table->integer('receipt_year');
            $table->decimal('overall_credit_limit', 15, 2)->nullable();
            $table->decimal('outstanding', 15, 2)->nullable();
            $table->decimal('cash_amount', 15, 2)->nullable();
            $table->string('currency')->default('PKR');
            $table->date('cash_maturity_date')->nullable();
            $table->string('cheque_no')->nullable();
            $table->decimal('cheque_amount', 15, 2)->nullable();
            $table->date('maturity_date')->nullable();
            $table->text('cheque_comments')->nullable();
            $table->boolean('is_third_party_cheque')->default(false);
            $table->string('remittance_bank_id')->nullable();
            $table->string('remittance_bank_name')->nullable();
            $table->string('customer_bank_id')->nullable();
            $table->string('customer_bank_name')->nullable();
            $table->string('cheque_image')->nullable();
            $table->text('description');
            $table->enum('receipt_type', ['cash_only', 'cheque_only', 'cash_and_cheque']);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index('customer_id');
            $table->index('receipt_number');
            $table->index('receipt_year');
            $table->index('currency');
            $table->index('receipt_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_receipts');
    }
};