<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('bill_number', 64)->index();
            $table->date('bill_date')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8)->default('PKR');
            $table->text('description')->nullable();

            // Workflow status — drives every routing decision (whose queue
            // the bill currently sits in, what actions are allowed, etc.):
            //   draft                  → uploader is still editing (used after a
            //                            rejection until they resubmit)
            //   pending_cmd_approval   → in CMD's queue
            //   pending_director_approval → CMD approved, in Director's queue
            //   approved               → both approvers signed off; terminal
            //   rejected               → either approver rejected; bill returns
            //                            to uploader who can edit and resubmit
            //                            (status flips back to pending_cmd_approval)
            $table->enum('status', [
                'draft',
                'pending_cmd_approval',
                'pending_director_approval',
                'approved',
                'rejected',
            ])->default('pending_cmd_approval')->index();

            // rejected_by_role: 'cmd' | 'director' — surfaces in the badge
            // when the bill is back in the uploader's queue.
            $table->string('rejected_by_role', 32)->nullable();

            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cmd_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cmd_approved_at')->nullable();
            $table->foreignId('director_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('director_approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'uploaded_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
    }
};
