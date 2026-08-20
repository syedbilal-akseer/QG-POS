<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wms_gate_passes', function (Blueprint $table) {
            $table->id();
            $table->string('gate_pass_number')->unique();
            $table->string('qr_token', 64)->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->enum('type', ['dispatch', 'transfer'])->default('dispatch');
            $table->enum('status', ['pending', 'validated', 'cancelled'])->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->timestamp('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_gate_passes');
    }
};
