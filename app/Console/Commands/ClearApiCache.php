<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class ClearApiCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all API caches after Oracle data sync';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing all API caches...');

        try {
            // Clear all cache
            Cache::flush();
            $this->info('✓ Application cache cleared');

            // Clear config cache
            Artisan::call('config:clear');
            $this->info('✓ Configuration cache cleared');

            // Clear route cache
            Artisan::call('route:clear');
            $this->info('✓ Route cache cleared');

            // Clear view cache
            Artisan::call('view:clear');
            $this->info('✓ View cache cleared');

            $this->newLine();
            $this->info('🎉 All caches cleared successfully!');
            $this->info('APIs will now return fresh data from the database.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to clear caches: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}