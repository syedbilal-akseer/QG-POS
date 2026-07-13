<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure parent key is indexed for foreign key link
        try {
            Schema::table('items', function (Blueprint $table) {
                $table->index('item_code');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        Schema::create('promotional_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');
            $table->string('image_path')->nullable();
            $table->decimal('original_price', 15, 2)->nullable();
            $table->decimal('discounted_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('item_code')->references('item_code')->on('items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotional_items');
    }
};