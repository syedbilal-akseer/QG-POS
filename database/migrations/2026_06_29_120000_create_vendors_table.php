<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            // oracle_vendor_id will hold the Oracle vendor PK once we have the
            // sync command — nullable now so dummy seed rows are accepted.
            // When the Oracle view shape lands we'll mirror its full column
            // set (vendor_number, payment_terms, default_currency, etc.).
            $table->unsignedBigInteger('oracle_vendor_id')->nullable()->unique();
            $table->string('vendor_code', 64)->index();
            $table->string('vendor_name');
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 32)->nullable();
            $table->string('email_address')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
