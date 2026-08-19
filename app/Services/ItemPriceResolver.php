<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemPrice;

/**
 * Resolves the price a customer's price list charges for an item, using the
 * same 3-tier fallback (exact price list -> item_id -> city-wide) that order
 * placement has always used. Extracted out of OrderController::orderPlace so
 * POS checkout and the mobile order API can never drift on pricing rules.
 */
class ItemPriceResolver
{
    public function resolve(Item $item, Customer $customer, ?string $requestedUom = null): ?ItemPrice
    {
        $itemCode = trim((string) $item->item_code);
        $listId   = trim((string) $customer->price_list_id);
        $listName = trim((string) $customer->price_list_name);

        $searchNames = [
            $listName,
            str_replace(' - ', '-', $listName),
            str_replace('-', ' - ', $listName),
            str_replace('-', ' ', $listName),
            str_replace(' - ', ' ', $listName),
        ];

        $today = now()->format('Y-m-d');
        $activeDateFilter = function ($q) use ($today) {
            $q->where(function ($sq) use ($today) {
                $sq->whereNull('start_date_active')
                   ->orWhere('start_date_active', '<=', $today);
            })->where(function ($sq) use ($today) {
                $sq->whereNull('end_date_active')
                   ->orWhere('end_date_active', '>=', $today);
            });
        };

        // Tier 1: exact price_list_id or a name-variant match on item_code.
        $itemPrice = ItemPrice::where('item_code', $itemCode)
            ->where(function ($q) use ($listId, $searchNames) {
                $q->where('price_list_id', $listId);
                foreach ($searchNames as $name) {
                    $q->orWhere('price_list_name', 'LIKE', '%' . $name . '%');
                }
            })
            ->when($requestedUom, fn ($q) => $q->where('uom', $requestedUom))
            ->tap($activeDateFilter)
            ->first();

        // Tier 2: same, keyed by item_id instead of item_code.
        if (! $itemPrice) {
            $itemPrice = ItemPrice::where('item_id', $item->inventory_item_id)
                ->where(function ($q) use ($listId, $searchNames) {
                    $q->where('price_list_id', $listId);
                    foreach ($searchNames as $name) {
                        $q->orWhere('price_list_name', 'LIKE', '%' . $name . '%');
                    }
                })
                ->when($requestedUom, fn ($q) => $q->where('uom', $requestedUom))
                ->tap($activeDateFilter)
                ->first();
        }

        // Tier 3: city-wide fallback (e.g. "Karachi-Wholesale" -> any "Karachi%" list).
        if (! $itemPrice) {
            $cityPrefix = trim(explode('-', $listName)[0] ?? explode(' ', $listName)[0] ?? '');

            if ($cityPrefix) {
                $itemPrice = ItemPrice::where(function ($q) use ($itemCode, $item) {
                        $q->where('item_code', $itemCode)
                          ->orWhere('item_id', $item->inventory_item_id);
                    })
                    ->where('price_list_name', 'LIKE', $cityPrefix . '%')
                    ->whereNotNull('list_price')
                    ->when($requestedUom, fn ($q) => $q->where('uom', $requestedUom))
                    ->tap($activeDateFilter)
                    ->orderBy('list_price', 'desc')
                    ->first();
            }
        }

        return $itemPrice;
    }
}
