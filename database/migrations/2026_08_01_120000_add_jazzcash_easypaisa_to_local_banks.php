<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $wallets = ['JazzCash', 'EasyPaisa'];

        foreach ($wallets as $name) {
            $exists = DB::table('local_banks')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('local_banks')->insert([
                    'name' => $name,
                    'is_islamic' => false,
                    'is_microfinance' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('local_banks')->whereIn('name', ['JazzCash', 'EasyPaisa'])->delete();
    }
};
