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
        Schema::table('banks', function (Blueprint $table) {
            // Oracle columns that actually exist
            $table->bigInteger('org_id')->nullable()->after('id');
            $table->string('receipt_classes')->nullable()->after('org_id');
            $table->string('receipt_method')->nullable()->after('receipt_classes');
            $table->bigInteger('receipt_method_id')->nullable()->after('receipt_method');
            $table->bigInteger('receipt_class_id')->nullable()->after('receipt_method_id');
            $table->string('bank_account_name')->nullable()->after('receipt_class_id');
            $table->string('bank_account_num')->after('bank_account_name'); // This will be our account_number
            $table->bigInteger('bank_account_id')->unique()->after('bank_account_num'); // Primary identifier
            $table->string('iban_number')->nullable()->after('bank_account_id');
            $table->string('bank_name')->after('iban_number');
            $table->string('bank_branch_name')->nullable()->after('bank_name');
            
            // Additional columns for compatibility (with defaults since they don't exist in Oracle)
            $table->enum('account_type', ['current', 'savings', 'loan', 'credit', 'other'])->default('current')->after('bank_branch_name');
            $table->string('currency', 3)->default('PKR')->after('account_type');
            $table->string('country')->default('Pakistan')->after('currency');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('country');
            
            // Sync metadata
            $table->string('created_by')->nullable()->after('status');
            $table->string('updated_by')->nullable()->after('created_by');
            $table->timestamp('synced_at')->nullable()->after('updated_by');
            
            // Indexes for performance
            $table->index('bank_account_id');
            $table->index('bank_account_num');
            $table->index('org_id');
            $table->index('receipt_method_id');
            $table->index('status');
            $table->index('bank_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['bank_account_id']);
            $table->dropIndex(['bank_account_num']);
            $table->dropIndex(['org_id']);
            $table->dropIndex(['receipt_method_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['bank_name']);
            
            // Drop columns
            $table->dropColumn([
                'org_id',
                'receipt_classes',
                'receipt_method',
                'receipt_method_id',
                'receipt_class_id',
                'bank_account_name',
                'bank_account_num',
                'bank_account_id',
                'iban_number',
                'bank_name',
                'bank_branch_name',
                'account_type',
                'currency',
                'country',
                'status',
                'created_by',
                'updated_by',
                'synced_at'
            ]);
        });
    }
};