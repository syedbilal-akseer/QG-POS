<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemPrice;

class DiagnoseItemPrice extends Command
{
    protected $signature = 'diagnose:item-price {customer_id} {item_code}';
    protected $description = 'Diagnose why item price lookup is returning null';

    public function handle()
    {
        $customerId = $this->argument('customer_id');
        $itemCode = $this->argument('item_code');

        $this->info("=== Item Price Diagnosis ===\n");

        // 1. Check ItemPrice model table name
        $itemPriceModel = new ItemPrice();
        $tableName = $itemPriceModel->getTable();
        $this->info("1. ItemPrice model uses table: '{$tableName}'");

        // 2. Check if customer exists
        $customer = Customer::where('customer_id', $customerId)->first();
        if (!$customer) {
            $this->error("Customer {$customerId} not found!");
            return 1;
        }

        $this->info("2. Customer found:");
        $this->line("   - Customer Number: {$customer->customer_number}");
        $this->line("   - Customer Name: {$customer->customer_name}");
        $this->line("   - Price List ID: {$customer->price_list_id}");

        // 3. Check if item exists
        $item = Item::where('item_code', $itemCode)->first();
        if (!$item) {
            $this->error("Item {$itemCode} not found in items table!");
        } else {
            $this->info("3. Item found:");
            $this->line("   - Item Code: {$item->item_code}");
            $this->line("   - Description: {$item->item_description}");
        }

        // 4. Check all item_prices records for this item_code
        $this->info("\n4. All records in 'item_prices' table for item '{$itemCode}':");
        $allPrices = ItemPrice::where('item_code', $itemCode)->get();

        if ($allPrices->isEmpty()) {
            $this->warn("   No records found in 'item_prices' table for item '{$itemCode}'");
        } else {
            $this->line("   Found {$allPrices->count()} record(s):");
            foreach ($allPrices as $price) {
                $this->line("\n   Record #{$price->id}:");
                $this->line("   - Price List ID: {$price->price_list_id}");
                $this->line("   - Price List Name: {$price->price_list_name}");
                $this->line("   - Item Code: {$price->item_code}");
                $this->line("   - List Price: {$price->list_price}");
                $this->line("   - UOM: {$price->uom}");
                $this->line("   - Start Date: " . ($price->start_date_active ? $price->start_date_active->format('Y-m-d H:i:s') : 'NULL'));
                $this->line("   - End Date: " . ($price->end_date_active ? $price->end_date_active->format('Y-m-d H:i:s') : 'NULL'));
                $this->line("   - Current Date: " . now()->format('Y-m-d H:i:s'));
                $this->line("   - Matches customer price_list_id? " . ($price->price_list_id == $customer->price_list_id ? 'YES' : 'NO'));

                // Check date filters
                $startValid = !$price->start_date_active || $price->start_date_active <= now();
                $endValid = !$price->end_date_active || $price->end_date_active >= now();
                $this->line("   - Start date valid? " . ($startValid ? 'YES' : 'NO'));
                $this->line("   - End date valid? " . ($endValid ? 'YES' : 'NO'));
            }
        }

        // 5. Test the actual query used in the API
        $this->info("\n5. Testing actual API query:");
        $this->line("   Query: ItemPrice::where('price_list_id', '{$customer->price_list_id}')");
        $this->line("          ->where('item_code', '{$itemCode}')");
        $this->line("          ->where('start_date_active', '<=', now())");
        $this->line("          ->where(function (\$q) {");
        $this->line("              \$q->whereNull('end_date_active')");
        $this->line("                ->orWhere('end_date_active', '>=', now());");
        $this->line("          })");
        $this->line("          ->first()");

        $itemPrice = ItemPrice::where('price_list_id', $customer->price_list_id)
            ->where('item_code', $itemCode)
            ->where('start_date_active', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date_active')
                  ->orWhere('end_date_active', '>=', now());
            })
            ->first();

        if ($itemPrice) {
            $this->info("\n   ✓ Query FOUND a matching record:");
            $this->line("   - Price: {$itemPrice->list_price}");
            $this->line("   - UOM: {$itemPrice->uom}");
        } else {
            $this->error("\n   ✗ Query returned NULL");
        }

        // 6. Check if there's a "price_lists" table that might be confused with "item_prices"
        $this->info("\n6. Checking for other price-related tables:");
        $tables = DB::select("SHOW TABLES LIKE '%price%'");
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            $this->line("   - Found table: {$tableName}");

            // If there's a price_lists table, check it
            if ($tableName === 'price_lists') {
                $this->warn("\n   WARNING: Found 'price_lists' table (different from 'item_prices')!");
                $priceListsCount = DB::table('price_lists')->where('item_code', $itemCode)->count();
                $this->line("   Records in 'price_lists' for item '{$itemCode}': {$priceListsCount}");
            }
        }

        $this->info("\n=== Diagnosis Complete ===");

        return 0;
    }
}
