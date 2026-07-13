<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ranks "frequently / recently used" things for a given user, so the mobile
 * app can surface their most relevant items / customers at the top of lists.
 *
 * Recency wins: items used more recently are higher in the list, with frequency
 * acting as a tiebreaker. The window defaults to the last 180 days.
 */
class UserActivityRanker
{
    /**
     * Item codes the user has ordered most recently (latest order at the top).
     * Returns up to $limit codes ordered for direct use in a sort.
     */
    public static function recentItemCodes(int $userId, int $limit = 50, int $daysBack = 180): array
    {
        $cacheKey = "ranker:items:u{$userId}:l{$limit}:d{$daysBack}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $limit, $daysBack) {
            $rows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.user_id', $userId)
                ->where('orders.created_at', '>=', now()->subDays($daysBack))
                ->whereNull('orders.deleted_at')
                ->select(
                    'order_items.inventory_item_id',
                    DB::raw('MAX(orders.created_at) AS last_used'),
                    DB::raw('COUNT(*) AS frequency')
                )
                ->groupBy('order_items.inventory_item_id')
                ->orderByDesc('last_used')
                ->orderByDesc('frequency')
                ->limit($limit)
                ->get();

            return $rows->pluck('inventory_item_id')->filter()->values()->all();
        });
    }

    /**
     * Customer numbers the user has dealt with most recently (orders).
     * Customer "number" because orders.customer_id stores the Oracle number,
     * not the local PK (see Customer::orders relationship).
     */
    public static function recentCustomerNumbers(int $userId, int $limit = 50, int $daysBack = 180): array
    {
        $cacheKey = "ranker:customers:u{$userId}:l{$limit}:d{$daysBack}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $limit, $daysBack) {
            $rows = DB::table('orders')
                ->where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays($daysBack))
                ->whereNull('deleted_at')
                ->whereNotNull('customer_id')
                ->select(
                    'customer_id',
                    DB::raw('MAX(created_at) AS last_used'),
                    DB::raw('COUNT(*) AS frequency')
                )
                ->groupBy('customer_id')
                ->orderByDesc('last_used')
                ->orderByDesc('frequency')
                ->limit($limit)
                ->get();

            return $rows->pluck('customer_id')->filter()->values()->all();
        });
    }

    /**
     * Sort a result collection so items appearing in $rankedKeys come first
     * (in their ranked order), then the rest in their original order.
     *
     * @param  iterable $items       Original results (collection / array)
     * @param  array    $rankedKeys  Ordered list of key values to prioritise
     * @param  callable $keyOf       fn($item) => key value to match against $rankedKeys
     */
    public static function sortByRanked($items, array $rankedKeys, callable $keyOf)
    {
        if (empty($rankedKeys)) {
            return collect($items)->values();
        }

        $rankedKeys = array_map('strval', $rankedKeys);
        $rankIndex  = array_flip($rankedKeys);

        $ranked  = [];
        $unranked = [];
        $originalOrder = 0;

        foreach ($items as $item) {
            $k = (string) $keyOf($item);
            if (isset($rankIndex[$k])) {
                // Keep position so we can reorder by rank afterwards.
                $ranked[] = ['position' => $rankIndex[$k], 'item' => $item];
            } else {
                $unranked[] = ['position' => $originalOrder, 'item' => $item];
            }
            $originalOrder++;
        }

        usort($ranked, fn ($a, $b) => $a['position'] <=> $b['position']);
        usort($unranked, fn ($a, $b) => $a['position'] <=> $b['position']);

        return collect(array_merge(
            array_column($ranked, 'item'),
            array_column($unranked, 'item')
        ));
    }
}
