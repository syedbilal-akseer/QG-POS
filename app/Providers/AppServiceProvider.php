<?php

namespace App\Providers;

use App\Models\DayTourPlan;
use App\Models\MonthlyVisitReport;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\View\Components\Modal;
use Filament\Notifications\Livewire\DatabaseNotifications;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        Model::preventAccessingMissingAttributes();
        // Model::preventLazyLoading();

        FilamentColor::register([
            'danger' => Color::Rose,
            'gray' => Color::Zinc,
            'info' => Color::Blue,
            'primary' => Color::Orange,
            'success' => Color::Green,
            'warning' => Color::Amber,
            'indigo' => Color::Indigo,
            'teal' => Color::Teal,
            'lime' => Color::Lime,
            'emerald' => Color::Emerald,
            'violet' => Color::Violet,
            'fuchsia' => Color::Fuchsia,

        ]);

        // DatabaseNotifications::trigger('notifications-trigger');

        DatabaseNotifications::pollingInterval('15s');

        Modal::closedByClickingAway(false);

        Route::bind('plan', function ($value) {
            // Attempt to find a MonthlyVisitReport
            if ($monthlyVisitReport = MonthlyVisitReport::find($value)) {
                return $monthlyVisitReport;
            }

            // Attempt to find a DayTourPlan
            if ($dayTourPlan = DayTourPlan::find($value)) {
                return $dayTourPlan;
            }

            // If neither exists, throw a 404
            abort(404, 'Plan not found.');
        });

    }
}
