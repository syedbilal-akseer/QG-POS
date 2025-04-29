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
        Schema::create('monthly_tour_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salesperson_id');
            $table->string('month')->nullable();
            $table->timestamps();

            $table->foreign('salesperson_id')->references('salesperson_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_tour_plans');
    }
};
