<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly per-salesperson collection (receipt) targets from the SC TARGET.xlsx.
 *
 * The Excel sheet has columns:
 *   PRIMARY NAME    Salesman Name   RCP TGT   datekey      dateFormatted
 *   Anjum, Tahir    Tahir Anjum     0         20240101     1/1/2024
 *
 * Each row = one (salesperson, year, month) → receipt target. We resolve the
 * salesperson to users.id when possible but always keep primary_name in case
 * a match can't be made at import time (e.g. user hasn't synced from Oracle yet).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('salesperson_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('primary_name', 100);     // raw name from Excel (e.g. "Anjum, Tahir")
            $table->string('salesman_name', 100)->nullable(); // display name (e.g. "Tahir Anjum")
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');     // 1..12
            $table->decimal('receipt_target', 18, 6)->default(0);   // 6dp so MILLION_PKR values like 2.870978 survive
            $table->string('unit', 20)->default('MILLION_PKR');     // see SalespersonTarget::UNIT_FACTORS
            $table->date('period_start_date');                       // first of the month — handy for queries
            $table->timestamps();

            $table->unique(['primary_name', 'year', 'month'], 'sp_target_name_period_unique');
            $table->index(['user_id', 'year', 'month']);
            $table->index('period_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesperson_targets');
    }
};
