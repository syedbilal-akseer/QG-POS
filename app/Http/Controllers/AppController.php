<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Enums\RoleEnum;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class AppController extends Controller
{
    public function index(Request $request)
    {
        $pageTitle = "Dashboard";
        $user = auth()->user();
        $roleName = $user->role?->name ?? $user->role ?? null;

        if ($roleName === 'inventory-manager') {
            return redirect()->route('inventory.barcode');
        }

        // Determine OU IDs based on user role for filtering
        $ouIds = $this->getUserOuIds($user);

        // Debug: Log the OU IDs for this user
        \Log::info('Dashboard - User OU IDs', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'ou_ids' => $ouIds,
            'is_scm_lhr' => $user->isScmLhr(),
        ]);

        // Determine what sections to show for each role
        $permissions = [
            'show_orders' => $this->canShowOrders($user),
            'show_products' => $this->canShowProducts($user),
            'show_price_lists' => $this->canShowPriceLists($user),
            'show_receipts' => $this->canShowReceipts($user),
            'show_visits' => $this->canShowVisits($user),
            'show_customers' => $this->canShowCustomers($user),
        ];

        // Gather stats ONLY for sections that will be shown (performance optimization)
        $stats = [
            'orders' => $permissions['show_orders'] ? $this->getOrderStats($user, $ouIds) : ['total' => 0, 'pending' => 0, 'synced' => 0],
            'products' => $permissions['show_products'] ? $this->getProductStats($user, $ouIds) : ['total' => 0, 'active' => 0],
            'price_lists' => $permissions['show_price_lists'] ? $this->getPriceListStats($user, $ouIds) : ['total' => 0, 'changed' => 0, 'corporate' => 0, 'trade' => 0, 'wholesaler' => 0],
            'receipts' => $permissions['show_receipts'] ? $this->getReceiptStats($user, $ouIds) : ['total' => 0, 'pending' => 0, 'pushed' => 0, 'total_amount' => 0],
            'visits' => $permissions['show_visits'] ? $this->getVisitStats($user) : ['total' => 0, 'today' => 0, 'completed' => 0],
            'customers' => $permissions['show_customers'] ? $this->getCustomerStats($user) : ['total' => 0, 'with_orders' => 0],
        ];

        // ── Leaderboard filters ───────────────────────────────────────────
        // Defaults to current month. Date range covers single-month, month-range,
        // and arbitrary date-range use cases via two date inputs. Salesperson is
        // an optional multi-select that narrows the leaderboard.
        $defaultFrom = now()->startOfMonth()->toDateString();
        $defaultTo   = now()->endOfMonth()->toDateString();
        $filterFrom  = $request->input('from', $defaultFrom);
        $filterTo    = $request->input('to',   $defaultTo);
        $filterSalespersonId = $request->filled('salesperson_id')
            ? (int) $request->input('salesperson_id')
            : null;
        $filterSalespersonIds = $filterSalespersonId ? [$filterSalespersonId] : [];

        try {
            $startDate = Carbon::parse($filterFrom)->startOfDay();
            $endDate   = Carbon::parse($filterTo)->endOfDay();
            if ($endDate->lt($startDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
                [$filterFrom, $filterTo] = [$startDate->toDateString(), $endDate->toDateString()];
            }
        } catch (\Exception $e) {
            $startDate = now()->startOfMonth();
            $endDate   = now()->endOfMonth();
            $filterFrom = $defaultFrom;
            $filterTo   = $defaultTo;
        }

        // Top salespeople per location — visibility per section is driven by
        // the user's role / specific email overrides for the sales team leads.
        $dashAccess = $this->getDashboardLeaderboardAccess($user);
        $topSalespeople = ($dashAccess['any'] ?? false)
            ? $this->getTopSalespeoplePerLocation($dashAccess, $startDate, $endDate, $filterSalespersonIds)
            : null;

        $leaderboardFilters = [
            'from'                => $filterFrom,
            'to'                  => $filterTo,
            'salesperson_id'      => $filterSalespersonId,
            'salesperson_options' => ($dashAccess['any'] ?? false)
                ? $this->getLeaderboardSalespersonOptions()
                : [],
        ];

        return view('admin.index', compact(
            'pageTitle', 'stats', 'user', 'permissions',
            'topSalespeople', 'dashAccess', 'leaderboardFilters'
        ));
    }

    /**
     * Decide which leaderboard sections each user sees on the dashboard.
     *
     * Resolution order:
     *   1. Admin                       → everything
     *   2. Specific email overrides    → fine-grained (KHI / LHR / all / orders-only)
     *   3. Sales-head + assigned OUs   → scoped by location
     *   4. Anyone else                 → nothing
     *
     * Returns:
     *   [
     *     'orders'   => ['khi' => bool, 'lhr' => bool],
     *     'receipts' => ['khi' => bool, 'lhr' => bool],
     *     'detail'   => bool,   // false → overall-count card only, no top-5 table
     *     'any'      => bool,   // true if any section is visible
     *   ]
     */
    protected function getDashboardLeaderboardAccess($user): array
    {
        $access = [
            'orders'   => ['khi' => false, 'lhr' => false],
            'receipts' => ['khi' => false, 'lhr' => false],
            'detail'   => true,
            'any'      => false,
        ];

        // 1. Admin or any user with 'view-all' role → everything
        if ($user->isAdmin() || (method_exists($user, 'hasViewAll') && $user->hasViewAll())) {
            $access['orders']   = ['khi' => true, 'lhr' => true];
            $access['receipts'] = ['khi' => true, 'lhr' => true];
            $access['any']      = true;
            return $access;
        }

        // 1b. Location-scoped view roles ('view-khi' / 'view-lhr')
        if (method_exists($user, 'hasViewKhi') && method_exists($user, 'hasViewLhr')) {
            $vKhi = $user->hasViewKhi();
            $vLhr = $user->hasViewLhr();
            if ($vKhi || $vLhr) {
                $access['orders']['khi']   = $vKhi;
                $access['orders']['lhr']   = $vLhr;
                $access['receipts']['khi'] = $vKhi;
                $access['receipts']['lhr'] = $vLhr;
                $access['any']             = true;
                return $access;
            }
        }

        // 2. Specific email overrides — these take precedence over role-only logic
        //    so the four sales team leads always see the right scope regardless
        //    of how their UserOrganization assignments end up.
        $email = strtolower($user->email ?? '');

        if ($email === 'masood_qamar@quadri-group.com') {
            // Full access (Karachi + Lahore, orders + receipts, with detail)
            $access['orders']   = ['khi' => true, 'lhr' => true];
            $access['receipts'] = ['khi' => true, 'lhr' => true];
            $access['any']      = true;
            return $access;
        }

        if ($email === 'nauman_ahmad@quadri-group.com') {
            // Karachi only — orders + receipts with full detail
            $access['orders']['khi']   = true;
            $access['receipts']['khi'] = true;
            $access['any']             = true;
            return $access;
        }

        if ($email === 'm_asim@quadri-group.com') {
            // Lahore only — orders + receipts with full detail
            $access['orders']['lhr']   = true;
            $access['receipts']['lhr'] = true;
            $access['any']             = true;
            return $access;
        }

        if ($email === 'muhammad_fahim@quadri-group.com') {
            // Orders only (both cities), overall-count cards (no top-5 detail)
            $access['orders'] = ['khi' => true, 'lhr' => true];
            $access['detail'] = false;
            $access['any']    = true;
            return $access;
        }

        // 3. Sales-head fallback — location derived from UserOrganization
        $isSalesHead = method_exists($user, 'isSalesHead')
            ? $user->isSalesHead()
            : (($user->getRoleName() ?? null) === 'sales-head');

        if ($isSalesHead) {
            $khiOus = [102, 103, 104, 105, 106];
            $lhrOus = [108, 109];
            $userOus = method_exists($user, 'getAllowedOuIds')
                ? $user->getAllowedOuIds()
                : (method_exists($user, 'getOracleOrganizations') ? $user->getOracleOrganizations() : []);

            $hasKhi = !empty(array_intersect($userOus, $khiOus));
            $hasLhr = !empty(array_intersect($userOus, $lhrOus));

            // If a sales-head has no OUs assigned, give them everything (overall only)
            if (!$hasKhi && !$hasLhr) {
                $hasKhi = true;
                $hasLhr = true;
                $access['detail'] = false;
            }

            $access['orders']['khi']   = $hasKhi;
            $access['orders']['lhr']   = $hasLhr;
            $access['receipts']['khi'] = $hasKhi;
            $access['receipts']['lhr'] = $hasLhr;
            $access['any']             = $hasKhi || $hasLhr;
            return $access;
        }

        return $access;
    }

    /**
     * Top 5 salespeople for KHI / LHR by order count + receipt count.
     *
     * Returns:
     *   [
     *     'orders'   => ['khi' => Collection, 'lhr' => Collection, 'khi_total', 'lhr_total'],
     *     'receipts' => ['khi' => Collection, 'lhr' => Collection, 'khi_total', 'lhr_total'],
     *   ]
     *
     * Each collection is { user_id, name, count, total_amount }.
     * Sections the caller doesn't have access to are returned empty so
     * we don't burn DB time computing data that won't be rendered.
     */
    protected function getTopSalespeoplePerLocation(
        ?array $access = null,
        ?Carbon $start = null,
        ?Carbon $end = null,
        array $salespersonIds = []
    ): array {
        // Default: full access (preserves backward compatibility if called without arg)
        $access = $access ?? [
            'orders'   => ['khi' => true, 'lhr' => true],
            'receipts' => ['khi' => true, 'lhr' => true],
            'detail'   => true,
        ];
        $detail = $access['detail'] ?? true;

        // Default to current month if no range provided.
        $start = $start ?? now()->startOfMonth();
        $end   = $end   ?? now()->endOfMonth();

        $khi = [102, 103, 104, 105, 106];
        $lhr = [108, 109];

        // Admin users are excluded from the leaderboard AND from the per-location
        // totals shown above each table — they aren't field salespeople, so their
        // activity skews the rankings. Covers both the string `role` column and
        // a role_id pointing to a role named 'admin' (matches User::isAdmin()).
        $adminUserIds = \App\Models\User::query()
            ->where(function ($q) {
                $q->where('role', 'admin')
                  ->orWhereHas('role', fn ($r) => $r->where('name', 'admin'));
            })
            ->pluck('id')
            ->all();

        // Each salesperson gets classified to exactly one location based on
        // where the majority of their assigned customers live. Prevents the
        // same person showing up in both Karachi and Lahore leaderboards when
        // they happen to have placed orders for customers in both regions.
        $userLocation = $this->getSalespersonLocations($khi, $lhr);
        $khiUserIds = array_keys(array_filter($userLocation, fn ($l) => $l === 'khi'));
        $lhrUserIds = array_keys(array_filter($userLocation, fn ($l) => $l === 'lhr'));

        // Overridden users (e.g. Umair Quadri) — their orders/receipts must
        // count toward their pinned location even when their assigned
        // customers still carry the wrong ou_id, so the closures below OR
        // these IDs in instead of requiring the customer.ou_id join match.
        $overrides           = $this->getSalespersonOverrideIds();
        $khiOverrideUserIds  = $overrides['khi'] ?? [];
        $lhrOverrideUserIds  = $overrides['lhr'] ?? [];

        // If the user picked specific salespeople from the filter, intersect with
        // each location's pre-computed set so a salesperson only appears under
        // their home location.
        if (!empty($salespersonIds)) {
            $khiUserIds         = array_values(array_intersect($khiUserIds, $salespersonIds));
            $lhrUserIds         = array_values(array_intersect($lhrUserIds, $salespersonIds));
            $khiOverrideUserIds = array_values(array_intersect($khiOverrideUserIds, $salespersonIds));
            $lhrOverrideUserIds = array_values(array_intersect($lhrOverrideUserIds, $salespersonIds));
        }

        // Use DB::table() so we get raw rows (stdClass), not Eloquent models.
        // Aggregate queries with SELECT only on a subset of columns trip
        // Eloquent's "attribute [id] not retrieved" guard.
        $orderTops = function (array $ouIds, array $locationUserIds, array $overrideUserIds = []) use ($adminUserIds, $start, $end) {
            if (empty($locationUserIds) && empty($overrideUserIds)) return collect();
            // orders.customer_id references customers.customer_id (Oracle number),
            // NOT customers.id (local PK). See Customer::orders() relationship.
            // NOTE: order_status filter intentionally removed — counts cover every
            // status (pending, pushed, cancelled, etc.) per the dashboard spec.
            //
            // Standard row qualifies when customer.ou_id matches the location AND
            // the order's user is in locationUserIds. Override users (Umair, etc.)
            // qualify regardless of customer.ou_id since their customers carry
            // the wrong tagging.
            $rows = \DB::table('orders')
                ->join('customers', 'customers.customer_id', '=', 'orders.customer_id')
                ->select('orders.user_id',
                    \DB::raw('COUNT(*) as cnt'),
                    \DB::raw('COALESCE(SUM(orders.total_amount), 0) as total_amount'))
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
                ->whereNotIn('orders.user_id', $adminUserIds)
                ->whereBetween('orders.created_at', [$start, $end])
                ->groupBy('orders.user_id')
                ->orderByDesc('cnt')
                ->limit(5)
                ->get();

            // Resolve names in a single follow-up query rather than N+1.
            $names = \App\Models\User::whereIn('id', $rows->pluck('user_id'))
                ->pluck('name', 'id');

            return $rows->map(fn ($row) => [
                'user_id'      => $row->user_id,
                'name'         => $names[$row->user_id] ?? 'Unknown',
                'count'        => (int) $row->cnt,
                'total_amount' => (float) $row->total_amount,
            ]);
        };

        $receiptTops = function (array $ouIds, array $locationUserIds, array $overrideUserIds = []) use ($adminUserIds, $start, $end) {
            if (empty($locationUserIds) && empty($overrideUserIds)) return collect();
            // customer_receipts.customer_id references customers.customer_id,
            // NOT customers.id. See CustomerReceipt::customer() relationship.
            // Override users count regardless of ou_id tagging.
            $rows = \DB::table('customer_receipts')
                ->leftJoin('customers', 'customers.customer_id', '=', 'customer_receipts.customer_id')
                ->select('customer_receipts.created_by',
                    \DB::raw('COUNT(*) as cnt'),
                    \DB::raw('COALESCE(SUM(customer_receipts.receipt_amount), 0) as total_amount'))
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
                ->whereNotIn('customer_receipts.created_by', $adminUserIds)
                ->whereBetween('customer_receipts.created_at', [$start, $end])
                ->groupBy('customer_receipts.created_by')
                ->orderByDesc('cnt')
                ->limit(5)
                ->get();

            $names = \App\Models\User::whereIn('id', $rows->pluck('created_by'))
                ->pluck('name', 'id');

            return $rows->map(fn ($row) => [
                'user_id'      => $row->created_by,
                'name'         => $names[$row->created_by] ?? 'Unknown',
                'count'        => (int) $row->cnt,
                'total_amount' => (float) $row->total_amount,
            ]);
        };

        // Overall totals per location for the card headers — also exclude admin
        // activity and stay consistent with the location-bound leaderboard below.
        $orderTotal = function (array $ouIds, array $locationUserIds, array $overrideUserIds = []) use ($adminUserIds, $start, $end) {
            if (empty($locationUserIds) && empty($overrideUserIds)) return 0;
            return \DB::table('orders')
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
                ->whereNotIn('orders.user_id', $adminUserIds)
                ->whereBetween('orders.created_at', [$start, $end])
                ->count();
        };

        $receiptTotal = function (array $ouIds, array $locationUserIds, array $overrideUserIds = []) use ($adminUserIds, $start, $end) {
            if (empty($locationUserIds) && empty($overrideUserIds)) return 0;
            return \DB::table('customer_receipts')
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
                ->whereNotIn('customer_receipts.created_by', $adminUserIds)
                ->whereBetween('customer_receipts.created_at', [$start, $end])
                ->count();
        };

        $emptyCol = collect();

        return [
            'orders' => [
                'khi'       => ($detail && ($access['orders']['khi'] ?? false)) ? $orderTops($khi, $khiUserIds, $khiOverrideUserIds) : $emptyCol,
                'lhr'       => ($detail && ($access['orders']['lhr'] ?? false)) ? $orderTops($lhr, $lhrUserIds, $lhrOverrideUserIds) : $emptyCol,
                'khi_total' => ($access['orders']['khi'] ?? false) ? $orderTotal($khi, $khiUserIds, $khiOverrideUserIds) : 0,
                'lhr_total' => ($access['orders']['lhr'] ?? false) ? $orderTotal($lhr, $lhrUserIds, $lhrOverrideUserIds) : 0,
            ],
            'receipts' => [
                'khi'       => ($detail && ($access['receipts']['khi'] ?? false)) ? $receiptTops($khi, $khiUserIds, $khiOverrideUserIds) : $emptyCol,
                'lhr'       => ($detail && ($access['receipts']['lhr'] ?? false)) ? $receiptTops($lhr, $lhrUserIds, $lhrOverrideUserIds) : $emptyCol,
                'khi_total' => ($access['receipts']['khi'] ?? false) ? $receiptTotal($khi, $khiUserIds, $khiOverrideUserIds) : 0,
                'lhr_total' => ($access['receipts']['lhr'] ?? false) ? $receiptTotal($lhr, $lhrUserIds, $lhrOverrideUserIds) : 0,
            ],
        ];
    }

    /**
     * Each salesperson is assigned to ONE location. Returns [user_id => 'khi'|'lhr'].
     *
     * Source-of-truth precedence:
     *   1. user_organizations.oracle_ou_id — the actual org assignment the
     *      admin set on the user record. This is authoritative.
     *   2. Fallback for users with no org row: customer-distribution majority
     *      (where do most of their assigned customers live?). Keeps people
     *      who haven't been mapped yet from disappearing from the leaderboard.
     *
     * Why this matters: a salesperson with KHI org assignment who happens to
     * have placed orders for a few Lahore customers would otherwise land in
     * the Lahore leaderboard instead of Karachi (Umair Quadri case).
     *
     * Tied org assignments (one user assigned to both KHI and LHR orgs) and
     * tied customer counts stay unclassified — they appear in neither leaderboard
     * rather than ambiguously in both.
     */
    /**
     * Hard-coded name → location overrides. These users get pinned to a
     * specific location regardless of user_organizations or the customer
     * fallback — and their orders/receipts also count toward that location
     * even when their assigned customers still carry the wrong ou_id.
     * Same pattern as User::salesLeadEmailOuOverride().
     *
     * Umair Quadri: no user_organizations rows; every customer assigned
     * to him still has ou_id=108 (Lahore) from before the KHI move.
     */
    protected function salespersonLocationNameOverrides(): array
    {
        return [
            'Umair Quadri' => 'khi',
        ];
    }

    /**
     * Resolve overrides to user IDs grouped by target location.
     * Returns ['khi' => [user_id, ...], 'lhr' => [...]].
     */
    protected function getSalespersonOverrideIds(): array
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

    protected function getSalespersonLocations(array $khiOus, array $lhrOus): array
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
            // Find every distinct salesperson name in customers, see which of
            // them already have a classification via $result, and only fall back
            // for the rest. Prevents users without an org assignment from
            // disappearing from the leaderboard entirely.
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
     * Build the salesperson dropdown for the leaderboard filter — every user
     * who has ever placed an order or created a receipt, minus admin users.
     * Cached because the list rarely changes and runs on every dashboard load.
     */
    protected function getLeaderboardSalespersonOptions(): array
    {
        return Cache::remember('dashboard_leaderboard_sp_options_v1', now()->addMinutes(30), function () {
            $userIds = DB::table('orders')->whereNotNull('user_id')->distinct()->pluck('user_id')
                ->merge(
                    DB::table('customer_receipts')->whereNotNull('created_by')->distinct()->pluck('created_by')
                )
                ->unique()
                ->values();

            if ($userIds->isEmpty()) return [];

            return User::query()
                ->whereIn('id', $userIds)
                ->where(function ($q) {
                    $q->where('role', '!=', 'admin')
                      ->orWhereNull('role');
                })
                ->whereDoesntHave('role', fn ($r) => $r->where('name', 'admin'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        });
    }

    /**
     * Check if user can show orders section
     */
    protected function canShowOrders($user): bool
    {
        // Admin sees all orders
        if ($user->isAdmin()) {
            return true;
        }

        // Salespeople see their orders
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            return true;
        }

        // Supply Chain and SCM-LHR users see orders
        if ($user->isSupplyChain() || $user->isScmLhr()) {
            return true;
        }

        // CMD-KHI and CMD-LHR do NOT see orders (they see receipts instead)
        return false;
    }

    /**
     * Check if user can show products section
     */
    protected function canShowProducts($user): bool
    {
        // Only admin sees products
        return $user->isAdmin();
    }

    /**
     * Check if user can show price lists section
     */
    protected function canShowPriceLists($user): bool
    {
        // Admin and price-uploads role can see price lists
        return $user->isAdmin() || $user->isPriceUploads();
    }

    /**
     * Check if user can show receipts section
     */
    protected function canShowReceipts($user): bool
    {
        // Admin sees all receipts
        if ($user->isAdmin()) {
            return true;
        }

        // CMD-KHI and CMD-LHR see receipts (their main responsibility)
        if ($user->isCmdKhi() || $user->isCmdLhr()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can show visits section
     */
    protected function canShowVisits($user): bool
    {
        // Admin sees all visits
        if ($user->isAdmin()) {
            return true;
        }

        // Salespeople see their own visits
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can show customers section
     */
    protected function canShowCustomers($user): bool
    {
        // Admin sees all customers
        if ($user->isAdmin()) {
            return true;
        }

        // Salespeople see their customers
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            return true;
        }

        return false;
    }

    /**
     * Get OU IDs based on user role and location
     */
    protected function getUserOuIds($user): ?array
    {
        // Admin sees all data - no OU filtering
        if ($user->isAdmin()) {
            return null;
        }

        // CMD-KHI sees only Karachi data
        if ($user->isCmdKhi()) {
            return [102, 103, 104, 105, 106];
        }

        // CMD-LHR sees only Lahore data
        if ($user->isCmdLhr()) {
            return [108, 109];
        }

        // SCM-LHR sees only Lahore data
        if ($user->isScmLhr()) {
            return [108, 109];
        }

        // Supply-chain users use their assigned organizations
        if ($user->isSupplyChain()) {
            $oracleOrgs = $user->getOracleOrganizations();
            return !empty($oracleOrgs) ? $oracleOrgs : null;
        }

        // Default: no filtering (for other roles)
        return null;
    }

    /**
     * Get order statistics filtered by user role
     */
    protected function getOrderStats($user, ?array $ouIds): array
    {
        $query = \App\Models\Order::query();

        // Debug: Log incoming parameters
        \Log::info('getOrderStats - Start', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'ou_ids' => $ouIds,
        ]);

        // Salespeople see only their own orders
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            $query->where('user_id', $user->id);
            \Log::info('getOrderStats - Salesperson filter applied');
        }
        // Apply OU filtering for location-based roles (filter through customer relationship)
        elseif ($ouIds !== null) {
            // Use whereHas to filter by customer's ou_id (same as ListOrders component)
            $query->whereHas('customer', function ($customerQuery) use ($ouIds) {
                $customerQuery->whereIn('ou_id', $ouIds);
            });

            \Log::info('getOrderStats - OU filter applied', [
                'ou_ids' => $ouIds,
            ]);
        } else {
            \Log::info('getOrderStats - NO filter applied (ouIds is null)');
        }

        $total = (clone $query)->count();
        $pending = (clone $query)->where('order_status', \App\Enums\OrderStatusEnum::PENDING)->count();
        $synced = (clone $query)->whereNotNull('oracle_at')->count();

        \Log::info('getOrderStats - Results', [
            'total' => $total,
            'pending' => $pending,
            'synced' => $synced,
        ]);

        return [
            'total' => $total,
            'pending' => $pending,
            'synced' => $synced,
        ];
    }

    /**
     * Get product statistics filtered by user role
     */
    protected function getProductStats($user, ?array $ouIds): array
    {
        $query = \App\Models\Item::query();

        // Apply OU filtering if needed (products might be filtered by warehouse OU)
        if ($ouIds !== null) {
            // Products are generally not filtered by OU, but we can filter by warehouse
            // For now, show all products
        }

        return [
            'total' => \App\Models\Item::count(),
            'active' => \App\Models\Item::whereNotNull('item_code')->count(),
        ];
    }

    /**
     * Get price list statistics filtered by user role
     */
    protected function getPriceListStats($user, ?array $ouIds): array
    {
        $query = \App\Models\ItemPrice::query();

        // Price lists are admin-only data (no OU filtering needed)
        // Price lists are identified by price_list_id (7010-7012 for Karachi, 7007-7009 for Lahore)
        // If we need location filtering, we'd filter by price_list_id ranges, not ou_id

        return [
            'total' => (clone $query)->count('id'),
            'changed' => (clone $query)->where('price_changed', true)->count(),
            'corporate' => (clone $query)->where('price_type', 'corporate')->count(),
            'trade' => (clone $query)->where('price_type', 'trade')->count(),
            'wholesaler' => (clone $query)->where('price_type', 'wholesaler')->count(),
        ];
    }

    /**
     * Get receipt statistics filtered by user role
     */
    protected function getReceiptStats($user, ?array $ouIds): array
    {
        $query = \App\Models\CustomerReceipt::query();

        // Check if CMD user has assigned salespeople
        if (($user->isCmdKhi() || $user->isCmdLhr()) && !$user->hasAllSalespeopleAccess()) {
            $assignedSalespeopleIds = $user->getAssignedSalespeopleIds();

            // Filter receipts by assigned salespeople (created_by)
            if (!empty($assignedSalespeopleIds)) {
                $query->whereIn('created_by', $assignedSalespeopleIds);
            } else {
                // If no matching users found, return empty results
                return [
                    'total' => 0,
                    'pending' => 0,
                    'pushed' => 0,
                    'total_amount' => 0,
                ];
            }

            // Still apply OU filtering for location
            if ($ouIds !== null) {
                $query->where(function($q) use ($ouIds) {
                    $q->whereIn('ou_id', $ouIds)
                      ->orWhereHas('customer', function($customerQuery) use ($ouIds) {
                          $customerQuery->whereIn('ou_id', $ouIds);
                      });
                });
            }
        }
        // Regular OU filtering for other users or CMD users with "All" access
        elseif ($ouIds !== null) {
            // Filter by receipt's ou_id OR by customer's ou_id (fallback for older receipts)
            $query->where(function($q) use ($ouIds) {
                $q->whereIn('ou_id', $ouIds)
                  ->orWhereHas('customer', function($customerQuery) use ($ouIds) {
                      $customerQuery->whereIn('ou_id', $ouIds);
                  });
            });
        }

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->whereNull('oracle_entered_at')->count(),
            'pushed' => (clone $query)->whereNotNull('oracle_entered_at')->count(),
            'total_amount' => (clone $query)->sum('receipt_amount'),
        ];
    }

    /**
     * Get visit statistics filtered by user role
     */
    protected function getVisitStats($user): array
    {
        $query = \App\Models\CustomerVisit::query();

        // Salespeople see only their own visits
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            $query->where('user_id', $user->id);
        }

        return [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->whereDate('visit_start_time', today())->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];
    }

    /**
     * Get customer statistics filtered by user role
     */
    protected function getCustomerStats($user): array
    {
        $query = \App\Models\Customer::query();

        // Salespeople see customers they've interacted with (via orders or visits)
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            // Get customer IDs from user's orders
            $orderCustomerIds = \App\Models\Order::where('user_id', $user->id)
                ->whereNotNull('customer_id')
                ->distinct()
                ->pluck('customer_id')
                ->filter();

            // Get customer IDs from user's visits
            $visitCustomerIds = \App\Models\CustomerVisit::where('user_id', $user->id)
                ->whereNotNull('customer_id')
                ->distinct()
                ->pluck('customer_id')
                ->filter();

            // Combine both lists
            $customerIds = $orderCustomerIds->merge($visitCustomerIds)->unique()->values();

            if ($customerIds->isNotEmpty()) {
                $query->whereIn('id', $customerIds);
            } else {
                // If no customer IDs found, return empty results
                return [
                    'total' => 0,
                    'with_orders' => 0,
                ];
            }

            // Get customers with recent orders (for this salesperson only)
            $recentOrderCustomerIds = \App\Models\Order::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subMonths(6))
                ->whereNotNull('customer_id')
                ->distinct()
                ->pluck('customer_id')
                ->filter();

            return [
                'total' => $query->count(),
                'with_orders' => $recentOrderCustomerIds->count(),
            ];
        }

        // Admin and other roles see all customers
        // Get count of customers with recent orders (last 6 months)
        $recentOrderCustomerIds = \App\Models\Order::query()
            ->where('created_at', '>=', now()->subMonths(6))
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->filter();

        $totalCustomers = $query->count();
        $customersWithOrders = 0;

        if ($recentOrderCustomerIds->isNotEmpty()) {
            $customersWithOrders = \App\Models\Customer::query()
                ->whereIn('id', $recentOrderCustomerIds)
                ->count();
        }

        return [
            'total' => $totalCustomers,
            'with_orders' => $customersWithOrders,
        ];
    }

    public function orders()
    {
        $pageTitle = "Dashboard";
        return view('admin.orders.index', compact('pageTitle'));
    }

    public function products()
    {
        $pageTitle = "Dashboard";
        return view('admin.products.index', compact('pageTitle'));
    }

    public function customers()
    {
        $pageTitle = "Dashboard";
        return view('admin.customers.index', compact('pageTitle'));
    }

    public function users()
    {
        $pageTitle = "Dashboard";
        return view('admin.users.index', compact('pageTitle'));
    }

    public function monthlyTourPlans()
    {
        $pageTitle = "Monthly Tour Plans";
        return view('crm.monthly-tour-plans', compact('pageTitle'));
    }

    public function visits()
    {
        $pageTitle = "Manage Visits";
        return view('crm.manage-visit', compact('pageTitle'));
    }
}
