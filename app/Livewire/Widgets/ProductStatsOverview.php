<?php

namespace App\Livewire\Widgets;

use App\Models\Item;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ProductStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        // Cache the total products count
        $totalProducts = Cache::remember('total_products', 60, fn() => Item::count());

        // Cache the active products count (assuming items have is_active field or similar)
        $activeProducts = Cache::remember('active_products', 60, fn() => Item::whereNotNull('item_code')->count());

        // Cache products added this month
        $productsThisMonth = Cache::remember('products_this_month', 60, fn() => Item::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count());

        return [
            Stat::make('Total Products', $totalProducts)
                ->description('All products in inventory')
                ->icon('heroicon-o-cube')
                ->color('primary'),

            Stat::make('Active Products', $activeProducts)
                ->description('Products with item code')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Added This Month', $productsThisMonth)
                ->description('New products this month')
                ->icon('heroicon-o-plus-circle')
                ->color('info'),
        ];
    }
}
