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
        Schema::create('receipt_cheques', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_receipt_id');
            $table->string('bank_name');
            $table->string('cheque_no');
            $table->decimal('cheque_amount', 15, 2);
            $table->date('cheque_date');
            $table->string('reference')->nullable();
            $table->text('comments')->nullable();
            $table->json('cheque_images')->nullable();
            $table->boolean('is_third_party_cheque')->default(false);
            $table->date('maturity_date')->nullable();
            $table->enum('status', ['pending', 'cleared', 'bounced', 'cancelled'])->default('pending');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('customer_receipt_id')->references('id')->on('customer_receipts')->onDelete('cascade');

            // Indexes for better performance
            $table->index('customer_receipt_id');
            $table->index('cheque_no');
            $table->index('cheque_date');
            $table->index('maturity_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_cheques');
    }
};