<?php

namespace App\Http\Controllers\Reports;

use App\Exports\SalesOrderPercentageExport;
use App\Http\Controllers\Controller;
use App\Traits\OracleNlsSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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
            ->mapWithKeys(fn ($m) => [$m => Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $allMonths = $months->keys()->values()->all();
        $currentMonth = Carbon::now()->startOfMonth();
        $completedMonths = array_values(array_filter($allMonths, fn ($m) => Carbon::parse('01-' . $m)->startOfMonth()->lt($currentMonth)));
        $sortedMonths = array_slice($completedMonths, -2, 2);
        if (count($sortedMonths) === 0) {
            $sortedMonths = $allMonths;
        }

        $rows = $rows->filter(fn ($row) => in_array($row->order_month, $sortedMonths, true));

        $matrix = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $pct = round((float) $r->mobile_pct, 2);
            $matrix[$r->salesperson][$r->order_month] = [
                'pct'       => $pct,
                'pct_label' => $pct . '%',
                'mobile'    => (int) $r->mobile_orders,
                'total'     => (int) $r->total_orders,
            ];
            $colTotals[$r->order_month]['mobile'] += (int) $r->mobile_orders;
            $colTotals[$r->order_month]['total']  += (int) $r->total_orders;
        }
        ksort($matrix);

        return view('admin.reports.sales-order-percentage', [
            'months'        => $sortedMonths,
            'matrix'        => $matrix,
            'colTotals'     => $colTotals,
            'growthRows'    => $this->buildGrowthRows($matrix, $sortedMonths),
            'overallGrowth' => $this->buildOverallGrowth($colTotals, $sortedMonths),
        ]);
    }

    public function export(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->setOracleNlsSession();

        $rows = DB::connection('oracle')
            ->table('APPS.QG_SALES_ORDER_PERCENTAGE')
            ->select('order_month', 'salesperson', 'total_orders', 'mobile_orders', 'mobile_pct')
            ->get();

        $months = $rows->pluck('order_month')->unique()
            ->mapWithKeys(fn ($m) => [$m => Carbon::parse('01-' . $m)->format('Y-m')])
            ->sort();
        $allMonths = $months->keys()->values()->all();
        $currentMonth = Carbon::now()->startOfMonth();
        $completedMonths = array_values(array_filter($allMonths, fn ($m) => Carbon::parse('01-' . $m)->startOfMonth()->lt($currentMonth)));
        $sortedMonths = array_slice($completedMonths, -2, 2);
        if (count($sortedMonths) === 0) {
            $sortedMonths = $allMonths;
        }

        $rows = $rows->filter(fn ($row) => in_array($row->order_month, $sortedMonths, true));

        $matrix = [];
        $colTotals = array_fill_keys($sortedMonths, ['mobile' => 0, 'total' => 0]);
        foreach ($rows as $r) {
            $pct = round((float) $r->mobile_pct, 2);
            $matrix[$r->salesperson][$r->order_month] = [
                'pct'       => $pct,
                'pct_label' => $pct . '%',
                'mobile'    => (int) $r->mobile_orders,
                'total'     => (int) $r->total_orders,
            ];
            $colTotals[$r->order_month]['mobile'] += (int) $r->mobile_orders;
            $colTotals[$r->order_month]['total']  += (int) $r->total_orders;
        }
        ksort($matrix);

        $growthRows = $this->buildGrowthRows($matrix, $sortedMonths);
        $overallGrowth = $this->buildOverallGrowth($colTotals, $sortedMonths);

        $filename = 'sales-order-percentage-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new SalesOrderPercentageExport($sortedMonths, $matrix, $colTotals, $growthRows, $overallGrowth),
            $filename
        );
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
