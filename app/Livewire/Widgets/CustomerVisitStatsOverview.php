<?php

namespace App\Livewire\Widgets;

use App\Models\CustomerVisit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CustomerVisitStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $currentUser = auth()->user();

        // Apply access control
        $canViewAll = $currentUser->isAdmin() || $currentUser->email === 'nauman_ahmad@quadri-group.com';

        if ($canViewAll) {
            // Admin/special user sees all visits
            $query = CustomerVisit::query();
        } else {
            // Regular users see only their own visits
            $query = CustomerVisit::where('user_id', $currentUser->id);
        }

        // Cache the stats
        $cacheKey = $canViewAll ? 'all_visits_stats' : 'user_visits_stats_' . $currentUser->id;

        $stats = Cache::remember($cacheKey, 60, function() use ($query) {
            return [
                'total' => $query->count(),
                'today' => (clone $query)->whereDate('visit_start_time', today())->count(),
                'this_week' => (clone $query)->whereBetween('visit_start_time', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
            ];
        });

        return [
            Stat::make('Total Visits', $stats['total'])
                ->description('All customer visits')
                ->icon('heroicon-o-map-pin')
                ->color('primary'),

            Stat::make('Today\'s Visits', $stats['today'])
                ->description('Visits scheduled for today')
                ->icon('heroicon-o-calendar')
                ->color('success'),

            Stat::make('This Week', $stats['this_week'])
                ->description('Visits this week')
                ->icon('heroicon-o-calendar-days')
                ->color('info'),

            Stat::make('Completed', $stats['completed'])
                ->description('Successfully completed visits')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
