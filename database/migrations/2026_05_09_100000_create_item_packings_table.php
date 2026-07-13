<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-level packing definitions for items (Phase 1: item-level barcoding).
 *
 * One row per item × per packing level. Levels:
 *   1 = PRIMARY_UOM      (base measurement, e.g. KG)
 *   2 = SECONDARY_UOM    (individual unit, e.g. TB / BTL)
 *   3 = PRIMARY_PACKING  (small pack, e.g. BOX)
 *   4 = SECONDARY_PACKING (large pack, e.g. CARTON)
 *
 * conversion_to_base is "how many primary-UOM units this level contains".
 * It's nullable because SC will fill in real conversion factors later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_packings', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 64)->index();
            $table->unsignedTinyInteger('level'); // 1..4
            $table->string('level_code', 32);      // e.g. PRIMARY_UOM, SECONDARY_PACKING
            $table->string('uom_code', 32);        // e.g. KG, CARTON (normalized uppercase)
            $table->string('uom_label', 64)->nullable(); // original casing for display
            $table->decimal('conversion_to_base', 18, 6)->nullable();
            $table->string('barcode_payload', 255); // the full JSON payload printed on the QR
            $table->string('supplier_barcode', 128)->nullable(); // pre-printed supplier code if any
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['item_code', 'level']);
            $table->index('barcode_payload');
            $table->index('supplier_barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_packings');
    }
};
