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
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ledger_import_id')->nullable();
            $table->string('original_filename');
            $table->string('source_file_hash')->nullable();
            $table->string('customer_code');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('salesperson_raw')->nullable();
            $table->unsignedBigInteger('salesperson_id')->nullable();
            $table->string('salesperson_name')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('total_debit', 15, 2)->nullable();
            $table->decimal('total_credit', 15, 2)->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('extracted_pages')->nullable();
            $table->string('page_range')->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            $table->string('whatsapp_status')->nullable();
            $table->text('whatsapp_error')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->foreign('salesperson_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ledger_import_id')->references('id')->on('ledger_imports')->nullOnDelete();
            $table->index(['customer_code', 'processing_status']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['period_from', 'period_to']);
            $table->index('source_file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledgers');
    }
};
