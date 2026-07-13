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
        if (Schema::hasTable('banks')) {
            Schema::table('banks', function (Blueprint $table) {
                if (!Schema::hasColumn('banks', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('banks')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }
    }
};
