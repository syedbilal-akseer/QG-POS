<?php

namespace App\Services;

use App\Models\WMS\Lpn;
use App\Models\WMS\Transaction;

/**
 * Bridges a POS sale to the portal's own warehouse stock (wms_lpns) — the
 * two were previously separate: selling an item at the counter never
 * touched what the warehouse module thought was on the shelf.
 */
class WmsStockService
{
    /**
     * Deduct sold quantity from this OU's on-hand LPNs for an item,
     * earliest-expiry-first (FEFO — matches the doc's expiry-traceability
     * requirement for adhesives/chemicals), falling back to oldest-received
     * when there's no expiry. Never blocks the sale: POS is a commercial
     * transaction and must not fail because warehouse put-away records are
     * incomplete or behind — it deducts what it can and reports the rest.
     *
     * @return float quantity that could NOT be covered by any LPN on hand (0 = fully covered)
     */
    public function deductForSale(string $itemCode, float $quantity, ?string $ouId, string $reference, int $userId): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $query = Lpn::active()->where('item_code', $itemCode)->where('quantity', '>', 0);
        if ($ouId) {
            $query->where('ou_id', $ouId);
        }

        $lpns = $query->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('created_at')->get();

        $remaining = $quantity;

        foreach ($lpns as $lpn) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $lpn->quantity);
            if ($take <= 0) {
                continue;
            }

            $fromLocationId = $lpn->location_id;
            $lpn->decrement('quantity', $take);

            Transaction::create([
                'lpn_id' => $lpn->id,
                'item_code' => $itemCode,
                'transaction_type' => 'pos_sale',
                'from_location_id' => $fromLocationId,
                'to_location_id' => null,
                'quantity' => $take,
                'user_id' => $userId,
                'reference' => $reference,
            ]);

            $remaining -= $take;
        }

        return round($remaining, 3);
    }
}
