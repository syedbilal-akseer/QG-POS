<?php

namespace App\Livewire\Widgets;

use App\Models\Visit;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class VisitExpensesStatsOverview extends BaseWidget
{
    public Visit $visit;

    protected function getStats(): array
    {
        // Cache the total expenses count for this visit
        $totalExpenses = Cache::remember("visit_{$this->visit->id}_total_expenses", 60, function () {
            return $this->visit->expenses()->count();
        });

        // Cache the total amount spent for this visit
        $totalAmount = Cache::remember("visit_{$this->visit->id}_total_amount", 60, function () {
            return $this->visit->expenses()->sum('total');
        });

        // Cache the pending expenses count for this visit
        $pendingExpenses = Cache::remember("visit_{$this->visit->id}_pending_expenses", 60, function () {
            return $this->visit->expenses()->where('status', 'pending')->count();
        });

        // Cache the approved expenses count for this visit
        $approvedExpenses = Cache::remember("visit_{$this->visit->id}_approved_expenses", 60, function () {
            return $this->visit->expenses()->where('status', 'approved')->count();
        });

        return [
            // Total expenses stat
            Stat::make('Total Expenses', $totalExpenses)
                ->description('Number of recorded expenses')
                ->chart($this->generateChartData('total_expenses'))
                ->icon('heroicon-o-clipboard')
                ->color('primary'),

            // Total amount stat
            Stat::make('Total Amount', 'Rs ' . number_format($totalAmount, 2))
                ->description('Total amount spent')
                ->chart($this->generateChartData('total_amount'))
                ->icon('heroicon-o-currency-rupee')
                ->color('success'),

            // Pending expenses stat
            Stat::make('Pending Expenses', $pendingExpenses)
                ->description('Expenses awaiting approval')
                ->chart($this->generateChartData('pending_expenses'))
                ->icon('heroicon-o-clock')
                ->color('warning'),

            // Approved expenses stat
            Stat::make('Approved Expenses', $approvedExpenses)
                ->description('Approved expenses')
                ->chart($this->generateChartData('approved_expenses'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }

    protected function generateChartData(string $statType): array
    {
        $data = [];
        $currentDate = now();

        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = $currentDate->copy()->startOfMonth()->subMonths($i);
            $endOfMonth = $startOfMonth->copy()->endOfMonth();

            $count = match ($statType) {
                'total_expenses' => $this->visit->expenses()->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'pending_expenses' => $this->visit->expenses()->where('status', 'pending')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'approved_expenses' => $this->visit->expenses()->where('status', 'approved')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                default => 0,
            };

            $data[] = $count;
        }

        return $data;
    }
}
