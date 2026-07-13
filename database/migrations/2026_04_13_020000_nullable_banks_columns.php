<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('bank_account_num')->nullable()->change();
            $table->string('bank_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('bank_account_num')->nullable(false)->change();
            $table->string('bank_name')->nullable(false)->change();
        });
    }
};
