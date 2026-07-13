<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builties', function (Blueprint $table) {
            // customer_code lets a builty be linked to a customer at upload
            // time without requiring an invoice — when a new invoice for that
            // customer is later uploaded and extracted, the builty auto-merges
            // into it (see InvoiceController::separateCustomerInvoices ->
            // BuiltyController::autoAttachToInvoice).
            $table->string('customer_code', 64)->nullable()->index()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('builties', function (Blueprint $table) {
            $table->dropColumn('customer_code');
        });
    }
};
