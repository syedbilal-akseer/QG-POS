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
        Schema::table('items', function (Blueprint $table) {
            // Add indexes for better performance
            $table->index('inventory_item_id', 'idx_items_inventory_item_id');
            $table->index('item_code', 'idx_items_item_code');
            $table->index(['item_description', 'item_code'], 'idx_items_search');
            $table->index('created_at', 'idx_items_created_at');
        });

        Schema::table('item_prices', function (Blueprint $table) {
            // Add indexes for better performance
            $table->index('item_id', 'idx_item_prices_item_id');
            $table->index(['item_id', 'price_list_id'], 'idx_item_prices_lookup');
            $table->index('start_date_active', 'idx_item_prices_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_items_inventory_item_id');
            $table->dropIndex('idx_items_item_code');
            $table->dropIndex('idx_items_search');
            $table->dropIndex('idx_items_created_at');
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $table->dropIndex('idx_item_prices_item_id');
            $table->dropIndex('idx_item_prices_lookup');
            $table->dropIndex('idx_item_prices_start_date');
        });
    }
};