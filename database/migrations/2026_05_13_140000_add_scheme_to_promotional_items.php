<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds "Buy N, get M free" scheme fields to promotional_items.
 *
 *   buy_qty       — quantity the customer must buy (e.g. 10)
 *   free_qty      — quantity given free (e.g. 1)
 *   free_item_code — nullable; if NULL the free item is the SAME item as purchased.
 *                   if set, it's a different item (e.g. buy 10 glue → get 1 cleaner free)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('promotional_items', function (Blueprint $table) {
            if (!Schema::hasColumn('promotional_items', 'buy_qty')) {
                $table->unsignedInteger('buy_qty')->nullable()->after('discounted_price');
            }
            if (!Schema::hasColumn('promotional_items', 'free_qty')) {
                $table->unsignedInteger('free_qty')->nullable()->after('buy_qty');
            }
            if (!Schema::hasColumn('promotional_items', 'free_item_code')) {
                $table->string('free_item_code')->nullable()->after('free_qty')->index();
            }
            if (!Schema::hasColumn('promotional_items', 'scheme_start_date')) {
                $table->date('scheme_start_date')->nullable()->after('free_item_code');
            }
            if (!Schema::hasColumn('promotional_items', 'scheme_end_date')) {
                $table->date('scheme_end_date')->nullable()->after('scheme_start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotional_items', function (Blueprint $table) {
            $table->dropColumn(['buy_qty', 'free_qty', 'free_item_code', 'scheme_start_date', 'scheme_end_date']);
        });
    }
};
