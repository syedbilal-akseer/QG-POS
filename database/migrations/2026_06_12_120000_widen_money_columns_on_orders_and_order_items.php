<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real orders for bulk SKUs (rubber/adhesive sold in TN) routinely exceed
 * the decimal(10,2) cap of 99,999,999.99 PKR. A single line of price 20,815 ×
 * qty 15,086 = 314,065,490 PKR which the column refuses to accept:
 *
 *   SQLSTATE[22003]: Numeric value out of range: 1264 Out of range value for
 *   column 'sub_total' at row 1
 *
 * Widen every money column on orders + order_items to decimal(18,2), which
 * holds up to 9,999,999,999,999,999.99 PKR. Idempotent: each ALTER guards
 * on a fresh introspection of the column type so re-running the migration
 * is safe.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->retypeColumn('order_items', 'price',     'DECIMAL(18,2) NULL');
        $this->retypeColumn('order_items', 'cap_price', 'DECIMAL(18,2) NULL');
        $this->retypeColumn('order_items', 'sub_total', 'DECIMAL(18,2) NULL');
        $this->retypeColumn('order_items', 'discount',  'DECIMAL(18,2) NOT NULL DEFAULT 0.00');

        $this->retypeColumn('orders', 'sub_total',    'DECIMAL(18,2) NOT NULL DEFAULT 0.00');
        $this->retypeColumn('orders', 'discount',     'DECIMAL(18,2) NULL');
        $this->retypeColumn('orders', 'total_amount', 'DECIMAL(18,2) NOT NULL DEFAULT 0.00');
    }

    public function down(): void
    {
        // No-op: shrinking back to decimal(10,2) would truncate live data
        // and re-introduce the very overflow this migration fixes.
    }

    /**
     * Issue an ALTER TABLE ... MODIFY for the given column. Bypasses
     * doctrine/dbal (not installed) by using raw SQL.
     */
    private function retypeColumn(string $table, string $column, string $newType): void
    {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$newType}");
    }
};
