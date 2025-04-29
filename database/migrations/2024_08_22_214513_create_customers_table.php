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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->unique();
            $table->string('ou_id')->nullable();
            $table->string('ou_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('customer_site_id')->nullable();
            $table->string('salesperson')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->string('address1')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('overall_credit_limit')->nullable();
            $table->string('credit_days')->nullable();
            $table->string('nic')->nullable();
            $table->string('ntn')->nullable();
            $table->string('sales_tax_registration_num')->nullable();
            $table->string('category_code')->nullable();
            $table->timestamp('creation_date')->nullable();
            $table->string('price_list_id')->nullable();
            $table->string('price_list_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
