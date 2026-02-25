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
        // Add index for price_list_id on customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->index('price_list_id', 'idx_customers_price_list_id');
        });

        // Add index for price_list_id on item_prices table (as first column for better performance)
        Schema::table('item_prices', function (Blueprint $table) {
            $table->index('price_list_id', 'idx_item_prices_price_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_price_list_id');
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $table->dropIndex('idx_item_prices_price_list_id');
        });
    }
};