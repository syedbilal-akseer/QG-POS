<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Traits\OracleNlsSession;
use Illuminate\Support\Facades\DB;

/**
 * Mobile-receipt adoption report powered by APPS.QG_RECEIPTS_PERCENTAGE.
 *
 *   GET  /admin/reports/receipts-percentage         → HTML preview
 *   GET  /admin/reports/receipts-percentage/export  → CSV download
 *
 * Structurally identical to SalesOrderPercentageController — different
 * source view and metric name, same pivot + CSV shape.
 */
class ReceiptsPercentageController extends Controller
{
    use OracleNlsSession;

    public function index()
    {
        $this->setOracleNlsSession();

        $rows = DB::connection('oracle')
            ->table('APPS.QG_RECEIPTS_PERCENTAGE')
            ->select('receipt_month', 'salesperson', 'total_receipts', 'mobile_receipts', 'mobile_pct')
            ->get();

        $months = $rows->pluck('receipt_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $sortedMonths = $months->keys()->values()->all();

        $matrix    = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $matrix[$r->salesperson][$r->receipt_month] = [
                'pct'    => round((float) $r->mobile_pct, 2) . '%',
                'mobile' => (int) $r->mobile_receipts,
                'total'  => (int) $r->total_receipts,
            ];
            $colTotals[$r->receipt_month]['mobile'] += (int) $r->mobile_receipts;
            $colTotals[$r->receipt_month]['total']  += (int) $r->total_receipts;
        }
        ksort($matrix);

        return view('admin.reports.receipts-percentage', [
            'months'    => $sortedMonths,
            'matrix'    => $matrix,
            'colTotals' => $colTotals,
        ]);
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->setOracleNlsSession();

        $rows = DB::connection('oracle')
            ->table('APPS.QG_RECEIPTS_PERCENTAGE')
            ->select('receipt_month', 'salesperson', 'total_receipts', 'mobile_receipts', 'mobile_pct')
            ->get();

        $months = $rows->pluck('receipt_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $sortedMonths = $months->keys()->values()->all();

        $matrix    = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $matrix[$r->salesperson][$r->receipt_month] = [
                'pct'    => round((float) $r->mobile_pct, 2) . '%',
                'mobile' => (int) $r->mobile_receipts,
                'total'  => (int) $r->total_receipts,
            ];
            $colTotals[$r->receipt_month]['mobile'] += (int) $r->mobile_receipts;
            $colTotals[$r->receipt_month]['total']  += (int) $r->total_receipts;
        }
        ksort($matrix);

        $filename = 'receipts-percentage-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($sortedMonths, $matrix, $colTotals) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge(
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
                fputcsv($out, $row);

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
            fputcsv($out, $totalRow);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
