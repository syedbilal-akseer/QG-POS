<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OracleOrderLine;
use Illuminate\Console\Command;
use App\Models\OrderSyncHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOrderWithOracle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:sync-oracle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize MySQL order quantities with Oracle order data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting synchronization...');

        // Batch processing of orders
        Order::with('orderItems.syncHistory')->chunk(100, function ($orders) {
            DB::transaction(function () use ($orders) {
                $progressBar = $this->output->createProgressBar($orders->count());
                foreach ($orders as $order) {
                    $this->info("Processing Order: {$order->order_number}");
                    foreach ($order->orderItems as $orderItem) {
                        $this->info("Checking Item: {$orderItem->inventory_item_id}");
                        $oracleOrderLine = OracleOrderLine::where('orig_sys_document_ref', $order->order_number)
                            ->where('inventory_item_id', $orderItem->inventory_item_id)
                            ->first();

                        if ($oracleOrderLine) {
                            $oracleQuantity = $oracleOrderLine->ordered_quantity;
                            $this->info("Oracle Quantity: {$oracleQuantity}");

                            if ($orderItem->ob_quantity != $oracleQuantity) {
                                $this->info("Updating ob_quantity for Item: {$orderItem->inventory_item_id}");

                                OrderSyncHistory::create([
                                    'order_id' => $order->id,
                                    'item_id' => $orderItem->id,
                                    'previous_quantity' => $orderItem->quantity,
                                    'new_quantity' => $oracleQuantity,
                                    'synced_at' => now(),
                                ]);

                                $orderItem->update([
                                    'ob_quantity' => $oracleQuantity,
                                ]);

                                $this->info("Order item updated successfully.");
                            }
                        } else {
                            $this->warn("No Oracle data found for Item: {$orderItem->inventory_item_id}");
                        }
                    }
                    $progressBar->advance();
                }
                $progressBar->finish();
                $this->info("\nOrder synchronization completed.");
            });
        });

        return Command::SUCCESS;
    }
}
