<?php

namespace App\Console\Commands;

use App\Models\ItemPrice;
use Illuminate\Console\Command;

class DiagnoseOrphanedPrices extends Command
{
    protected $signature = 'diagnose:orphaned-prices {price_list_id? : Limit to a single price_list_id}';

    protected $description = 'List item_code + price_list_id pairs that have price_prices rows but no currently-active one — these items are invisible in customer search/browse for that price list';

    public function handle()
    {
        $now = now();

        $query = ItemPrice::query()->select('item_code', 'price_list_id', 'price_list_name')->distinct();

        if ($priceListId = $this->argument('price_list_id')) {
            $query->where('price_list_id', $priceListId);
        }

        $pairs = $query->get();

        $this->info("Checking {$pairs->count()} item_code + price_list_id pair(s)...");

        $orphaned = [];

        foreach ($pairs as $pair) {
            $stillActive = ItemPrice::where('item_code', $pair->item_code)
                ->where('price_list_id', $pair->price_list_id)
                ->whereNotNull('list_price')
                ->where(function ($q) use ($now) {
                    $q->whereNull('start_date_active')->orWhere('start_date_active', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_date_active')->orWhere('end_date_active', '>=', $now);
                })
                ->exists();

            if (! $stillActive) {
                $orphaned[] = $pair;
            }
        }

        if (empty($orphaned)) {
            $this->info('No orphaned pairs found.');
            return 0;
        }

        $this->warn(count($orphaned).' orphaned item/price-list pair(s) found:');
        $this->table(
            ['item_code', 'price_list_id', 'price_list_name'],
            collect($orphaned)->map(fn ($p) => [$p->item_code, $p->price_list_id, $p->price_list_name])->all()
        );

        return 0;
    }
}
