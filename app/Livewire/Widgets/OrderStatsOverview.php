<?php

namespace App\Livewire\Widgets;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class OrderStatsOverview extends BaseWidget
{

    protected function getStats(): array
    {
        // Cache the total orders count
        $totalOrders = Cache::remember('total_orders', 60, fn() => Order::count());

        // Cache the pending orders count
        $pendingOrders = Cache::remember('pending_orders', 60, fn() => Order::where('order_status', OrderStatusEnum::PENDING)->count());

        // Cache the completed orders count
        $completedOrders = Cache::remember('completed_orders', 60, fn() => Order::where('order_status', OrderStatusEnum::COMPLETED)->count());

        // Cache orders that have been synced to Oracle (where oracle_at is not null)
        $syncedOrders = Cache::remember('synced_orders', 60, fn() => Order::whereNotNull('oracle_at')->count());

        // Generate chart data for the past 6 months
        $totalOrdersChartData = $this->generateChartData('total_orders');
        $pendingOrdersChartData = $this->generateChartData('pending_orders');
        $completedOrdersChartData = $this->generateChartData('completed_orders');
        $syncedOrdersChartData = $this->generateChartData('synced_orders');

        return [
            // Stat for total orders
            Stat::make('Total Orders', $totalOrders)
                ->description('Total number of orders')
                ->icon('heroicon-o-clipboard')
                ->chart($totalOrdersChartData)
                ->color('primary'),

            // Stat for pending orders
            Stat::make('Pending Orders', $pendingOrders)
                ->description('Orders awaiting fulfillment')
                ->icon('heroicon-o-clock')
                ->chart($pendingOrdersChartData)
                ->color('warning'),

            // Stat for completed orders
            Stat::make('Completed Orders', $completedOrders)
                ->description('Orders successfully delivered')
                ->icon('heroicon-o-check-circle')
                ->chart($completedOrdersChartData)
                ->color('success'),

            // Stat for orders synced to Oracle
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
                    $count = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
                    break;

                case 'pending_orders':
                    $count = Order::where('order_status', OrderStatusEnum::PENDING)
                        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
                    break;

                case 'completed_orders':
                    $count = Order::where('order_status', OrderStatusEnum::COMPLETED)
                        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
                    break;

                case 'synced_orders':
                    $count = Order::whereNotNull('oracle_at')
                        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
                    break;

                default:
                    $count = 0;
            }

            $data[] = $count;
        }

        return $data;
    }
}
