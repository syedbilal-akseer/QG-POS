<?php

namespace App\Livewire\Widgets;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class OrderStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $userId = auth()->id();

        // Cache the total orders count (per user)
        $totalOrders = Cache::remember("total_orders_{$userId}", 60, fn() => $this->getOrderQuery()->count());

        // Cache the pending orders count (per user)
        $pendingOrders = Cache::remember("pending_orders_{$userId}", 60, fn() => $this->getOrderQuery()
            ->where('order_status', OrderStatusEnum::PENDING)
            ->count());

        // Cache orders that have been synced to Oracle (where oracle_at is not null) (per user)
        $syncedOrders = Cache::remember("synced_orders_{$userId}", 60, fn() => $this->getOrderQuery()
            ->whereNotNull('oracle_at')
            ->count());

        // Generate chart data for the past 6 months
        $totalOrdersChartData = $this->generateChartData('total_orders');
        $pendingOrdersChartData = $this->generateChartData('pending_orders');
        $syncedOrdersChartData = $this->generateChartData('synced_orders');

        return [
            Stat::make('Total Orders', $totalOrders)
                ->description('Total number of orders')
                ->icon('heroicon-o-clipboard')
                ->chart($totalOrdersChartData)
                ->color('primary'),

            Stat::make('Pending Orders', $pendingOrders)
                ->description('Orders awaiting fulfillment')
                ->icon('heroicon-o-clock')
                ->chart($pendingOrdersChartData)
                ->color('warning'),

            Stat::make('Synced to Oracle', $syncedOrders)
                ->description('Orders synced with Oracle system')
                ->icon('heroicon-o-circle-stack')
                ->chart($syncedOrdersChartData)
                ->color('violet'),
        ];
    }

    /**
     * Generate chart data for the past 6 months.
     */
    protected function generateChartData(string $statType): array
    {
        $data = [];
        $currentDate = now();
        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = $currentDate->copy()->startOfMonth()->subMonths($i);
            $endOfMonth = $startOfMonth->copy()->endOfMonth();

            switch ($statType) {
                case 'total_orders':
                    $count = $this->getOrderQuery()
                        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                        ->count();
                    break;

                case 'pending_orders':
                    $count = $this->getOrderQuery()
                        ->where('order_status', OrderStatusEnum::PENDING)
                        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                        ->count();
                    break;

                case 'synced_orders':
                    $count = $this->getOrderQuery()
                        ->whereNotNull('oracle_at')
                        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                        ->count();
                    break;

                default:
                    $count = 0;
            }

            $data[] = $count;
        }

        return $data;
    }

    /**
     * Get the base query for orders based on the user's role.
     */
    protected function getOrderQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Order::query();
        $user = auth()->user();

        // Salespeople see only their own orders
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            $query->where('user_id', $user->id);
        }
        // Apply OU filtering for location-based roles (filter through customer relationship)
        elseif (!$user->isAdmin()) {
            // Get OU IDs based on user role
            $ouIds = $this->getUserOuIds($user);

            if ($ouIds !== null) {
                // Use whereHas to filter by customer's ou_id
                $query->whereHas('customer', function ($customerQuery) use ($ouIds) {
                    $customerQuery->whereIn('ou_id', $ouIds);
                });
            }
        }

        return $query;
    }

    /**
     * Get OU IDs based on user role and location (same logic as AppController)
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
}

