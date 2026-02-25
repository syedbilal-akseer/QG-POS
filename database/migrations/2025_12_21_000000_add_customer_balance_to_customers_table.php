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
        Schema::table('customers', function (Blueprint $table) {
            // Add customer_balance column from Oracle QG_POS_CUSTOMER_MASTER view
            $table->decimal('customer_balance', 15, 2)->nullable()->after('overall_credit_limit')
                  ->comment('Customer balance from Oracle CUSTOMER_BALANCE column - synced every 2 hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('customer_balance');
        });
    }
};
