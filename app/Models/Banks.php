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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('bank_code')->unique();
            $table->string('bank_name');
            $table->string('bank_short_name')->nullable();
            $table->string('branch_code');
            $table->string('branch_name');
            $table->string('account_number');
            $table->string('account_title');
            $table->enum('account_type', ['current', 'savings', 'loan', 'credit', 'other'])->default('current');
            $table->string('currency', 3)->default('PKR');
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('address1');
            $table->string('address2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('country')->default('Pakistan');
            $table->string('postal_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('fax_number')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('bank_gl_account')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('ou_id')->nullable();
            $table->string('ou_name')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->dateTime('creation_date')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('bank_code');
            $table->index('account_number');
            $table->index('ou_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_masters');
    }
};