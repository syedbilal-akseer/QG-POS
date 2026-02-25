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
            $table->decimal('cheque_amount', 15, 2)->nullable()->after('cheque_no');
            $table->date('maturity_date')->nullable()->after('cheque_amount');
            $table->text('cheque_comments')->nullable()->after('maturity_date');
            $table->boolean('is_third_party_cheque')->default(false)->after('cheque_comments');
            
            // Add index for maturity_date for better performance
            $table->index('maturity_date');
            $table->index('is_third_party_cheque');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropIndex(['maturity_date']);
            $table->dropIndex(['is_third_party_cheque']);
            $table->dropColumn(['cheque_amount', 'maturity_date', 'cheque_comments', 'is_third_party_cheque']);
        });
    }
};