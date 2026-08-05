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
        // The actual leaderboard tables are rendered (and paginated) by the
        // Dashboard\SalesLeaderboard Livewire component; we only resolve
        // access + filters here.
        $dashAccess = $this->getDashboardLeaderboardAccess($user);

        $leaderboardFilters = [
            'from'                => $filterFrom,
            'to'                  => $filterTo,
            'salesperson_id'      => $filterSalespersonId,
            'salesperson_options' => ($dashAccess['any'] ?? false)
                ? $this->getLeaderboardSalespersonOptions()
                : [],
        ];

        $sectionLinks = $this->dashboardSectionLinks();

        return view('admin.index', compact(
            'pageTitle', 'stats', 'user', 'permissions',
            'dashAccess', 'leaderboardFilters', 'sectionLinks'
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
     * The per-location salesperson leaderboard (orders + receipts tables,
     * paginated) is rendered by the Dashboard\SalesLeaderboard Livewire
     * component — see app/Livewire/Dashboard/SalesLeaderboard.php. The
     * classifier/override logic below is kept on the controller (rather
     * than only living in App\Services\SalespersonLeaderboardService)
     * because DiagnoseSalespersonLocation reflects into
     * getSalespersonLocations() by method name.
     */
    protected function salespersonLocationNameOverrides(): array
    {
        return app(\App\Services\SalespersonLeaderboardService::class)->salespersonLocationNameOverrides();
    }

    protected function getSalespersonOverrideIds(): array
    {
        return app(\App\Services\SalespersonLeaderboardService::class)->getSalespersonOverrideIds();
    }

    protected function getSalespersonLocations(array $khiOus, array $lhrOus): array
    {
        return app(\App\Services\SalespersonLeaderboardService::class)->getSalespersonLocations($khiOus, $lhrOus);
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

        // CMD-KHI, CMD-LHR, and Head of CMD see receipts (their main responsibility)
        if ($user->isCmdKhi() || $user->isCmdLhr() || $user->isCmdHead()) {
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
     * Destination URL for each dashboard stat-card section, so cards are
     * clickable straight into the relevant list instead of being static
     * numbers. The dashboard route itself is gated to
     * admin/cmd-khi/cmd-lhr/sales-head/view-* (see routes/web.php), and of
     * those, only admin actually sees the Orders/Products/Price
     * Lists/Visits/Customers sections (cmd-khi/cmd-lhr only see Receipts) —
     * so one static route per section is enough; no per-role branching
     * needed, every role that can see a section already has access to the
     * route it links to.
     */
    protected function dashboardSectionLinks(): array
    {
        return [
            'orders'      => route('orders.all'),
            'customers'   => route('customers.all'),
            'products'    => route('products.all'),
            'price_lists' => route('price-lists.index'),
            'receipts'    => route('admin.receipts.index'),
            'visits'      => route('customer-visits.all'),
        ];
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

        // Head of CMD sees both Karachi and Lahore data — no city restriction
        if ($user->isCmdHead()) {
            return [102, 103, 104, 105, 106, 108, 109];
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
