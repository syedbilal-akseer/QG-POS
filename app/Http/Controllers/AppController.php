<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Enums\RoleEnum;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class AppController extends Controller
{
    public function index()
    {
        $pageTitle = "Dashboard";
        $user = auth()->user();

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

        return view('admin.index', compact('pageTitle', 'stats', 'user', 'permissions'));
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
