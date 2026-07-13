<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'source_file_hash')) {
            Schema::table('invoices', function (Blueprint $table) {
                // SHA-256 of the uploaded PDF. Indexed so the duplicate-check
                // lookup in InvoiceController::store() stays O(log n).
                $table->string('source_file_hash', 64)->nullable()->after('original_filename');
                $table->index('source_file_hash');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'source_file_hash')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex(['source_file_hash']);
                $table->dropColumn('source_file_hash');
            });
        }
    }
};
