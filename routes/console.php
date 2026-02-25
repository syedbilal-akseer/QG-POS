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

Artisan::command('sync:oracle-all', function () {
    $this->info('Starting full Oracle sync...');

    $this->info('1/7 Syncing customers...');
    Artisan::call('sync:oracle-customers');

    $this->info('2/7 Syncing products...');
    Artisan::call('sync:oracle-products');

    $this->info('3/7 Syncing item prices...');
    Artisan::call('sync:oracle-items-price');

    $this->info('4/7 Syncing banks...');
    Artisan::call('sync:oracle-banks');

    $this->info('5/7 Syncing users...');
    Artisan::call('sync:oracle-users');

    $this->info('6/7 Syncing order types...');
    Artisan::call('sync:oracle-order-types');

    $this->info('7/7 Syncing warehouses...');
    Artisan::call('sync:oracle-warehouses');

    $this->info('Clearing all API caches...');
    Artisan::call('cache:clear-api');

    $this->info('✓ Full Oracle sync completed and cache cleared!');
})->purpose('Sync all Oracle data and clear cache');

/**
 * To enable scheduled tasks, add this cron entry to your server:
 * * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
 */
