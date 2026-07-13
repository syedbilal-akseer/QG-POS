<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Wrapper commands that automatically clear cache after sync
Artisan::command('sync:oracle-customers-clear', function () {
    $this->info('Syncing Oracle customers...');
    Artisan::call('sync:oracle-customers');
    $this->info('Clearing API cache...');
    Artisan::call('cache:clear-api');
    $this->info('✓ Customers synced and cache cleared!');
})->purpose('Sync Oracle customers and clear cache');

Artisan::command('sync:oracle-products-clear', function () {
    $this->info('Syncing Oracle products...');
    Artisan::call('sync:oracle-products');
    $this->info('Clearing API cache...');
    Artisan::call('cache:clear-api');
    $this->info('✓ Products synced and cache cleared!');
})->purpose('Sync Oracle products and clear cache');

Artisan::command('sync:oracle-items-price-clear', function () {
    $this->info('Syncing Oracle item prices...');
    Artisan::call('sync:oracle-items-price');
    $this->info('Clearing API cache...');
    Artisan::call('cache:clear-api');
    $this->info('✓ Item prices synced and cache cleared!');
})->purpose('Sync Oracle item prices and clear cache');

Artisan::command('sync:oracle-banks-clear', function () {
    $this->info('Syncing Oracle banks...');
    Artisan::call('sync:oracle-banks');
    $this->info('Clearing API cache...');
    Artisan::call('cache:clear-api');
    $this->info('✓ Banks synced and cache cleared!');
})->purpose('Sync Oracle banks and clear cache');

Artisan::command('sync:oracle-users-clear', function () {
    $this->info('Syncing Oracle users...');
    Artisan::call('sync:oracle-users');
    $this->info('Clearing API cache...');
    Artisan::call('cache:clear-api');
    $this->info('✓ Users synced and cache cleared!');
})->purpose('Sync Oracle users and clear cache');

Artisan::command('sync:oracle-transporters-clear', function () {
    $this->info('Syncing Oracle transporters...');
    Artisan::call('sync:oracle-transporters');
    $this->info('Clearing API cache...');
    Artisan::call('cache:clear-api');
    $this->info('✓ Transporters synced and cache cleared!');
})->purpose('Sync Oracle transporters and clear cache');

Artisan::command('sync:oracle-all', function () {
    $this->info('Starting full Oracle sync...');

    $this->info('1/8 Syncing customers...');
    Artisan::call('sync:oracle-customers');

    $this->info('2/8 Syncing products...');
    Artisan::call('sync:oracle-products');

    $this->info('3/8 Syncing item prices...');
    Artisan::call('sync:oracle-items-price');

    $this->info('4/8 Syncing banks...');
    Artisan::call('sync:oracle-banks');

    $this->info('5/8 Syncing users...');
    Artisan::call('sync:oracle-users');

    $this->info('6/8 Syncing order types...');
    Artisan::call('sync:oracle-order-types');

    $this->info('7/8 Syncing warehouses...');
    Artisan::call('sync:oracle-warehouses');

    $this->info('8/8 Syncing transporters...');
    Artisan::call('sync:oracle-transporters');

    $this->info('Clearing all API caches...');
    Artisan::call('cache:clear-api');

    $this->info('✓ Full Oracle sync completed and cache cleared!');
})->purpose('Sync all Oracle data and clear cache');

/**
 * Daily wipe of transactional data created by the test accounts
 * (app_dev@qgpos.com, qa@qgpos.com). Runs at 03:00 server time. The user
 * rows themselves are preserved — only their authored records are removed.
 */
Schedule::command('cleanup:test-user-data')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

/**
 * To enable scheduled tasks, add this cron entry to your server:
 * * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
 */
