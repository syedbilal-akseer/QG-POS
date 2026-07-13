<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Traits\OracleNlsSession;
use Illuminate\Support\Facades\DB;

/**
 * Mobile-order adoption report powered by APPS.QG_SALES_ORDER_PERCENTAGE.
 *
 *   GET  /admin/reports/sales-order-percentage         → HTML preview
 *   GET  /admin/reports/sales-order-percentage/export  → CSV download
 *
 * The preview page renders the same pivoted matrix as the Excel so the user
 * can spot-check numbers before downloading.
 */
class SalesOrderPercentageController extends Controller
{
    use OracleNlsSession;

    public function index()
    {
        $this->setOracleNlsSession();

        $rows = DB::connection('oracle')
            ->table('APPS.QG_SALES_ORDER_PERCENTAGE')
            ->select('order_month', 'salesperson', 'total_orders', 'mobile_orders', 'mobile_pct')
            ->get();

        $months = $rows->pluck('order_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $sortedMonths = $months->keys()->values()->all();

        $matrix = [];
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

        return view('admin.reports.sales-order-percentage', [
            'months'    => $sortedMonths,
            'matrix'    => $matrix,
            'colTotals' => $colTotals,
        ]);
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->setOracleNlsSession();

        $rows = DB::connection('oracle')
            ->table('APPS.QG_SALES_ORDER_PERCENTAGE')
            ->select('order_month', 'salesperson', 'total_orders', 'mobile_orders', 'mobile_pct')
            ->get();

        // Chronological month sort ("MAY-2026" → real date → Y-m key).
        $months = $rows->pluck('order_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $sortedMonths = $months->keys()->values()->all();

        // Pivot into salesperson × month, plus column running totals.
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

        $filename = 'sales-order-percentage-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($sortedMonths, $matrix, $colTotals) {
            $out = fopen('php://output', 'w');

            // Header row: Salesperson | <months...> | Total Orders | Mobile Orders | Overall Mobile %
            fputcsv($out, array_merge(
                ['Salesperson'],
                $sortedMonths,
                ['Total Orders', 'Mobile Orders', 'Overall Mobile %'],
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

            // Grand-total row.
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
