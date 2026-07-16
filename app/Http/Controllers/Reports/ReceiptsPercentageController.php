<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Traits\OracleNlsSession;
use Carbon\Carbon;
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
            ->mapWithKeys(fn ($m) => [$m => Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $allMonths = $months->keys()->values()->all();
        $currentMonth = Carbon::now()->startOfMonth();
        $completedMonths = array_values(array_filter($allMonths, fn ($m) => Carbon::parse('01-' . $m)->startOfMonth()->lt($currentMonth)));
        $sortedMonths = array_slice($completedMonths, -2, 2);
        if (count($sortedMonths) === 0) {
            $sortedMonths = $allMonths;
        }

        $rows = $rows->filter(fn ($row) => in_array($row->receipt_month, $sortedMonths, true));

        $matrix    = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $pct = round((float) $r->mobile_pct, 2);
            $matrix[$r->salesperson][$r->receipt_month] = [
                'pct'       => $pct,
                'pct_label' => $pct . '%',
                'mobile'    => (int) $r->mobile_receipts,
                'total'     => (int) $r->total_receipts,
            ];
            $colTotals[$r->receipt_month]['mobile'] += (int) $r->mobile_receipts;
            $colTotals[$r->receipt_month]['total']  += (int) $r->total_receipts;
        }
        ksort($matrix);

        return view('admin.reports.receipts-percentage', [
            'months'        => $sortedMonths,
            'matrix'        => $matrix,
            'colTotals'     => $colTotals,
            'growthRows'    => $this->buildGrowthRows($matrix, $sortedMonths),
            'overallGrowth' => $this->buildOverallGrowth($colTotals, $sortedMonths),
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
            ->mapWithKeys(fn ($m) => [$m => Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $allMonths = $months->keys()->values()->all();
        $currentMonth = Carbon::now()->startOfMonth();
        $completedMonths = array_values(array_filter($allMonths, fn ($m) => Carbon::parse('01-' . $m)->startOfMonth()->lt($currentMonth)));
        $sortedMonths = array_slice($completedMonths, -2, 2);
        if (count($sortedMonths) === 0) {
            $sortedMonths = $allMonths;
        }

        $rows = $rows->filter(fn ($row) => in_array($row->receipt_month, $sortedMonths, true));

        $matrix    = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $pct = round((float) $r->mobile_pct, 2);
            $matrix[$r->salesperson][$r->receipt_month] = [
                'pct'       => $pct,
                'pct_label' => $pct . '%',
                'mobile'    => (int) $r->mobile_receipts,
                'total'     => (int) $r->total_receipts,
            ];
            $colTotals[$r->receipt_month]['mobile'] += (int) $r->mobile_receipts;
            $colTotals[$r->receipt_month]['total']  += (int) $r->total_receipts;
        }
        ksort($matrix);

        $filename = 'receipts-percentage-' . now()->format('Y-m-d') . '.csv';
        $growthRows = $this->buildGrowthRows($matrix, $sortedMonths);
        $overallGrowth = $this->buildOverallGrowth($colTotals, $sortedMonths);

        return response()->streamDownload(function () use ($sortedMonths, $matrix, $colTotals, $growthRows, $overallGrowth) {
            $out = fopen('php://output', 'w');

            $headers = ['Salesperson'];
            foreach ($sortedMonths as $m) {
                $headers[] = $m . ' Total';
                $headers[] = $m . ' Mobile';
                $headers[] = $m . ' Mobile %';
            }
            $headers[] = 'Growth';

            fputcsv($out, $headers);

            $grandMob = 0;
            $grandTot = 0;

            foreach ($matrix as $sp => $cells) {
                $spMob = 0;
                $spTot = 0;
                $row   = [$sp];
                foreach ($sortedMonths as $m) {
                    $c = $cells[$m] ?? null;
                    if ($c) {
                        $row[] = $c['total'];
                        $row[] = $c['mobile'];
                        $row[] = $c['pct_label'];
                        $spMob += $c['mobile'];
                        $spTot += $c['total'];
                    } else {
                        $row[] = '';
                        $row[] = '';
                        $row[] = '';
                    }
                }
                $row[] = $growthRows[$sp] ?? '—';
                fputcsv($out, $row);

                $grandMob += $spMob;
                $grandTot += $spTot;
            }

            $totalRow = ['ALL SALESPERSONS'];
            foreach ($sortedMonths as $m) {
                $mob = $colTotals[$m]['mobile'];
                $tot = $colTotals[$m]['total'];
                $pct = $tot > 0 ? round(($mob / $tot) * 100, 2) . '%' : '0%';
                $totalRow[] = $tot;
                $totalRow[] = $mob;
                $totalRow[] = $pct;
            }
            $totalRow[] = $overallGrowth;
            fputcsv($out, $totalRow);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildGrowthRows(array $matrix, array $months): array
    {
        if (count($months) !== 2) {
            return [];
        }

        [$previousMonth, $currentMonth] = $months;
        $growthRows = [];

        foreach ($matrix as $salesperson => $cells) {
            $previousPct = $cells[$previousMonth]['pct'] ?? null;
            $currentPct = $cells[$currentMonth]['pct'] ?? null;
            $growthRows[$salesperson] = $this->formatGrowth($previousPct, $currentPct);
        }

        return $growthRows;
    }

    private function buildOverallGrowth(array $colTotals, array $months): string
    {
        if (count($months) !== 2) {
            return '—';
        }

        return $this->formatGrowth(
            $this->percentageFromTotals($colTotals[$months[0]] ?? null),
            $this->percentageFromTotals($colTotals[$months[1]] ?? null)
        );
    }

    private function percentageFromTotals(?array $totals): ?float
    {
        if (!$totals || $totals['total'] === 0) {
            return null;
        }

        return round(($totals['mobile'] / $totals['total']) * 100, 2);
    }

    private function formatGrowth(?float $previous, ?float $current): string
    {
        if ($previous === null || $current === null) {
            return '—';
        }

        $diff = round($current - $previous, 2);
        if ($diff === 0.0) {
            return '0%';
        }

        return ($diff > 0 ? '↑ ' : '↓ ') . abs($diff) . '%';
    }
}
