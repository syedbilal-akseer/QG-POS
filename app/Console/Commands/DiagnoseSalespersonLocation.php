<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Diagnose why a salesperson ends up in KHI vs LHR on the dashboard.
 * Prints their user_organizations rows, customer-distribution counts, and the
 * final classifier verdict.
 *
 *   php artisan diagnose:salesperson "Umair Quadri"
 */
class DiagnoseSalespersonLocation extends Command
{
    protected $signature   = 'diagnose:salesperson {name : Full or partial name (LIKE %name%)}';
    protected $description = 'Show why a salesperson is classified as KHI / LHR on the dashboard';

    public function handle(): int
    {
        // Always clear any cached classifier output first so we see fresh data.
        Cache::forget('dashboard_salesperson_locations_v1');
        Cache::forget('dashboard_salesperson_locations_v2');

        $name = $this->argument('name');
        $khi  = [102, 103, 104, 105, 106];
        $lhr  = [108, 109];

        $user = User::where('name', 'like', "%{$name}%")
            ->orWhere('oracle_user_name', 'like', "%{$name}%")
            ->first();

        if (!$user) {
            $this->error("No user matched '{$name}'.");
            return self::FAILURE;
        }

        $this->info("User: {$user->name}  (id={$user->id}, role={$user->role})");
        $this->line('');

        // 1) user_organizations rows
        $orgs = DB::table('user_organizations')->where('user_id', $user->id)->get();
        $this->line("user_organizations rows ({$orgs->count()}):");
        foreach ($orgs as $r) {
            $ouInt  = (int) $r->oracle_ou_id;
            $region = in_array($ouInt, $khi, true) ? 'KHI'
                    : (in_array($ouInt, $lhr, true) ? 'LHR' : 'OTHER');
            $active = $r->is_active ? 'ACTIVE' : 'inactive';
            $this->line("  oracle_ou_id={$r->oracle_ou_id}  →  {$region}  ({$active})");
        }
        $this->line('');

        // 2) Active-only counts by region
        $activeKhi = DB::table('user_organizations')->where('user_id', $user->id)->where('is_active', true)->whereIn('oracle_ou_id', $khi)->count();
        $activeLhr = DB::table('user_organizations')->where('user_id', $user->id)->where('is_active', true)->whereIn('oracle_ou_id', $lhr)->count();
        $this->line("Active org rows:  KHI={$activeKhi}   LHR={$activeLhr}");

        // 3) Customer-distribution fallback signal
        $khiC = DB::table('customers')->where('salesperson', $user->name)->whereIn('ou_id', $khi)->count();
        $lhrC = DB::table('customers')->where('salesperson', $user->name)->whereIn('ou_id', $lhr)->count();
        $this->line("Customer distribution (fallback): KHI={$khiC}   LHR={$lhrC}");
        $this->line('');

        // 4) Run the actual classifier
        $controller = new \App\Http\Controllers\AppController();
        $ref = new \ReflectionMethod($controller, 'getSalespersonLocations');
        $ref->setAccessible(true);
        $map = $ref->invoke($controller, $khi, $lhr);
        $verdict = $map[$user->id] ?? 'UNCLASSIFIED';
        $this->info("==> Dashboard verdict: {$verdict}");

        // 5) Reasoning
        $this->line('');
        if ($activeKhi > 0 || $activeLhr > 0) {
            if ($activeKhi > $activeLhr) {
                $this->line("Reason: user_organizations majority → KHI");
            } elseif ($activeLhr > $activeKhi) {
                $this->line("Reason: user_organizations majority → LHR");
            } else {
                $this->line("Reason: user_organizations is tied — fell back to customer distribution");
            }
        } else {
            $this->line("Reason: no active user_organizations row — fell back to customer distribution");
        }

        return self::SUCCESS;
    }
}
