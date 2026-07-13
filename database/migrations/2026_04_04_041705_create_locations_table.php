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
        Schema::create('wms_locations', function (Blueprint $table) {
            $table->id();
            $table->string('location_code')->unique();
            $table->enum('type', ['zone', 'aisle', 'rack', 'bin', 'logical_bin']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_locations');
    }
};
