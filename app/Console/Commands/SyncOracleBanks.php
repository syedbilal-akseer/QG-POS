<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\OracleBankMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOracleBanks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-banks {--force : Force sync even if Oracle table doesn\'t exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync banks from Oracle database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Starting Oracle banks synchronization...');

            // Check if Oracle connection is working
            if (!$this->testOracleConnection()) {
                $this->error('Oracle connection failed. Please check your database configuration.');
                return Command::FAILURE;
            }

            // Check if Oracle bank table exists
            if (!$this->checkOracleBankTableExists()) {
                if (!$this->option('force')) {
                    $this->error('Oracle bank master table does not exist. Use --force to skip this check.');
                    return Command::FAILURE;
                }
                $this->warn('Oracle bank master table does not exist, but continuing due to --force option.');
                return Command::SUCCESS;
            }

            DB::transaction(function () {
                $this->syncBanks();
            });

            $this->info('Banks synced successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('Oracle banks sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Test Oracle database connection
     */
    private function testOracleConnection(): bool
    {
        try {
            DB::connection('oracle')->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error('Oracle connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if Oracle bank master table exists
     */
    private function checkOracleBankTableExists(): bool
    {
        try {
            $exists = DB::connection('oracle')->select("
                SELECT COUNT(*) as count 
                FROM all_objects 
                WHERE owner = 'APPS' 
                AND object_name = 'QG_BANK_MASTER' 
                AND object_type IN ('TABLE', 'VIEW')
            ");

            return $exists[0]->count > 0;
        } catch (\Exception $e) {
            Log::warning('Failed to check Oracle bank table existence: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform the actual bank synchronization
     */
private function syncBanks(): void
{
    // Fetch all banks from Oracle
    $oracleBanks = OracleBankMaster::active()->get();
    
    $syncedCount = 0;
    $errorCount = 0;

    $this->info("Found {$oracleBanks->count()} banks in Oracle to sync.");

    if ($oracleBanks->isEmpty()) {
        $this->warn('No banks found in Oracle database.');
        return;
    }

    // Progress bar for better UX
    $progressBar = $this->output->createProgressBar($oracleBanks->count());
    $progressBar->start();

    foreach ($oracleBanks as $oracleBank) {
        try {
            // Debug: Show what we're trying to sync
            if ($this->option('verbose') || $syncedCount < 3) {
                $this->info("Syncing bank: {$oracleBank->bank_name} (ID: {$oracleBank->bank_account_id})");
            }
            
            // Sync to MySQL using the correct Oracle column names
            $result = Bank::updateOrCreate(
                [
                    // Using bank_account_id as unique identifier
                    'bank_account_id' => $oracleBank->bank_account_id,
                ],
                [
                    // Map Oracle columns directly to MySQL columns
                    'org_id' => $oracleBank->org_id,
                    'receipt_classes' => $oracleBank->receipt_classes,
                    'receipt_method' => $oracleBank->receipt_method,
                    'receipt_method_id' => $oracleBank->receipt_method_id,
                    'receipt_class_id' => $oracleBank->receipt_class_id,
                    'bank_account_name' => $oracleBank->bank_account_name,
                    'bank_account_num' => $oracleBank->bank_account_num,
                    'iban_number' => $oracleBank->iban_number,
                    'bank_name' => $oracleBank->bank_name,
                    'bank_branch_name' => $oracleBank->bank_branch_name,
                    
                    // Default values for columns that don't exist in Oracle
                    'account_type' => 'current',
                    'currency' => 'PKR',
                    'country' => 'Pakistan',
                    'status' => 'active',
                    
                    // Sync metadata
                    'created_by' => 'Oracle Sync',
                    'synced_at' => now(),
                    'updated_at' => now(),
                ]
            );
            
            if ($this->option('verbose') || $syncedCount < 3) {
                $this->info("✓ Successfully synced: {$oracleBank->bank_name}");
            }
            
            $syncedCount++;
            
        } catch (\Exception $e) {
            $errorCount++;
            $errorMessage = "Failed to sync bank ID {$oracleBank->bank_account_id} ({$oracleBank->bank_name}): " . $e->getMessage();
            $errorDetails = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            
            Log::error($errorMessage, $errorDetails);
            
            // Always show errors in console for debugging
            $this->error($errorMessage);
            $this->error("Error details: " . $e->getFile() . " line " . $e->getLine());
            
            // Show first few errors in detail to understand the pattern
            if ($errorCount <= 3) {
                $this->error("Full error trace: " . $e->getTraceAsString());
            }
        }
        
        $progressBar->advance();
    }

    $progressBar->finish();
    $this->line(''); // New line after progress bar

    $this->info("Sync completed: {$syncedCount} banks synced successfully.");
    
    if ($errorCount > 0) {
        $this->warn("{$errorCount} banks failed to sync. Check logs for details.");
    }

    // Log summary
    Log::info("Oracle banks sync completed", [
        'total_banks' => $oracleBanks->count(),
        'synced_count' => $syncedCount,
        'error_count' => $errorCount,
    ]);
}

    /**
     * Get sync statistics for reporting
     */
    private function getSyncStatistics(): array
    {
        try {
            $oracleCount = OracleBankMaster::active()->count();
            $localCount = Bank::active()->count();
            
            return [
                'oracle_banks' => $oracleCount,
                'local_banks' => $localCount,
                'last_sync' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            return [
                'oracle_banks' => 'Error',
                'local_banks' => Bank::active()->count(),
                'last_sync' => now()->toDateTimeString(),
                'error' => $e->getMessage(),
            ];
        }
    }
}