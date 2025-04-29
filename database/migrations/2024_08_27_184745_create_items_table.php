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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id')->unique(); // Set as unsigned big integer and unique
            $table->string('item_code')->nullable();
            $table->string('item_description')->nullable();
            $table->string('primary_uom_code')->nullable();
            $table->string('secondary_uom_code')->nullable();
            $table->string('major_category')->nullable();
            $table->string('minor_category')->nullable();
            $table->string('sub_minor_category')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
