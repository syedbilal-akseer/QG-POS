<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\PromotionalItem;
use Illuminate\Support\Facades\Log;

/**
 * Applies promotional "Buy N get M free" schemes to an order. Called from
 * OrderController::orderPlace AFTER paid items have been created but BEFORE
 * order totals are computed, so free items don't inflate the subtotal.
 */
class PromotionalSchemeService
{
    /**
     * Walk the order's paid items, find any matching active scheme, and append
     * is_free order_items for the free quantity. Returns the number of free
     * lines added (for logging / debugging).
     */
    public function applyToOrder($order, array $paidItems): int
    {
        $freeLinesAdded = 0;

        foreach ($paidItems as $line) {
            // $line = ['item_code' => ..., 'quantity' => ..., 'uom' => ...]
            $itemCode = $line['item_code'] ?? null;
            $orderedQty = (int) ($line['quantity'] ?? 0);
            if (!$itemCode || $orderedQty < 1) continue;

            $promo = PromotionalItem::where('item_code', $itemCode)
                ->where('is_active', true)
                ->whereNotNull('buy_qty')
                ->whereNotNull('free_qty')
                ->get()
                ->first(fn ($p) => $p->isSchemeActive() && $p->buy_qty > 0 && $p->free_qty > 0);

            if (!$promo) continue;

            $bundles = intdiv($orderedQty, (int) $promo->buy_qty);
            if ($bundles < 1) continue;

            $freeQty = $bundles * (int) $promo->free_qty;
            $freeItemCode = $promo->free_item_code ?: $promo->item_code;

            $freeItem = Item::where('item_code', $freeItemCode)->first();
            if (!$freeItem) {
                Log::warning('Promo: free_item_code not in items table — skipping free line', [
                    'promo_id'       => $promo->id,
                    'free_item_code' => $freeItemCode,
                ]);
                continue;
            }

            // Pick a plausible UOM so downstream Oracle push doesn't choke.
            $uom = $line['uom']
                ?? optional(ItemPrice::where('item_code', $freeItemCode)->first())->uom
                ?? $freeItem->primary_uom_code
                ?? 'EA';

            $order->orderItems()->create([
                'inventory_item_id' => $freeItem->inventory_item_id,
                'uom'               => $uom,
                'quantity'          => $freeQty,
                'price'             => 0,
                'sub_total'         => 0,
                'discount'          => 0,
                'is_free'           => true,
                'promotion_id'      => $promo->id,
            ]);

            $freeLinesAdded++;

            Log::info('Promo: free line added', [
                'order_id'        => $order->id,
                'trigger_item'    => $itemCode,
                'trigger_qty'     => $orderedQty,
                'promo_buy_qty'   => $promo->buy_qty,
                'promo_free_qty'  => $promo->free_qty,
                'bundles_earned'  => $bundles,
                'free_item_code'  => $freeItemCode,
                'free_qty_added'  => $freeQty,
            ]);
        }

        return $freeLinesAdded;
    }
}
