<?php

namespace App\Providers;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentColor;
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
    }
}
