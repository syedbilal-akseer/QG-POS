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
            $table->unsignedBigInteger('supply_chain_user_id')->nullable()->after('reporting_to');
            $table->unsignedBigInteger('account_user_id')->nullable()->after('supply_chain_user_id');
            
            $table->foreign('supply_chain_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('account_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supply_chain_user_id']);
            $table->dropForeign(['account_user_id']);
            $table->dropColumn(['supply_chain_user_id', 'account_user_id']);
        });
    }
};