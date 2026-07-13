<?php

namespace App\Console\Commands;

use App\Models\OracleWarehouse;
use App\Models\Warehouse;
use App\Traits\OracleNlsSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleWarehouses extends Command
{
    use OracleNlsSession;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-warehouses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync warehouses from Oracle database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set Oracle session parameters to avoid NLS mismatch errors (ORA-01858)
        $this->setOracleNlsSession();

        DB::transaction(function () {
            // Fetch all warehouses from Oracle
            $oracleWarehouses = OracleWarehouse::all();

            foreach ($oracleWarehouses as $oracleWarehouse) {
                // Sync to MySQL (match by organization_id). The warehouses table
                // intentionally has no timestamp columns (Warehouse::$timestamps
                // is false) — passing updated_at here used to trigger a
                // "Unknown column 'updated_at'" SQL error during the scheduler run.
                Warehouse::updateOrCreate(
                    ['organization_id' => $oracleWarehouse->organization_id],
                    [
                        'organization_code' => $oracleWarehouse->organization_code,
                        'ou'                => $oracleWarehouse->ou,
                    ]
                );
            }
        });

        $this->info('Warehouses synced successfully.');
    }
}
