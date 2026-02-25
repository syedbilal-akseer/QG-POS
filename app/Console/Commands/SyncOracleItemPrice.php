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
        $this->info('Starting Oracle price sync...');

        // Get total count first
        $totalRecords = OracleItemPrice::count();
        $this->info("Total records to sync: {$totalRecords}");

        if ($totalRecords == 0) {
            $this->warn('No Oracle price records found to sync.');
            return;
        }

        $batchSize = 1000; // Process in batches of 1000
        $totalBatches = ceil($totalRecords / $batchSize);
        $currentBatch = 0;
        $syncedCount = 0;

        $this->info("Processing in {$totalBatches} batches of {$batchSize} records each");

        DB::transaction(function () use ($batchSize, $totalBatches, &$currentBatch, &$syncedCount) {
            OracleItemPrice::chunk($batchSize, function ($oracleItemsPrices) use (&$currentBatch, &$syncedCount, $totalBatches) {
                $currentBatch++;
                $this->info("Syncing Oracle prices - batch {$currentBatch} of {$totalBatches}");

                foreach ($oracleItemsPrices as $oracleItemPrice) {
                    // Log Oracle data for first 10 items per batch
                    if ($syncedCount < 10) {
                        $this->info("Oracle data: {$oracleItemPrice->item_code} | {$oracleItemPrice->price_list_name} | {$oracleItemPrice->uom} | {$oracleItemPrice->list_price}");
                    }

                    // Find existing record using same criteria as web sync: item_code + price_list_name + uom
                    $existingPrice = ItemPrice::where('item_code', $oracleItemPrice->item_code)
                        ->where('price_list_name', $oracleItemPrice->price_list_name)
                        ->where('uom', $oracleItemPrice->uom)
                        ->first();

                    if ($existingPrice) {
                        // Update existing record if price differs
                        $oldPrice = (float) $existingPrice->list_price;
                        $newPrice = (float) $oracleItemPrice->list_price;

                        if ($oldPrice !== $newPrice) {
                            $existingPrice->update([
                                'price_list_id' => $oracleItemPrice->price_list_id,
                                'price_list_name' => $oracleItemPrice->price_list_name,
                                'item_id' => $oracleItemPrice->item_id,
                                'previous_price' => $existingPrice->list_price,
                                'list_price' => $newPrice,
                                'item_description' => $oracleItemPrice->item_description,
                                'start_date_active' => $oracleItemPrice->start_date_active,
                                'end_date_active' => $oracleItemPrice->end_date_active,
                                'updated_at' => now(),
                            ]);

                            // Log updates for first 10 items per batch
                            if ($syncedCount < 10) {
                                $this->info("UPDATED: {$oracleItemPrice->item_code} | {$oldPrice} → {$newPrice} | Diff: " . ($newPrice - $oldPrice));
                            }
                        } else {
                            // Update other fields even if price is the same (to ensure price_list_id and item_id are set)
                            $existingPrice->update([
                                'price_list_id' => $oracleItemPrice->price_list_id,
                                'price_list_name' => $oracleItemPrice->price_list_name,
                                'item_id' => $oracleItemPrice->item_id,
                                'item_description' => $oracleItemPrice->item_description,
                                'start_date_active' => $oracleItemPrice->start_date_active,
                                'end_date_active' => $oracleItemPrice->end_date_active,
                                'updated_at' => now(),
                            ]);
                        }
                    } else {
                        // Create new record
                        ItemPrice::create([
                            'price_list_id' => $oracleItemPrice->price_list_id,
                            'price_list_name' => $oracleItemPrice->price_list_name,
                            'item_id' => $oracleItemPrice->item_id,
                            'item_code' => $oracleItemPrice->item_code,
                            'item_description' => $oracleItemPrice->item_description,
                            'uom' => $oracleItemPrice->uom,
                            'list_price' => $oracleItemPrice->list_price,
                            'start_date_active' => $oracleItemPrice->start_date_active,
                            'end_date_active' => $oracleItemPrice->end_date_active,
                            'price_changed' => false,
                        ]);

                        // Log new records for first 10 items per batch
                        if ($syncedCount < 10) {
                            $this->info("CREATED: {$oracleItemPrice->item_code} | {$oracleItemPrice->price_list_name} | {$oracleItemPrice->uom} | {$oracleItemPrice->list_price}");
                        }
                    }

                    $syncedCount++;
                }

                $this->info("Batch {$currentBatch} completed. Synced {$syncedCount} records so far.");
            });
        });

        $this->info("Oracle price sync completed successfully. Total synced: {$syncedCount} records.");
    }
}

