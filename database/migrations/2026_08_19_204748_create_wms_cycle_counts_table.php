<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wms_cycle_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('wms_locations');
            $table->foreignId('counted_by')->constrained('users');
            $table->enum('status', ['open', 'reviewed'])->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('wms_cycle_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('wms_cycle_counts')->cascadeOnDelete();
            $table->string('item_code');
            $table->decimal('system_qty', 18, 3)->default(0);
            $table->decimal('counted_qty', 18, 3)->default(0);
            $table->decimal('variance', 18, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_cycle_count_lines');
        Schema::dropIfExists('wms_cycle_counts');
    }
};
