<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('salesperson_targets', 'sales_target')) {
            Schema::table('salesperson_targets', function (Blueprint $table) {
                $table->decimal('sales_target', 18, 6)->default(0)->after('receipt_target');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('salesperson_targets', 'sales_target')) {
            Schema::table('salesperson_targets', function (Blueprint $table) {
                $table->dropColumn('sales_target');
            });
        }
    }
};
