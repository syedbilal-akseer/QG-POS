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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('department_id')->constrained()->onDelete('set null');
            $table->foreignId('reporting_to')->nullable()->after('role_id')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) { 
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
            $table->dropForeign(['reporting_to']);
            $table->dropColumn('reporting_to');
        });
    }
};
