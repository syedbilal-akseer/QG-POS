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
        Schema::create('wms_lpns', function (Blueprint $table) {
            $table->id();
            $table->string('lpn_number')->unique();
            $table->unsignedBigInteger('grn_line_id')->nullable();
            $table->string('item_code');
            $table->string('lot_number')->nullable();
            $table->string('system_sub_lot')->nullable();
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->enum('uom', ['pallet', 'carton', 'unit'])->default('unit');
            $table->unsignedBigInteger('parent_lpn_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('status')->default('received');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_lpns');
    }
};
