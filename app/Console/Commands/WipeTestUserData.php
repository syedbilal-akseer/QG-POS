<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes the transactional data CREATED BY two designated test accounts
 * (app_dev@qgpos.com and qa@qgpos.com), but leaves the user rows themselves
 * intact. Runs daily at 3 AM via the scheduler in routes/console.php.
 *
 * Only records whose authoring columns (user_id / created_by / cancelled_by
 * / pushed_by) match one of these accounts are removed. Records owned by
 * other users that merely reference these accounts (e.g. customers.salesperson
 * pointing at qa@qgpos.com) are left alone — that's the user's explicit
 * preference and avoids cascading damage if the accounts ever go live.
 */
class WipeTestUserData extends Command
{
    protected $signature   = 'cleanup:test-user-data {--dry-run : show counts without deleting}';
    protected $description = 'Hard-delete transactional data created by app_dev@qgpos.com and qa@qgpos.com (user rows are preserved).';

    /**
     * Emails treated as test accounts. Add new emails here to extend coverage.
     */
    private const TEST_EMAILS = [
        'app_dev@qgpos.com',
        'qa@qgpos.com',
    ];

    public function handle(): int
    {
        $userIds = User::whereIn('email', self::TEST_EMAILS)->pluck('id')->all();

        if (empty($userIds)) {
            $this->warn('No test accounts found (' . implode(', ', self::TEST_EMAILS) . '). Nothing to do.');
            return self::SUCCESS;
        }

        $this->info('Test user ids: ' . implode(', ', $userIds));
        $dryRun = (bool) $this->option('dry-run');

        // table => list of columns to match against $userIds (OR-joined).
        // Order matters: child tables first so FKs don't block parent deletes.
        $tables = [
            // order_items must die before orders (FK)
            'order_items'           => ['__via_orders__'], // special — handled below
            'orders'                => ['user_id', 'cancelled_by', 'pushed_by'],

            'customer_receipts'     => ['created_by', 'cancelled_by', 'oracle_entered_by'],

            'customer_visits'       => ['user_id'],
            'customer_forms'        => ['user_id', 'created_by'],
            'customer_form_events'  => ['created_by'],

            'attendances'           => ['user_id'],
            'leaves'                => ['user_id'],
            'expenses'              => ['user_id', 'created_by'],

            'monthly_tour_plans'    => ['user_id', 'created_by'],
            'day_tour_plans'        => ['user_id', 'created_by'],
            'tour_plan_visits'      => ['user_id'],

            'activity_logs'         => ['user_id', 'causer_id'],
            'barcode_scan_logs'     => ['user_id'],
            'user_location_history' => ['user_id'],

            'returned_cheques'      => ['created_by', 'returned_by'],
            'receipt_cheques'       => ['created_by'],
        ];

        $totalDeleted = 0;

        DB::beginTransaction();
        try {
            foreach ($tables as $table => $cols) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                // Special case: order_items doesn't have user_id directly. We
                // delete the lines that belong to orders owned by test users.
                if ($table === 'order_items') {
                    if (!Schema::hasTable('orders')) continue;
                    $orderIds = DB::table('orders')
                        ->where(function ($q) use ($userIds) {
                            $q->whereIn('user_id', $userIds)
                              ->orWhereIn('cancelled_by', $userIds)
                              ->orWhereIn('pushed_by', $userIds);
                        })
                        ->pluck('id');

                    $deleted = $orderIds->isNotEmpty()
                        ? DB::table('order_items')->whereIn('order_id', $orderIds)->{$dryRun ? 'count' : 'delete'}()
                        : 0;
                    $this->line(sprintf('  %-24s %d', $table, $deleted));
                    $totalDeleted += $deleted;
                    continue;
                }

                // Build OR-joined where on whichever columns the table actually has.
                $validCols = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
                if (empty($validCols)) {
                    continue;
                }

                $query = DB::table($table)->where(function ($q) use ($validCols, $userIds) {
                    foreach ($validCols as $col) {
                        $q->orWhereIn($col, $userIds);
                    }
                });

                $deleted = $dryRun ? $query->count() : $query->delete();
                $this->line(sprintf('  %-24s %d', $table, $deleted));
                $totalDeleted += $deleted;
            }

            // Clear FCM tokens on the test users themselves so push to dead
            // sessions stops piling up but the account stays usable for login.
            if (!$dryRun) {
                User::whereIn('id', $userIds)->update([
                    'fcm_token'            => null,
                    'fcm_token_updated_at' => now(),
                ]);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("DRY RUN — rolled back. Would have removed {$totalDeleted} rows.");
            } else {
                DB::commit();
                $this->info("✓ Wiped {$totalDeleted} rows across " . count($tables) . " tables.");
                Log::info('cleanup:test-user-data completed', [
                    'user_ids'    => $userIds,
                    'rows_wiped'  => $totalDeleted,
                ]);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Cleanup failed: ' . $e->getMessage());
            Log::error('cleanup:test-user-data failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
