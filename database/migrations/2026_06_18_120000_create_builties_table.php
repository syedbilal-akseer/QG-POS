<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builties', function (Blueprint $table) {
            $table->id();
            $table->string('builty_number', 64)->index();

            // Order is required; invoice is optional (set when the order has
            // been invoiced). When invoice_id is present the upload flow also
            // merges this builty into invoices.pdf_path (same logic as the
            // existing /api/invoices/upload-builty endpoint).
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // After conversion every file_path points to a PDF, even when the
            // user uploaded a PNG/JPG. original_filename + original_ext keep
            // the source filename for display.
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('original_ext', 8)->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builties');
    }
};
