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
        Schema::create('customer_forms', function (Blueprint $table) {
            $table->id();

            // Form type: 'HBM' (Pakistan Mega Leather Show visitor) or 'sales' (regular sales form)
            $table->enum('form_type', ['HBM', 'sales'])->default('sales')->index();

            // Visitor / Customer identification
            $table->string('name')->nullable();
            $table->string('designation')->nullable();
            $table->string('company_name')->nullable();

            // Contact info
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('skype')->nullable();

            // Customer type checkboxes (true/false flags)
            $table->boolean('is_shoe_material_dealer')->default(false);
            $table->boolean('is_shoe_manufacturer')->default(false);
            $table->boolean('is_merchandise')->default(false);
            $table->boolean('is_cottage')->default(false);
            $table->boolean('is_ladies')->default(false);
            $table->boolean('is_gents')->default(false);

            // Capacity range (one of the specified ranges)
            // Stores the selected range as a string, e.g. "1-200", "201-500", etc.
            $table->enum('capacity', [
                '1-200',
                '201-500',
                '501-1000',
                '1001-2000',
                '2000+'
            ])->nullable();

            // Inquiry & samples
            $table->text('inquiry')->nullable();
            $table->text('sample_given')->nullable();
            $table->text('sample_required')->nullable();

            // Submitted by (free text from the physical form; separate from app user)
            $table->string('submitted_by')->nullable();

            // Linked to app user (salesperson who submitted/created this record)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Optional link to customer in customers table
            $table->string('customer_code')->nullable()->index();
            $table->string('customer_name_linked')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_forms');
    }
};
