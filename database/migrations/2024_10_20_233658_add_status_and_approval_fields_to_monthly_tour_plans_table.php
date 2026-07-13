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
        Schema::table('monthly_tour_plans', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('month');

            $table->boolean('line_manager_approval')->default(false)->after('status');
            $table->boolean('hod_approval')->default(false)->after('line_manager_approval');

            $table->text('rejection_reason')->nullable()->after('hod_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_tour_plans', function (Blueprint $table) {
            $table->dropColumn(['status', 'line_manager_approval', 'hod_approval', 'rejection_reason']);
        });
    }
};
