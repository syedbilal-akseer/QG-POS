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
        Schema::table('day_tour_plans', function (Blueprint $table) { 
            $table->unsignedBigInteger('transferred_to')->nullable()->after('monthly_tour_plan_id');
            $table->enum('transfer_status', ['pending', 'accepted', 'rejected'])->default('pending')->after('transferred_to');
            $table->text('transfer_reason')->nullable()->after('transfer_status');
 
            $table->foreign('transferred_to')->references('salesperson_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('day_tour_plans', function (Blueprint $table) {
            $table->dropForeign(['transferred_to']);
            $table->dropColumn(['transferred_to', 'transfer_status', 'transfer_reason']);
        });
    }
};
