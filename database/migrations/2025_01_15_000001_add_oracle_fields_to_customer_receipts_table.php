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
        Schema::table('customer_receipts', function (Blueprint $table) {
            // Add Oracle-specific fields
            $table->string('ou_id')->nullable()->after('receipt_method');
            $table->decimal('receipt_amount', 15, 2)->nullable()->after('ou_id');
            $table->date('receipt_date')->nullable()->after('receipt_amount');
            $table->string('status')->nullable()->after('receipt_date');
            $table->text('comments')->nullable()->after('status');
            $table->timestamp('creation_date')->nullable()->after('comments');
            $table->string('bank_account_id')->nullable()->after('creation_date');
            $table->string('oracle_status')->nullable()->after('bank_account_id');
            $table->string('oracle_receipt_number')->nullable()->after('oracle_status');
            $table->timestamp('oracle_entered_at')->nullable()->after('oracle_receipt_number');
            $table->unsignedBigInteger('oracle_entered_by')->nullable()->after('oracle_entered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'ou_id',
                'receipt_amount', 
                'receipt_date',
                'status',
                'comments',
                'creation_date',
                'bank_account_id',
                'oracle_status',
                'oracle_receipt_number',
                'oracle_entered_at',
                'oracle_entered_by'
            ]);
        });
    }
};