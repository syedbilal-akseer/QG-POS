<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // order_id becomes optional: the new supply-chain "quick upload" path
        // (Builty just carries the file + status=sent_to_accounts) doesn't
        // know the order yet — accounts fills it in when they markSubmitted().
        Schema::table('builties', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
        });

        Schema::table('builties', function (Blueprint $table) {
            // sent_to_accounts: uploaded (by supply-chain or accounts) but not
            // yet reviewed/completed by accounts.
            // submitted: accounts has filled in order/customer/invoice and
            // confirmed — the "official" record accounts intended to submit.
            $table->string('status', 32)->default('sent_to_accounts')->after('uploaded_by');
            $table->foreignId('submitted_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->index('status');
        });

        // Backfill: every pre-existing row was created through the
        // accounts-only upload flow with order_id already filled in — treat
        // those as already submitted so they don't suddenly appear in the
        // new "pending review" queue.
        DB::table('builties')
            ->whereNotNull('order_id')
            ->update([
                'status'       => 'submitted',
                'submitted_by' => DB::raw('uploaded_by'),
                'submitted_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('builties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['status', 'submitted_at']);
        });

        Schema::table('builties', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable(false)->change();
        });
    }
};
