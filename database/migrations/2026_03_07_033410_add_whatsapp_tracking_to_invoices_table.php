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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('whatsapp_sent_at')->nullable()->after('notes');
            $table->string('whatsapp_message_id')->nullable()->after('whatsapp_sent_at');
            $table->string('whatsapp_status')->nullable()->after('whatsapp_message_id'); // e.g., 'sent', 'failed'
            $table->text('whatsapp_error')->nullable()->after('whatsapp_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_sent_at', 'whatsapp_message_id', 'whatsapp_status', 'whatsapp_error']);
        });
    }
};
