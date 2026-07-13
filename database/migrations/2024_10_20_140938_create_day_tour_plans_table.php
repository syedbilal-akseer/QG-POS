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
        Schema::create('day_tour_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monthly_tour_plan_id');
            $table->date('date');
            $table->string('day')->nullable();
            $table->string('from_location');
            $table->string('to_location');
            $table->boolean('is_night_stay')->default(false);
            $table->longText('key_tasks')->nullable();
            $table->timestamps();

            $table->foreign('monthly_tour_plan_id')->references('id')->on('monthly_tour_plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_tour_plans');
    }
};
