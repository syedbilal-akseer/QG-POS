<?php

namespace App\Console\Commands;

use App\Traits\OracleNlsSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a CSV report of APPS.QG_RECEIPTS_PERCENTAGE to
 *   storage/app/reports/receipts-percentage-YYYY-MM-DD.csv
 *
 * Pivoted layout: rows = salesperson, columns = receipt months (chronological),
 * cells = "mobile% (mobile/total)". A trailing "ALL SALESPERSONS" row shows
 * per-column totals and a grand total.
 *
 *   php artisan report:receipts-percentage
 *   php artisan report:receipts-percentage --path=custom/path.csv
 */
class ExportReceiptsPercentage extends Command
{
    use OracleNlsSession;

    protected $signature = 'report:receipts-percentage
                            {--path= : Optional relative path under storage/app (default: reports/receipts-percentage-YYYY-MM-DD.csv)}';

    protected $description = 'Generate a CSV report of month-wise mobile-receipt adoption per salesperson from APPS.QG_RECEIPTS_PERCENTAGE.';

    public function handle(): int
    {
        $this->setOracleNlsSession();

        $this->info('Fetching APPS.QG_RECEIPTS_PERCENTAGE …');
        $rows = DB::connection('oracle')
            ->table('APPS.QG_RECEIPTS_PERCENTAGE')
            ->select('receipt_month', 'salesperson', 'total_receipts', 'mobile_receipts', 'mobile_pct')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('View returned zero rows — nothing to write.');
            return self::SUCCESS;
        }

        // Chronological month sort ("MAY-2026" → real date → Y-m key).
        $months = $rows->pluck('receipt_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $sortedMonths = $months->keys()->values()->all();

        $matrix    = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            // mobile_pct on this view is a raw number (0, 33.33) — normalise
            // to a "%"-suffixed string so the CSV matches the orders report.
            $pctRaw = (float) $r->mobile_pct;
            $matrix[$r->salesperson][$r->receipt_month] = [
                'pct'    => round($pctRaw, 2) . '%',
                'mobile' => (int) $r->mobile_receipts,
                'total'  => (int) $r->total_receipts,
            ];
            $colTotals[$r->receipt_month]['mobile'] += (int) $r->mobile_receipts;
            $colTotals[$r->receipt_month]['total']  += (int) $r->total_receipts;
        }
        ksort($matrix);

        $relPath = $this->option('path')
            ?: 'reports/receipts-percentage-' . now()->format('Y-m-d') . '.csv';

        Storage::disk('local')->makeDirectory(dirname($relPath));
        $absPath = Storage::disk('local')->path($relPath);

        $fh = fopen($absPath, 'w');
        if ($fh === false) {
            $this->error("Could not open $absPath for writing.");
            return self::FAILURE;
        }

        fputcsv($fh, array_merge(
            ['Salesperson'],
            $sortedMonths,
            ['Total Receipts', 'Mobile Receipts', 'Overall Mobile %'],
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
                    $row[] = sprintf('%s (%d/%d)', $c['pct'], $c['mobile'], $c['total']);
                    $spMob += $c['mobile'];
                    $spTot += $c['total'];
                } else {
                    $row[] = '';
                }
            }
            $row[] = $spTot;
            $row[] = $spMob;
            $row[] = $spTot > 0 ? round(($spMob / $spTot) * 100, 2) . '%' : '0%';
            fputcsv($fh, $row);

            $grandMob += $spMob;
            $grandTot += $spTot;
        }

        $totalRow = ['ALL SALESPERSONS'];
        foreach ($sortedMonths as $m) {
            $mob = $colTotals[$m]['mobile'];
            $tot = $colTotals[$m]['total'];
            $pct = $tot > 0 ? round(($mob / $tot) * 100, 2) . '%' : '0%';
            $totalRow[] = sprintf('%s (%d/%d)', $pct, $mob, $tot);
        }
        $totalRow[] = $grandTot;
        $totalRow[] = $grandMob;
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
