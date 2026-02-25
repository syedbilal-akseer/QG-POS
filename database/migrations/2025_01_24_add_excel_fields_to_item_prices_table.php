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
        Schema::table('item_prices', function (Blueprint $table) {
            // Add new fields from Excel file structure
            $table->text('major_desc')->nullable()->after('item_description');
            $table->text('minor_desc')->nullable()->after('major_desc');
            $table->text('sub_minor_desc')->nullable()->after('minor_desc');
            $table->string('brand')->nullable()->after('sub_minor_desc');
            $table->timestamp('start_date_active')->nullable();
            $table->timestamp('end_date_active')->nullable();
            $table->timestamp('system_last_update_date')->nullable();
            $table->timestamp('system_creation_date')->nullable();
            
            // Add indexes for better performance
            $table->index('brand');
            $table->index('start_date_active');
            $table->index('end_date_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_prices', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropIndex(['start_date_active']);
            $table->dropIndex(['end_date_active']);
            $table->dropColumn([
                'major_desc',
                'minor_desc',
                'sub_minor_desc',
                'brand',
                'start_date_active',
                'end_date_active',
                'system_last_update_date',
                'system_creation_date'
            ]);
        });
    }
};