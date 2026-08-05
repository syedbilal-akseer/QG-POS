<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendors AP is being consolidated into a single flow: Admin submits →
     * Director approves → CMD approves (24h SLA, informational) → Admin
     * manually closes the bill out. This extends the existing vendor_bills
     * table in place rather than standing up a parallel table.
     */
    public function up(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->timestamp('cmd_deadline_at')->nullable()->after('cmd_approved_at');
            $table->foreignId('closed_by')->nullable()->after('cmd_deadline_at')->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });

        DB::statement("ALTER TABLE vendor_bills MODIFY status ENUM(
            'draft',
            'pending_cmd_approval',
            'pending_director_approval',
            'approved',
            'closed',
            'rejected'
        ) NOT NULL DEFAULT 'pending_director_approval'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendor_bills MODIFY status ENUM(
            'draft',
            'pending_cmd_approval',
            'pending_director_approval',
            'approved',
            'rejected'
        ) NOT NULL DEFAULT 'pending_cmd_approval'");

        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['cmd_deadline_at', 'closed_at']);
        });
    }
};
