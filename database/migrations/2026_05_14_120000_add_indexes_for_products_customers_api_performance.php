<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that close the slowest scans in the products & customers APIs.
 *
 * Each `if (!$this->hasIndex(...))` guard makes the migration safe on
 * databases where the index already exists (some envs have indexes that
 * were applied directly via SQL, outside the migration system).
 */
return new class extends Migration
{
    public function up(): void
    {
        // item_prices(item_code, discounted_price) — speeds up the
        // "global discounts" lookup:
        //   ItemPrice::whereIn('item_code', $codes)->whereNotNull('discounted_price')
        // The composite walks item_code and skips NULL discounts inline.
        Schema::table('item_prices', function (Blueprint $table) {
            if (Schema::hasColumn('item_prices', 'discounted_price')
                && !$this->hasIndex('item_prices', 'idx_item_prices_item_code_discount')) {
                $table->index(['item_code', 'discounted_price'], 'idx_item_prices_item_code_discount');
            }
            // item_code on its own — for the hasMany(ItemPrice, 'item_code') join
            // and the categories whereHas('itemPrices') scan.
            if (!$this->hasIndex('item_prices', 'idx_item_prices_item_code')
                && !$this->hasIndex('item_prices', 'idx_prices_item_code')) {
                $table->index('item_code', 'idx_item_prices_item_code');
            }
            // price_list_name LIKE 'Karachi%' / 'Lahore%' filters.
            if (!$this->hasIndex('item_prices', 'idx_item_prices_price_list_name')
                && !$this->hasIndex('item_prices', 'idx_prices_price_list_name')) {
                $table->index('price_list_name', 'idx_item_prices_price_list_name');
            }
        });

        // items.minor_category / sub_minor_category — whereIn() filters used by
        // both the list and the categories endpoint.
        Schema::table('items', function (Blueprint $table) {
            if (!$this->hasIndex('items', 'idx_items_minor_category')) {
                $table->index('minor_category', 'idx_items_minor_category');
            }
            if (!$this->hasIndex('items', 'idx_items_sub_minor_category')) {
                $table->index('sub_minor_category', 'idx_items_sub_minor_category');
            }
        });

        // customers.salesperson — every role=user request filters by this.
        // customers.ou_id — supply-chain branch filters by this.
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->hasIndex('customers', 'idx_customers_salesperson')) {
                $table->index('salesperson', 'idx_customers_salesperson');
            }
            if (Schema::hasColumn('customers', 'ou_id')
                && !$this->hasIndex('customers', 'idx_customers_ou_id')) {
                $table->index('ou_id', 'idx_customers_ou_id');
            }
        });

        // orders(user_id, created_at) — UserActivityRanker groups orders for
        // the auth user within a 180-day window. The FK on user_id alone
        // doesn't help with the created_at range scan + ORDER BY.
        Schema::table('orders', function (Blueprint $table) {
            if (!$this->hasIndex('orders', 'idx_orders_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_orders_user_created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_prices', function (Blueprint $table) {
            if ($this->hasIndex('item_prices', 'idx_item_prices_item_code_discount')) {
                $table->dropIndex('idx_item_prices_item_code_discount');
            }
            if ($this->hasIndex('item_prices', 'idx_item_prices_item_code')) {
                $table->dropIndex('idx_item_prices_item_code');
            }
            if ($this->hasIndex('item_prices', 'idx_item_prices_price_list_name')) {
                $table->dropIndex('idx_item_prices_price_list_name');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if ($this->hasIndex('items', 'idx_items_minor_category')) {
                $table->dropIndex('idx_items_minor_category');
            }
            if ($this->hasIndex('items', 'idx_items_sub_minor_category')) {
                $table->dropIndex('idx_items_sub_minor_category');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if ($this->hasIndex('customers', 'idx_customers_salesperson')) {
                $table->dropIndex('idx_customers_salesperson');
            }
            if ($this->hasIndex('customers', 'idx_customers_ou_id')) {
                $table->dropIndex('idx_customers_ou_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if ($this->hasIndex('orders', 'idx_orders_user_created')) {
                $table->dropIndex('idx_orders_user_created');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database   = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index]
        );
        return !empty($result);
    }
};
