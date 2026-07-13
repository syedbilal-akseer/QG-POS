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
        Schema::table('receipt_cheques', function (Blueprint $table) {
            // Add instrument/oracle bank fields
            $table->string('instrument_id')->nullable()->after('bank_name');
            $table->string('instrument_name')->nullable()->after('instrument_id');
            $table->string('instrument_account_name')->nullable()->after('instrument_name');
            $table->string('instrument_account_num')->nullable()->after('instrument_account_name');
            $table->string('org_id')->nullable()->after('instrument_account_num');
            
            // Add indexes for better performance
            $table->index('instrument_id');
            $table->index('org_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_cheques', function (Blueprint $table) {
            $table->dropIndex(['instrument_id']);
            $table->dropIndex(['org_id']);
            $table->dropColumn([
                'instrument_id',
                'instrument_name', 
                'instrument_account_name',
                'instrument_account_num',
                'org_id'
            ]);
        });
    }
};