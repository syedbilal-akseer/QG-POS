<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks order_items that were auto-added by a promotional scheme (Buy N get M
 * free). is_free=true rows carry zero price/subtotal and are skipped from
 * discount calculations.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) return;
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('discount');
            }
            if (!Schema::hasColumn('order_items', 'promotion_id')) {
                $table->unsignedBigInteger('promotion_id')->nullable()->after('is_free');
                $table->index('promotion_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_items')) return;
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'promotion_id')) {
                $table->dropIndex(['promotion_id']);
                $table->dropColumn('promotion_id');
            }
            if (Schema::hasColumn('order_items', 'is_free')) {
                $table->dropColumn('is_free');
            }
        });
    }
};
