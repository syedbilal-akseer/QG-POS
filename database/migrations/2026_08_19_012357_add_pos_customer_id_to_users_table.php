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
            // The walk-in customer account this user's POS terminal sells
            // against — one dedicated Oracle "Walk-in Customer" record per
            // shop/till, assigned once by admin. Never resolved by name
            // matching at runtime; see app/Livewire/Pos/PosTerminal.php.
            $table->unsignedBigInteger('pos_customer_id')->nullable()->after('additional_roles');
            $table->foreign('pos_customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['pos_customer_id']);
            $table->dropColumn('pos_customer_id');
        });
    }
};
