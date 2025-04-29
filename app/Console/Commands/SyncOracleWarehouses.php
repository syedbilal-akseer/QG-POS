<?php

namespace App\Console\Commands;

use App\Models\OracleWarehouse;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleWarehouses extends Command
{
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
        DB::transaction(function () {
            // Fetch all warehouses from Oracle
            $oracleWarehouses = OracleWarehouse::all();

            foreach ($oracleWarehouses as $oracleWarehouse) {
                // Sync to MySQL (match by organization_id)
                Warehouse::updateOrCreate(
                    ['organization_id' => $oracleWarehouse->organization_id],
                    [
                        'organization_code' => $oracleWarehouse->organization_code,
                        'ou' => $oracleWarehouse->ou,
                    ]
                );
            }
        });

        $this->info('Warehouses synced successfully.');
    }
}
