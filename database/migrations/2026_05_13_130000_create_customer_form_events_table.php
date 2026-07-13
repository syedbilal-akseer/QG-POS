<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-form events. Each event groups together a batch of customer forms
 * captured during the same campaign (e.g. "Karachi Trade Show 2026", "HBM Q3
 * Drive"). On the mobile app the salesperson picks an event first, then sees
 * / adds forms inside that event.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customer_form_events')) {
            // Table already exists — only ensure the event_id column is added below.
            $this->addEventIdColumn();
            return;
        }

        Schema::create('customer_form_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'start_date']);
            $table->index('name');
        });

        $this->addEventIdColumn();
    }

    private function addEventIdColumn(): void
    {
        if (!Schema::hasTable('customer_forms')) {
            return;
        }
        Schema::table('customer_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_forms', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('id')
                      ->constrained('customer_form_events')->nullOnDelete();
                $table->index('event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_forms', function (Blueprint $table) {
            if (Schema::hasColumn('customer_forms', 'event_id')) {
                $table->dropConstrainedForeignId('event_id');
            }
        });
        Schema::dropIfExists('customer_form_events');
    }
};
