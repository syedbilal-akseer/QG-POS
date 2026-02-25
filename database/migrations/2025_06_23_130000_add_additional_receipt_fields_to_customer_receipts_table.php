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
            // Receipt number (unique by year)
            $table->year('receipt_year')->after('receipt_number');
            
            // Currency fields
            $table->enum('currency', ['PKR', 'USD'])->default('PKR')->after('cash_amount');
            
            // Maturity dates for both cash and cheque
            $table->date('cash_maturity_date')->nullable()->after('currency');
            // maturity_date already exists for cheque
            
            // Bank information
            $table->unsignedBigInteger('remittance_bank_id')->nullable()->after('is_third_party_cheque');
            $table->string('remittance_bank_name')->nullable()->after('remittance_bank_id');
            $table->unsignedBigInteger('customer_bank_id')->nullable()->after('remittance_bank_name');
            $table->string('customer_bank_name')->nullable()->after('customer_bank_id');
            
            // Add indexes for better performance
            $table->index('receipt_number');
            $table->index('receipt_year');
            $table->index('currency');
            $table->index('remittance_bank_id');
            $table->index('customer_bank_id');
            $table->index('cash_maturity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropIndex(['receipt_number']);
            $table->dropIndex(['receipt_year']);
            $table->dropIndex(['currency']);
            $table->dropIndex(['remittance_bank_id']);
            $table->dropIndex(['customer_bank_id']);
            $table->dropIndex(['cash_maturity_date']);
            
            $table->dropColumn([
                'receipt_number',
                'receipt_year',
                'currency',
                'cash_maturity_date',
                'remittance_bank_id',
                'remittance_bank_name',
                'customer_bank_id',
                'customer_bank_name'
            ]);
        });
    }
};