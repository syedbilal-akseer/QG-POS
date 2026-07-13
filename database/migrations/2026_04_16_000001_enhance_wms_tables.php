<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add ou_id + status tracking to wms_grns
        Schema::table('wms_grns', function (Blueprint $table) {
            $table->string('ou_id', 20)->nullable()->after('status')->index();
            $table->string('warehouse_name', 100)->nullable()->after('ou_id');
        });

        // Add cost_price + pallet_size to wms_grn_lines
        Schema::table('wms_grn_lines', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 4)->nullable()->after('received_qty')
                  ->comment('Landed cost per unit at time of receipt');
            $table->integer('pallet_size')->nullable()->after('cost_price')
                  ->comment('Units per pallet/carton — used for LPN auto-generation');
            $table->boolean('lpns_generated')->default(false)->after('pallet_size');
        });

        // Add ou_id + new statuses to wms_lpns
        Schema::table('wms_lpns', function (Blueprint $table) {
            $table->string('ou_id', 20)->nullable()->after('status')->index();
            // Change status to support: received, stored, picked, broken
            // Using string column so we don't need to alter the enum
            $table->string('status', 20)->default('received')->change();
        });
    }

    public function down(): void
    {
        Schema::table('wms_grns', function (Blueprint $table) {
            $table->dropColumn(['ou_id', 'warehouse_name']);
        });
        Schema::table('wms_grn_lines', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'pallet_size', 'lpns_generated']);
        });
        Schema::table('wms_lpns', function (Blueprint $table) {
            $table->dropColumn(['ou_id']);
        });
    }
};
