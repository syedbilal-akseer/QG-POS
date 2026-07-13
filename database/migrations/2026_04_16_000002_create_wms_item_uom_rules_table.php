<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wms_item_uom_rules', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 100)->index();
            $table->string('uom_level', 20)->comment('pallet, carton, box, unit');
            $table->decimal('qty_per_parent', 10, 4)->default(1)
                  ->comment('How many of this UOM fit in one parent UOM. e.g. carton has 20 boxes');
            $table->string('barcode_prefix', 20)->nullable()
                  ->comment('GS1 Application Identifier prefix, e.g. 01 for GTIN');
            $table->string('label_size', 20)->nullable()->default('100x50')
                  ->comment('Label dimensions in mm: width x height');
            $table->timestamps();

            $table->unique(['item_code', 'uom_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_item_uom_rules');
    }
};
