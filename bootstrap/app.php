<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'checkRole' => \App\Http\Middleware\CheckRole::class
        ]);

        $middleware->web(append: [
            \RalphJSmit\Livewire\Urls\Middleware\LivewireUrlsMiddleware::class
        ]);

        // Exclude specific routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'app/admin/price-lists/enter-new-prices',
            'admin/price-lists/enter-new-prices',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        // $schedule->command('sync:oracle-users-clear')->dailyAt('10:00');
        $schedule->command('sync:oracle-customers-clear')->everyTwoHours();
        $schedule->command('sync:oracle-products-clear')->dailyAt('10:00');
        $schedule->command('sync:oracle-items-price-clear')->dailyAt('10:00');
        $schedule->command('orders:sync-oracle')->dailyAt('10:00');
        $schedule->command('sync:oracle-warehouses')->dailyAt('10:00');
        $schedule->command('sync:oracle-order-types')->dailyAt('10:00');
        $schedule->command('sync:oracle-banks-clear')->dailyAt('10:00');
    })->create();
