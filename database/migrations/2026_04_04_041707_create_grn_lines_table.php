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
        Schema::create('wms_grn_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('wms_grns')->onDelete('cascade');
            $table->string('item_code');
            $table->text('description')->nullable();
            $table->string('supplier_lot_number')->nullable();
            $table->string('system_sub_lot')->unique();
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('ordered_qty', 10, 2)->default(0);
            $table->decimal('received_qty', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_grn_lines');
    }
};
