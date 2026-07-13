<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'builty_path')) {
                $table->string('builty_path')->nullable()->after('pdf_path');
            }
            if (!Schema::hasColumn('invoices', 'builty_uploaded_at')) {
                $table->timestamp('builty_uploaded_at')->nullable()->after('builty_path');
            }
            if (!Schema::hasColumn('invoices', 'builty_uploaded_by')) {
                $table->unsignedBigInteger('builty_uploaded_by')->nullable()->after('builty_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['builty_path', 'builty_uploaded_at', 'builty_uploaded_by'] as $col) {
                if (Schema::hasColumn('invoices', $col)) $table->dropColumn($col);
            }
        });
    }
};
