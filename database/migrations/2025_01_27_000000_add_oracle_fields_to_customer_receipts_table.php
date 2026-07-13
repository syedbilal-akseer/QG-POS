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
            // Oracle-specific fields required for Oracle EBS integration
            $table->string('ou_id')->nullable()->after('created_by')->comment('Operating Unit ID from Oracle');
            $table->decimal('receipt_amount', 15, 2)->nullable()->after('ou_id')->comment('Total receipt amount for Oracle');
            $table->date('receipt_date')->nullable()->after('receipt_amount')->comment('Receipt date for Oracle');
            $table->string('status')->nullable()->after('receipt_date')->comment('Oracle receipt status (must be NULL for new receipts)');
            $table->text('comments')->nullable()->after('status')->comment('User comments for Oracle receipt');
            $table->timestamp('creation_date')->nullable()->after('comments')->comment('System creation date for Oracle');
            $table->string('bank_account_id')->nullable()->after('creation_date')->comment('Bank Account ID from Oracle bank master');
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
                'bank_account_id'
            ]);
        });
    }
};