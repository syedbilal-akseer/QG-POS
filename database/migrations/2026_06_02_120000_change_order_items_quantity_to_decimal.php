<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KG-priced items (e.g. adhesives) need fractional quantities like 143.8.
 * The previous int(10) unsigned column silently truncated those to 143,
 * and the API validation rejected them outright with "must be an integer".
 *
 * Widen quantity + ob_quantity (the Oracle-pushed mirror) to decimal(18,3).
 * Three decimal places matches the precision the mobile UI emits.
 */
return new class extends Migration {
    public function up(): void
    {
        // doctrine/dbal isn't installed for ->change(); use raw ALTER so we
        // don't introduce a new dev dependency just for this column tweak.
        if (Schema::hasColumn('order_items', 'quantity')) {
            DB::statement('ALTER TABLE order_items MODIFY quantity DECIMAL(18,3) UNSIGNED NULL');
        }
        if (Schema::hasColumn('order_items', 'ob_quantity')) {
            DB::statement('ALTER TABLE order_items MODIFY ob_quantity DECIMAL(18,3) UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'quantity')) {
            DB::statement('ALTER TABLE order_items MODIFY quantity INT(10) UNSIGNED NULL');
        }
        if (Schema::hasColumn('order_items', 'ob_quantity')) {
            DB::statement('ALTER TABLE order_items MODIFY ob_quantity INT(10) UNSIGNED NULL');
        }
    }
};
