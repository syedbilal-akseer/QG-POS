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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('day_tour_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('monthly_visit_report_id')->constrained()->onDelete('cascade');
            $table->string('customer_name');
            $table->string('area')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('outlet_type')->nullable();
            $table->string('shop_category')->nullable();
            $table->text('visit_details')->nullable();
            $table->longText('competitors')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
