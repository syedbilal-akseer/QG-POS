<?php

namespace App\Console\Commands;

use App\Traits\OracleNlsSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a CSV report of APPS.QG_SALES_ORDER_PERCENTAGE to
 *   storage/app/reports/sales-order-percentage-YYYY-MM-DD.csv
 *
 * Pivoted layout: rows = salesperson, columns = order months (chronological),
 * cells = "mobile% (mobile/total)". A trailing "ALL SALESPERSONS" row shows
 * the company-wide totals per column and a grand total.
 *
 *   php artisan report:sales-order-percentage
 *   php artisan report:sales-order-percentage --path=custom/path.csv
 */
class ExportSalesOrderPercentage extends Command
{
    use OracleNlsSession;

    protected $signature = 'report:sales-order-percentage
                            {--path= : Optional relative path under storage/app (default: reports/sales-order-percentage-YYYY-MM-DD.csv)}';

    protected $description = 'Generate a CSV report of month-wise mobile-order adoption per salesperson from APPS.QG_SALES_ORDER_PERCENTAGE.';

    public function handle(): int
    {
        $this->setOracleNlsSession();

        $this->info('Fetching APPS.QG_SALES_ORDER_PERCENTAGE …');
        $rows = DB::connection('oracle')
            ->table('APPS.QG_SALES_ORDER_PERCENTAGE')
            ->select('order_month', 'salesperson', 'total_orders', 'mobile_orders', 'mobile_pct')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('View returned zero rows — nothing to write.');
            return self::SUCCESS;
        }

        // Order-month sort. The raw value is "MAY-2026" (Oracle's default
        // MON-YYYY). Prepend "01-" so Carbon parses it as a real date and
        // string sort doesn't put APR before AUG alphabetically.
        $months = $rows->pluck('order_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $sortedMonths = $months->keys()->values()->all();

        // Pivot rows: salesperson code → per-month cells. Also accumulate
        // per-column totals for the final grand-total row.
        $matrix    = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $matrix[$r->salesperson][$r->order_month] = [
                'pct'    => $r->mobile_pct,
                'mobile' => (int) $r->mobile_orders,
                'total'  => (int) $r->total_orders,
            ];
            $colTotals[$r->order_month]['mobile'] += (int) $r->mobile_orders;
            $colTotals[$r->order_month]['total']  += (int) $r->total_orders;
        }
        ksort($matrix);

        // Target path. Default lives under storage/app/reports so the file
        // is visible via the local disk and behind auth if we ever wire a
        // download endpoint.
        $relPath = $this->option('path')
            ?: 'reports/sales-order-percentage-' . now()->format('Y-m-d') . '.csv';

        Storage::disk('local')->makeDirectory(dirname($relPath));
        $absPath = Storage::disk('local')->path($relPath);

        $fh = fopen($absPath, 'w');
        if ($fh === false) {
            $this->error("Could not open $absPath for writing.");
            return self::FAILURE;
        }

        // Header row.
        fputcsv($fh, array_merge(
            ['Salesperson'],
            $sortedMonths,
            ['Total Orders', 'Mobile Orders', 'Oracle Orders', 'Overall Mobile %'],
        ));

        $grandMob = 0;
        $grandTot = 0;

        foreach ($matrix as $sp => $cells) {
            $spMob = 0;
            $spTot = 0;
            $row   = [$sp];
            foreach ($sortedMonths as $m) {
                $c = $cells[$m] ?? null;
                if ($c) {
                    // Oracle orders = total - mobile; percentage stays mobile/total as before.
                    $row[] = sprintf('%s (%d/%d, oracle:%d)', $c['pct'], $c['mobile'], $c['total'], $c['total'] - $c['mobile']);
                    $spMob += $c['mobile'];
                    $spTot += $c['total'];
                } else {
                    $row[] = '';
                }
            }
            $row[] = $spTot;
            $row[] = $spMob;
            $row[] = $spTot - $spMob;
            $row[] = $spTot > 0 ? round(($spMob / $spTot) * 100, 2) . '%' : '0%';
            fputcsv($fh, $row);

            $grandMob += $spMob;
            $grandTot += $spTot;
        }

        // ALL-SALESPERSONS grand-total row.
        $totalRow = ['ALL SALESPERSONS'];
        foreach ($sortedMonths as $m) {
            $mob = $colTotals[$m]['mobile'];
            $tot = $colTotals[$m]['total'];
            $pct = $tot > 0 ? round(($mob / $tot) * 100, 2) . '%' : '0%';
            $totalRow[] = sprintf('%s (%d/%d, oracle:%d)', $pct, $mob, $tot, $tot - $mob);
        }
        $totalRow[] = $grandTot;
        $totalRow[] = $grandMob;
        $totalRow[] = $grandTot - $grandMob;
        $totalRow[] = $grandTot > 0 ? round(($grandMob / $grandTot) * 100, 2) . '%' : '0%';
        fputcsv($fh, $totalRow);

        fclose($fh);

        $this->info(sprintf(
            'CSV written: %s  (%d salesperson rows, %d months)',
            $absPath,
            count($matrix),
            count($sortedMonths),
        ));

        return self::SUCCESS;
    }
}
