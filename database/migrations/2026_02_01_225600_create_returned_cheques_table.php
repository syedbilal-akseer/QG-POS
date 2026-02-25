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
        Schema::create('returned_cheques', function (Blueprint $col) {
            $col->id();
            $col->unsignedBigInteger('customer_receipt_id');
            $col->unsignedBigInteger('receipt_cheque_id');
            $col->text('reason');
            $col->string('image_path')->nullable();
            $col->unsignedBigInteger('submitted_by_id');
            $col->timestamps();

            $col->foreign('customer_receipt_id')->references('id')->on('customer_receipts')->onDelete('cascade');
            $col->foreign('receipt_cheque_id')->references('id')->on('receipt_cheques')->onDelete('cascade');
            $col->foreign('submitted_by_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returned_cheques');
    }
};
