<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->enum('oracle_status', ['pending', 'entered', 'failed'])->default('pending')->after('created_by');
            $table->string('oracle_receipt_number')->nullable()->after('oracle_status');
            $table->timestamp('oracle_entered_at')->nullable()->after('oracle_receipt_number');
            $table->bigInteger('oracle_entered_by')->nullable()->after('oracle_entered_at');
        });
    }

    public function down()
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropColumn(['oracle_status', 'oracle_receipt_number', 'oracle_entered_at', 'oracle_entered_by']);
        });
    }
};