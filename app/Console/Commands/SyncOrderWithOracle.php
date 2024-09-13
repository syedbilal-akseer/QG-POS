<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OracleOrderLine;
use Illuminate\Console\Command;

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
        // Fetch all orders that need to be synchronized
        $orders = Order::with('orderItems')->get();

        foreach ($orders as $order) {
            $this->info("Checking Order: {$order->order_number}");

            foreach ($order->orderItems as $orderItem) {
                $this->info("Checking Item: {$orderItem->inventory_item_id}");

                // Fetch the Oracle order line for the same inventory item ID
                $oracleOrderLine = OracleOrderLine::where('orig_sys_document_ref', $order->order_number)
                    ->where('inventory_item_id', $orderItem->inventory_item_id)
                    ->first();

                if ($oracleOrderLine) {
                    // Get the ordered quantity from Oracle
                    $oracleQuantity = $oracleOrderLine->ordered_quantity;

                    $this->info("Oracle Quantity: {$oracleQuantity}");

                    // If the Oracle quantity is different from the current ob_quantity, update it
                    if ($orderItem->ob_quantity != $oracleQuantity) {
                        $this->info("Updating ob_quantity for Item: {$orderItem->inventory_item_id}");

                        // Update the order item with the new quantity from Oracle
                        $orderItem->update([
                            'ob_quantity' => $oracleQuantity,
                        ]);

                        $this->info("Order item updated successfully.");
                    }
                } else {
                    $this->warn("No Oracle data found for Item: {$orderItem->inventory_item_id}");
                }
            }
        }

        $this->info('Order synchronization completed.');
        return Command::SUCCESS;
    }
}
