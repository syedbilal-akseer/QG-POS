<?php

use Illuminate\Database\Migrations\Migration;

// This migration is intentionally a no-op.
// Investigation showed banks.bank_account_id already has a non-unique index only —
// there was never a unique constraint to drop. The real fix is in
// 2026_04_13_020000_nullable_banks_columns.php (nullable bank_account_num / bank_name).
return new class extends Migration
{
    public function up(): void {}
    public function down(): void {}
};
