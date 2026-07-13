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
        Schema::table('banks', function (Blueprint $table) {
            $table->string('bank_code')->unique()->after('id');
            $table->string('bank_name')->after('bank_code');
            $table->string('bank_short_name')->nullable()->after('bank_name');
            $table->string('branch_code')->after('bank_short_name');
            $table->string('branch_name')->after('branch_code');
            $table->string('account_number')->after('branch_name');
            $table->string('account_title')->after('account_number');
            $table->enum('account_type', ['current', 'savings', 'loan', 'credit', 'other'])->default('current')->after('account_title');
            $table->string('currency', 3)->default('PKR')->after('account_type');
            $table->string('iban')->nullable()->after('currency');
            $table->string('swift_code')->nullable()->after('iban');
            $table->string('routing_number')->nullable()->after('swift_code');
            $table->string('address1')->after('routing_number');
            $table->string('address2')->nullable()->after('address1');
            $table->string('city')->after('address2');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->default('Pakistan')->after('state');
            $table->string('postal_code')->nullable()->after('country');
            $table->string('phone_number')->nullable()->after('postal_code');
            $table->string('fax_number')->nullable()->after('phone_number');
            $table->string('email')->nullable()->after('fax_number');
            $table->string('contact_person')->nullable()->after('email');
            $table->string('contact_person_phone')->nullable()->after('contact_person');
            $table->string('contact_person_email')->nullable()->after('contact_person_phone');
            $table->string('bank_gl_account')->nullable()->after('contact_person_email');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('bank_gl_account');
            $table->integer('ou_id')->nullable()->after('status');
            $table->string('ou_name')->nullable()->after('ou_id');
            $table->string('created_by')->nullable()->after('ou_name');
            $table->string('updated_by')->nullable()->after('created_by');
            $table->dateTime('creation_date')->nullable()->after('updated_by');
            
            // Indexes
            $table->index('bank_code');
            $table->index('account_number');
            $table->index('ou_id');
            $table->index('status');
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropIndex(['bank_code']);
            $table->dropIndex(['account_number']);
            $table->dropIndex(['ou_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['currency']);
            
            $table->dropColumn([
                'bank_code',
                'bank_name',
                'bank_short_name',
                'branch_code',
                'branch_name',
                'account_number',
                'account_title',
                'account_type',
                'currency',
                'iban',
                'swift_code',
                'routing_number',
                'address1',
                'address2',
                'city',
                'state',
                'country',
                'postal_code',
                'phone_number',
                'fax_number',
                'email',
                'contact_person',
                'contact_person_phone',
                'contact_person_email',
                'bank_gl_account',
                'status',
                'ou_id',
                'ou_name',
                'created_by',
                'updated_by',
                'creation_date'
            ]);
        });
    }
};