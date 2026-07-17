<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shared query logic behind the dashboard's per-location salesperson
 * leaderboard (orders + receipts, Karachi/Lahore). Used by AppController
 * (access gating + the salesperson-location classifier, which
 * DiagnoseSalespersonLocation also reflects into) and by the
 * Dashboard\SalesLeaderboard Livewire component (paginated tables).
 */
class SalespersonLeaderboardService
{
    public const KHI_OUS = [102, 103, 104, 105, 106];
    public const LHR_OUS = [108, 109];

    protected ?array $adminUserIdsCache = null;

    /**
     * Hard-coded name → location overrides. These users get pinned to a
     * specific location regardless of user_organizations or the customer
     * fallback — and their orders/receipts also count toward that location
     * even when their assigned customers still carry the wrong ou_id.
     *
     * Umair Quadri: no user_organizations rows; every customer assigned
     * to him still has ou_id=108 (Lahore) from before the KHI move.
     */
    public function salespersonLocationNameOverrides(): array
    {
        return [
            'Umair Quadri' => 'khi',
        ];
    }

    /**
     * Resolve overrides to user IDs grouped by target location.
     * Returns ['khi' => [user_id, ...], 'lhr' => [...]].
     */
    public function getSalespersonOverrideIds(): array
    {
        $cacheKey = 'dashboard_salesperson_overrides_v1';
        return Cache::remember($cacheKey, now()->addMinutes(30), function () {
            $names = $this->salespersonLocationNameOverrides();
            if (empty($names)) return ['khi' => [], 'lhr' => []];

            $users = User::query()
                ->select('id', 'name')
                ->whereIn('name', array_keys($names))
                ->get();

            $out = ['khi' => [], 'lhr' => []];
            foreach ($users as $u) {
                $loc = $names[$u->name] ?? null;
                if ($loc && isset($out[$loc])) {
                    $out[$loc][] = (int) $u->id;
                }
            }
            return $out;
        });
    }

    /**
     * Each salesperson is assigned to ONE location. Returns [user_id => 'khi'|'lhr'].
     *
     * Source-of-truth precedence:
     *   1. user_organizations.oracle_ou_id — the actual org assignment the
     *      admin set on the user record. This is authoritative.
     *   2. Fallback for users with no org row: customer-distribution majority
     *      (where do most of their assigned customers live?).
     *
     * Tied org assignments and tied customer counts stay unclassified.
     */
    public function getSalespersonLocations(array $khiOus, array $lhrOus): array
    {
        $cacheKey = 'dashboard_salesperson_locations_v3';
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($khiOus, $lhrOus) {
            $nameOverrides = $this->salespersonLocationNameOverrides();

            // ── Primary source: user_organizations.oracle_ou_id ──
            $orgRows = DB::table('user_organizations')
                ->select('user_id', 'oracle_ou_id')
                ->where('is_active', true)
                ->whereNotNull('oracle_ou_id')
                ->get();

            $perUser = [];
            foreach ($orgRows as $r) {
                $ouInt = (int) $r->oracle_ou_id;
                $loc = in_array($ouInt, $khiOus, true) ? 'khi'
                    : (in_array($ouInt, $lhrOus, true) ? 'lhr' : null);
                if (!$loc) continue;
                if (!isset($perUser[$r->user_id])) {
                    $perUser[$r->user_id] = ['khi' => 0, 'lhr' => 0];
                }
                $perUser[$r->user_id][$loc]++;
            }

            $result = [];
            foreach ($perUser as $userId => $counts) {
                if ($counts['khi'] > $counts['lhr']) $result[(int) $userId] = 'khi';
                elseif ($counts['lhr'] > $counts['khi']) $result[(int) $userId] = 'lhr';
                // tied (assigned to both KHI and LHR) → not classified
            }

            // ── Fallback for users with NO active org row: customer-distribution majority ──
            $custRows = DB::table('customers')
                ->select('salesperson', 'ou_id', DB::raw('COUNT(*) as cnt'))
                ->whereNotNull('salesperson')
                ->whereNotNull('ou_id')
                ->groupBy('salesperson', 'ou_id')
                ->get();

            $perName = [];
            foreach ($custRows as $r) {
                $ouInt = (int) $r->ou_id;
                $loc = in_array($ouInt, $khiOus, true) ? 'khi'
                    : (in_array($ouInt, $lhrOus, true) ? 'lhr' : null);
                if (!$loc) continue;
                if (!isset($perName[$r->salesperson])) {
                    $perName[$r->salesperson] = ['khi' => 0, 'lhr' => 0];
                }
                $perName[$r->salesperson][$loc] += (int) $r->cnt;
            }

            if (!empty($perName)) {
                $names = array_keys($perName);
                $users = User::query()
                    ->select('id', 'name', 'oracle_user_name')
                    ->where(function ($q) use ($names) {
                        $q->whereIn('name', $names)
                          ->orWhereIn('oracle_user_name', $names);
                    })
                    ->get();

                foreach ($users as $u) {
                    $uid = (int) $u->id;
                    if (isset($result[$uid])) continue; // already classified via org

                    $counts = $perName[$u->name]
                        ?? $perName[$u->oracle_user_name]
                        ?? null;
                    if (!$counts) continue;

                    if ($counts['khi'] > $counts['lhr']) $result[$uid] = 'khi';
                    elseif ($counts['lhr'] > $counts['khi']) $result[$uid] = 'lhr';
                }
            }

            // ── Apply name overrides LAST so they trump both sources. ──
            if (!empty($nameOverrides)) {
                $overrideUsers = User::query()
                    ->select('id', 'name')
                    ->whereIn('name', array_keys($nameOverrides))
                    ->get();
                foreach ($overrideUsers as $u) {
                    $result[(int) $u->id] = $nameOverrides[$u->name];
                }
            }

            return $result;
        });
    }

    /**
     * Admin users are excluded from the leaderboard AND from the per-location
     * totals — they aren't field salespeople, so their activity skews the
     * rankings. Covers both the string `role` column and a role_id pointing
     * to a role named 'admin' (matches User::isAdmin()). Memoized per
     * service instance since it's queried repeatedly per dashboard render.
     */
    public function adminUserIds(): array
    {
        if ($this->adminUserIdsCache === null) {
            $this->adminUserIdsCache = User::query()
                ->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhereHas('role', fn ($r) => $r->where('name', 'admin'));
                })
                ->pluck('id')
                ->all();
        }
        return $this->adminUserIdsCache;
    }

    /**
     * Resolve the per-location user id sets (regular + override) for both
     * Karachi and Lahore, optionally narrowed to a salesperson filter so a
     * salesperson only appears under their home location.
     */
    public function resolveLocationUserIds(array $salespersonIds = []): array
    {
        $userLocation = $this->getSalespersonLocations(self::KHI_OUS, self::LHR_OUS);
        $khiUserIds = array_keys(array_filter($userLocation, fn ($l) => $l === 'khi'));
        $lhrUserIds = array_keys(array_filter($userLocation, fn ($l) => $l === 'lhr'));

        $overrides = $this->getSalespersonOverrideIds();
        $khiOverrideUserIds = $overrides['khi'] ?? [];
        $lhrOverrideUserIds = $overrides['lhr'] ?? [];

        if (!empty($salespersonIds)) {
            $khiUserIds = array_values(array_intersect($khiUserIds, $salespersonIds));
            $lhrUserIds = array_values(array_intersect($lhrUserIds, $salespersonIds));
            $khiOverrideUserIds = array_values(array_intersect($khiOverrideUserIds, $salespersonIds));
            $lhrOverrideUserIds = array_values(array_intersect($lhrOverrideUserIds, $salespersonIds));
        }

        return compact('khiUserIds', 'lhrUserIds', 'khiOverrideUserIds', 'lhrOverrideUserIds');
    }

    // Use DB::table() so we get raw rows (stdClass), not Eloquent models.
    // orders.customer_id references customers.customer_id (Oracle number),
    // NOT customers.id. See Customer::orders() relationship.
    // NOTE: order_status filter intentionally omitted — counts cover every
    // status (pending, pushed, cancelled, etc.) per the dashboard spec.
    protected function ordersQuery(array $ouIds, array $locationUserIds, array $overrideUserIds, Carbon $start, Carbon $end)
    {
        return DB::table('orders')
            ->join('customers', 'customers.customer_id', '=', 'orders.customer_id')
            ->select('orders.user_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(orders.total_amount), 0) as total_amount'))
            ->where(function ($q) use ($ouIds, $locationUserIds, $overrideUserIds) {
                if (!empty($locationUserIds)) {
                    $q->orWhere(function ($a) use ($ouIds, $locationUserIds) {
                        $a->whereIn('customers.ou_id', $ouIds)
                          ->whereIn('orders.user_id', $locationUserIds);
                    });
                }
                if (!empty($overrideUserIds)) {
                    $q->orWhereIn('orders.user_id', $overrideUserIds);
                }
            })
            ->whereNotIn('orders.user_id', $this->adminUserIds())
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('orders.user_id');
    }

    // customer_receipts.customer_id references customers.customer_id,
    // NOT customers.id. See CustomerReceipt::customer() relationship.
    protected function receiptsQuery(array $ouIds, array $locationUserIds, array $overrideUserIds, Carbon $start, Carbon $end)
    {
        return DB::table('customer_receipts')
            ->leftJoin('customers', 'customers.customer_id', '=', 'customer_receipts.customer_id')
            ->select('customer_receipts.created_by',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(customer_receipts.receipt_amount), 0) as total_amount'))
            ->where(function ($q) use ($ouIds, $locationUserIds, $overrideUserIds) {
                if (!empty($locationUserIds)) {
                    $q->orWhere(function ($a) use ($ouIds, $locationUserIds) {
                        $a->where(function ($inner) use ($ouIds) {
                            $inner->whereIn('customer_receipts.ou_id', $ouIds)
                                  ->orWhereIn('customers.ou_id', $ouIds);
                        })
                        ->whereIn('customer_receipts.created_by', $locationUserIds);
                    });
                }
                if (!empty($overrideUserIds)) {
                    $q->orWhereIn('customer_receipts.created_by', $overrideUserIds);
                }
            })
            ->whereNotIn('customer_receipts.created_by', $this->adminUserIds())
            ->whereBetween('customer_receipts.created_at', [$start, $end])
            ->groupBy('customer_receipts.created_by');
    }

    public function paginateOrders(array $ouIds, array $locationUserIds, array $overrideUserIds, Carbon $start, Carbon $end, int $perPage, string $pageName): LengthAwarePaginator
    {
        if (empty($locationUserIds) && empty($overrideUserIds)) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['pageName' => $pageName]);
        }

        $paginator = $this->ordersQuery($ouIds, $locationUserIds, $overrideUserIds, $start, $end)
            ->orderByDesc('cnt')
            ->paginate($perPage, ['*'], $pageName);

        $names = User::whereIn('id', $paginator->pluck('user_id'))->pluck('name', 'id');

        $paginator->getCollection()->transform(fn ($row) => [
            'user_id'      => $row->user_id,
            'name'         => $names[$row->user_id] ?? 'Unknown',
            'count'        => (int) $row->cnt,
            'total_amount' => (float) $row->total_amount,
        ]);

        return $paginator;
    }

    public function paginateReceipts(array $ouIds, array $locationUserIds, array $overrideUserIds, Carbon $start, Carbon $end, int $perPage, string $pageName): LengthAwarePaginator
    {
        if (empty($locationUserIds) && empty($overrideUserIds)) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['pageName' => $pageName]);
        }

        $paginator = $this->receiptsQuery($ouIds, $locationUserIds, $overrideUserIds, $start, $end)
            ->orderByDesc('cnt')
            ->paginate($perPage, ['*'], $pageName);

        $names = User::whereIn('id', $paginator->pluck('created_by'))->pluck('name', 'id');

        $paginator->getCollection()->transform(fn ($row) => [
            'user_id'      => $row->created_by,
            'name'         => $names[$row->created_by] ?? 'Unknown',
            'count'        => (int) $row->cnt,
            'total_amount' => (float) $row->total_amount,
        ]);

        return $paginator;
    }

    public function totalOrders(array $ouIds, array $locationUserIds, array $overrideUserIds, Carbon $start, Carbon $end): int
    {
        if (empty($locationUserIds) && empty($overrideUserIds)) return 0;

        return DB::table('orders')
            ->join('customers', 'customers.customer_id', '=', 'orders.customer_id')
            ->where(function ($q) use ($ouIds, $locationUserIds, $overrideUserIds) {
                if (!empty($locationUserIds)) {
                    $q->orWhere(function ($a) use ($ouIds, $locationUserIds) {
                        $a->whereIn('customers.ou_id', $ouIds)
                          ->whereIn('orders.user_id', $locationUserIds);
                    });
                }
                if (!empty($overrideUserIds)) {
                    $q->orWhereIn('orders.user_id', $overrideUserIds);
                }
            })
            ->whereNotIn('orders.user_id', $this->adminUserIds())
            ->whereBetween('orders.created_at', [$start, $end])
            ->count();
    }

    public function totalReceipts(array $ouIds, array $locationUserIds, array $overrideUserIds, Carbon $start, Carbon $end): int
    {
        if (empty($locationUserIds) && empty($overrideUserIds)) return 0;

        return DB::table('customer_receipts')
            ->leftJoin('customers', 'customers.customer_id', '=', 'customer_receipts.customer_id')
            ->where(function ($q) use ($ouIds, $locationUserIds, $overrideUserIds) {
                if (!empty($locationUserIds)) {
                    $q->orWhere(function ($a) use ($ouIds, $locationUserIds) {
                        $a->where(function ($inner) use ($ouIds) {
                            $inner->whereIn('customer_receipts.ou_id', $ouIds)
                                  ->orWhereIn('customers.ou_id', $ouIds);
                        })
                        ->whereIn('customer_receipts.created_by', $locationUserIds);
                    });
                }
                if (!empty($overrideUserIds)) {
                    $q->orWhereIn('customer_receipts.created_by', $overrideUserIds);
                }
            })
            ->whereNotIn('customer_receipts.created_by', $this->adminUserIds())
            ->whereBetween('customer_receipts.created_at', [$start, $end])
            ->count();
    }
}
