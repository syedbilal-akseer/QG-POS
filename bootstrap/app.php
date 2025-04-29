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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'checkRole' => \App\Http\Middleware\CheckRole::class
        ]);

        $middleware->web(append: [
            \RalphJSmit\Livewire\Urls\Middleware\LivewireUrlsMiddleware::class
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
        $schedule->command('sync:oracle-customers')->daily();
        $schedule->command('sync:oracle-products')->daily();
        $schedule->command('sync:oracle-items-price')->daily();
        $schedule->command('orders:sync-oracle')->daily();
    })->create();
