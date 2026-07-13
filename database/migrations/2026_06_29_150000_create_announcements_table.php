<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('body');

            // Audience selector:
            //   target_type:
            //     'all'  → every user with a registered FCM token
            //     'role' → users whose primary role matches target_value
            $table->string('target_type', 16)->default('all');
            $table->string('target_value', 64)->nullable();

            // Tracking — populated when the controller fires the send.
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
