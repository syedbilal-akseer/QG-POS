<?php

namespace App\Console\Commands;

use App\Models\Transporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOracleTransporters extends Command
{
    protected $signature = 'sync:oracle-transporters';

    protected $description = 'Sync transporters from Oracle APPS.QG_TRANSPORTERS view to local database';

    public function handle()
    {
        $this->info('Starting transporters sync from Oracle...');

        $created = 0;
        $updated = 0;
        $errors  = 0;

        try {
            $rows = DB::connection('oracle')->select('SELECT VALUE, DESCRIPTION FROM APPS.QG_TRANSPORTERS');

            $this->info("Found " . count($rows) . " transporters in Oracle.");

            DB::transaction(function () use ($rows, &$created, &$updated, &$errors) {
                foreach ($rows as $row) {
                    try {
                        $existing = Transporter::where('value', $row->value)->first();

                        Transporter::updateOrCreate(
                            ['value' => $row->value],
                            ['description' => $row->description]
                        );

                        $existing ? $updated++ : $created++;
                    } catch (\Exception $e) {
                        $errors++;
                        $this->error("Error syncing transporter [{$row->value}]: " . $e->getMessage());
                        Log::error("Transporter sync error [{$row->value}]: " . $e->getMessage());
                    }
                }
            });

            $this->info("✓ Transporters synced — Created: {$created}, Updated: {$updated}, Errors: {$errors}");
            Log::info("Transporters sync completed.", compact('created', 'updated', 'errors'));

        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('Transporters sync failed: ' . $e->getMessage());
        }
    }
}
