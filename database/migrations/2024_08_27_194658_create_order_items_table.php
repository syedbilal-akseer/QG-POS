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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('inventory_item_id');
            $table->integer('quantity')->unsigned()->nullable();
            $table->integer('ob_quantity')->unsigned()->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            // Add foreign key constraint for inventory_item_id
            $table->foreign('inventory_item_id')->references('inventory_item_id')->on('items')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
