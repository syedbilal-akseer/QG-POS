<?php

namespace App\Console\Commands;

use App\Models\ItemPrice;
use App\Models\OracleItemPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleItemPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-items-price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync items price from Oracle database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            // Fetch all item prices from Oracle
            $oracleItemsPrices = OracleItemPrice::all();

            foreach ($oracleItemsPrices as $oracleItemPrice) {
                // Sync to MySQL
                ItemPrice::updateOrCreate(
                    [
                        'price_list_id' => $oracleItemPrice->price_list_id,
                        'item_id' => $oracleItemPrice->item_id,
                        'item_code' => $oracleItemPrice->item_code,
                        'uom' => $oracleItemPrice->uom,
                    ],
                    [
                        'price_list_name' => $oracleItemPrice->price_list_name,
                        'item_description' => $oracleItemPrice->item_description,
                        'list_price' => $oracleItemPrice->list_price,
                        'start_date_active' => $oracleItemPrice->start_date_active,
                        'end_date_active' => $oracleItemPrice->end_date_active,
                    ]
                );
            }
        });

        $this->info('Item Prices synced successfully.');
    }
}

