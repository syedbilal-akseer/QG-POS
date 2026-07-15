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
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code');

            $table->foreignId('ledger_import_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('transaction_date')->nullable();

            $table->string('document_type')->nullable();

            $table->string('document_no')->nullable();

            $table->longText('description')->nullable();

            $table->decimal('debit', 15, 2)->default(0);

            $table->decimal('credit', 15, 2)->default(0);

            $table->decimal('balance', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledgers');
    }
};
