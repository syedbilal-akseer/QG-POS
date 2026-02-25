<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\OrderType;
use App\Models\OracleOrderType;

class SyncOracleOrderTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-order-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync order types from Oracle database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            // Fetch all order types from Oracle
            $oracleOrderTypes = OracleOrderType::all();

            foreach ($oracleOrderTypes as $oracleOrderType) {
                // Sync to MySQL
                OrderType::updateOrCreate(
                    ['org_id' => $oracleOrderType->org_id],
                    [
                        'order_type_id' => $oracleOrderType->order_type_id,
                        'line_type_id' => $oracleOrderType->line_type_id,
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->info('Order types synced successfully.');
    }
}

