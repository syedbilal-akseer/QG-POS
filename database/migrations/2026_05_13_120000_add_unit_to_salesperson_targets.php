<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The TARGET.xlsx values are typically in millions of PKR (e.g. 2.87 means
 * 2,870,978 PKR). Store the unit alongside each row so the dashboard API
 * can normalise everything to actual PKR when returning numbers — and so
 * future uploads can choose a different unit without re-importing history.
 *
 * Allowed values:
 *   'PKR'         → store as-is (whole rupees)
 *   'THOUSAND_PKR' → multiply by 1,000
 *   'MILLION_PKR' → multiply by 1,000,000 (default for TARGET.xlsx)
 */
return new class extends Migration {
    public function up(): void
    {
        // Guard: the create-table migration was updated to include `unit`
        // from the start, so this becomes a no-op on fresh DBs. We keep
        // the migration so existing environments that already created
        // the table without the column still get it added.
        if (!Schema::hasTable('salesperson_targets')) {
            return; // table will be created with `unit` already present
        }
        if (Schema::hasColumn('salesperson_targets', 'unit')) {
            return;
        }
        Schema::table('salesperson_targets', function (Blueprint $table) {
            $table->string('unit', 20)->default('MILLION_PKR')->after('receipt_target');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('salesperson_targets') && Schema::hasColumn('salesperson_targets', 'unit')) {
            Schema::table('salesperson_targets', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }
};
