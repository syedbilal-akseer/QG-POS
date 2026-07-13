<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per platform. Mobile sends X-App-Platform + X-App-Version
        // headers on every authenticated /api/* call; the EnforceAppVersion
        // middleware looks up the matching platform row and rejects the call
        // with a 426 Upgrade Required when the client version sits below
        // `min_supported_version`. `latest_version` is purely informational
        // (admins use it to remember what was last published).
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 16)->unique(); // 'android' | 'ios'
            $table->string('latest_version', 32);
            $table->string('min_supported_version', 32);
            $table->string('store_url')->nullable(); // Play Store / App Store link surfaced in the error payload
            $table->text('force_update_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed sane defaults so the middleware doesn't lock everyone out on
        // first deploy. Versions start at 1.0.0 / min 1.0.0; admins bump the
        // min when they want to actually block older builds.
        DB::table('app_versions')->insert([
            [
                'platform' => 'android',
                'latest_version' => '1.0.0',
                'min_supported_version' => '1.0.0',
                'store_url' => null,
                'force_update_message' => 'Please install the latest version of the QG POS app from the Play Store to continue.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'platform' => 'ios',
                'latest_version' => '1.0.0',
                'min_supported_version' => '1.0.0',
                'store_url' => null,
                'force_update_message' => 'Please install the latest version of the QG POS app from the App Store to continue.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
