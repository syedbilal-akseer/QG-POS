<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of every QR/barcode scan for the "capture learnings" step on the
 * Phase 1 plan. Records who scanned what, where (device), and whether it
 * resolved successfully — so SC + IT can review failure patterns.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('barcode_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_input', 1024);              // exactly what the scanner sent
            $table->string('matched_item_code', 64)->nullable();
            $table->unsignedTinyInteger('matched_level')->nullable();
            $table->boolean('success');
            $table->string('failure_reason', 255)->nullable();
            $table->string('device', 128)->nullable();      // optional: from request header
            $table->string('app_version', 32)->nullable();  // optional: from request header
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_scan_logs');
    }
};
