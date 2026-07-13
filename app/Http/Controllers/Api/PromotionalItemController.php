<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\PromotionalItem;
use Illuminate\Http\JsonResponse;

class PromotionalItemController extends Controller
{
    /**
     * List all active promotional items with full item details, scheme details,
     * and free-item details (when the scheme awards a different item).
     */
    public function index(): JsonResponse
    {
        try {
            $promotions = PromotionalItem::with(['item', 'freeItem'])
                ->where('is_active', true)
                ->get();

            // Bulk-fetch current list prices for the items in one query.
            $itemCodes = $promotions->pluck('item_code')
                ->merge($promotions->pluck('free_item_code')->filter())
                ->unique()->values();

            $latestPrices = ItemPrice::whereIn('item_code', $itemCodes)
                ->orderByDesc('price_updated_at')
                ->get()
                ->groupBy('item_code')
                ->map(fn ($rows) => $rows->first());

            $data = $promotions->map(function (PromotionalItem $p) use ($latestPrices) {
                $original  = (float) $p->original_price;
                $discount  = (float) $p->discounted_price;
                $savings   = max($original - $discount, 0);
                $pct       = $original > 0 ? round(($savings / $original) * 100) : 0;

                $hasScheme = $p->buy_qty && $p->free_qty && $p->buy_qty > 0 && $p->free_qty > 0;
                $freeCode  = $p->free_item_code ?: $p->item_code;
                $freeItem  = $p->free_item_code ? $p->freeItem : $p->item;
                $freePrice = $latestPrices->get($freeCode);

                return [
                    'id'                  => $p->id,
                    'item_code'           => $p->item_code,
                    'price_list_name'     => $p->price_list_name,
                    'image_url'           => $p->image_path ? asset('storage/' . $p->image_path) : null,
                    'is_active'           => (bool) $p->is_active,

                    'pricing' => [
                        'original_price'     => round($original, 2),
                        'discounted_price'   => round($discount, 2),
                        'savings'            => round($savings, 2),
                        'discount_percentage'=> $pct,
                    ],

                    'scheme' => $hasScheme ? [
                        'buy_qty'           => (int) $p->buy_qty,
                        'free_qty'          => (int) $p->free_qty,
                        'free_item_code'    => $freeCode,
                        'free_item_same_as_purchased' => empty($p->free_item_code),
                        'scheme_start_date' => optional($p->scheme_start_date)->toDateString(),
                        'scheme_end_date'   => optional($p->scheme_end_date)->toDateString(),
                        'currently_active'  => $p->isSchemeActive(),
                        'label'             => "Buy {$p->buy_qty} get {$p->free_qty} free",
                        'free_item' => $freeItem ? [
                            'inventory_item_id' => $freeItem->inventory_item_id,
                            'item_code'         => $freeItem->item_code,
                            'description'       => $freeItem->item_description,
                            'primary_uom'       => $freeItem->primary_uom_code,
                            'list_price'        => $freePrice ? (float) $freePrice->list_price : null,
                            'uom'               => $freePrice?->uom,
                        ] : null,
                    ] : null,

                    'item_details' => $p->item ? [
                        'inventory_item_id'   => $p->item->inventory_item_id,
                        'item_code'           => $p->item->item_code,
                        'description'         => $p->item->item_description,
                        'primary_uom'         => $p->item->primary_uom_code,
                        'secondary_uom'       => $p->item->secondary_uom_code,
                        'major_category'      => $p->item->major_category,
                        'minor_category'      => $p->item->minor_category,
                        'sub_minor_category'  => $p->item->sub_minor_category,
                        'current_list_price'  => optional($latestPrices->get($p->item_code))->list_price !== null
                            ? (float) $latestPrices->get($p->item_code)->list_price : null,
                        'current_uom'         => optional($latestPrices->get($p->item_code))->uom,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Promotional items retrieved successfully',
                'count'   => $data->count(),
                'data'    => $data->values(),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to retrieve promotional items: ' . $e->getMessage(),
            ], 500);
        }
    }
}
