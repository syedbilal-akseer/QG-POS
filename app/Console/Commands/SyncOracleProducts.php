<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\OracleItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Oracle database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {
            // Fetch all products from Oracle
            $oracleProducts = OracleItem::all();

            foreach ($oracleProducts as $oracleProduct) {
                // Sync to MySQL (match by item_code)
                Item::updateOrCreate(
                    ['inventory_item_id' => $oracleProduct->inventory_item_id],
                    [
                        'item_code' => $oracleProduct->item_code,
                        'item_description' => $oracleProduct->item_description,
                        'primary_uom_code' => $oracleProduct->primary_uom_code,
                        'secondary_uom_code' => $oracleProduct->secondary_uom_code,
                        'major_category' => $oracleProduct->major_category,
                        'minor_category' => $oracleProduct->minor_category,
                        'sub_minor_category' => $oracleProduct->sub_minor_category,
                    ]
                );
            }
        });

        $this->info('Products synced successfully.');
    }
}
