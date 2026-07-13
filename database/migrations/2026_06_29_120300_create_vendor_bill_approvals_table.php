<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chronological audit trail of every approval / rejection / resubmit
        // action a bill goes through. Driven by VendorBillController on each
        // approve / reject / update event so the show page can render a
        // complete timeline.
        Schema::create('vendor_bill_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained('vendor_bills')->cascadeOnDelete();
            // step labels mirror the workflow vocab:
            //   submitted / cmd / director / resubmitted
            $table->string('step', 32);
            // action: 'approved' | 'rejected' | 'submitted' | 'resubmitted'
            $table->string('action', 16);
            $table->text('remarks')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();

            $table->index(['vendor_bill_id', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_approvals');
    }
};
