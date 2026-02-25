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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('customer_code');
            $table->string('customer_name');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('pdf_path');
            $table->json('extracted_pages')->nullable(); // Store page numbers that belong to this customer
            $table->string('page_range')->nullable(); // Human readable page range like "1-2"
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->index(['customer_code', 'processing_status']);
            $table->index(['uploaded_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIf('invoices');
    }
};