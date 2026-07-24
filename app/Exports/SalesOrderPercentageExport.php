<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Mirrors the HTML preview's two-row <thead>: row 1 shows the month name
 * merged across its 4 sub-columns (like colspan="4"), row 2 repeats the
 * Total/Mobile/Oracle/Mobile % labels. Salesperson/Growth merge vertically
 * across both header rows (like rowspan="2"). Using a real .xlsx (rather
 * than CSV) also avoids the UTF-8 mojibake Excel produces for the ↑/↓
 * growth-arrow characters when opening a BOM-less CSV.
 */
class SalesOrderPercentageExport implements FromArray, WithEvents
{
    public function __construct(
        protected array $sortedMonths,
        protected array $matrix,
        protected array $colTotals,
        protected array $growthRows,
        protected string $overallGrowth,
    ) {
    }

    public function array(): array
    {
        $rows = [];

        $headerRow1 = ['Salesperson'];
        $headerRow2 = [''];
        foreach ($this->sortedMonths as $m) {
            $headerRow1[] = $m;
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow2[] = 'Total Orders';
            $headerRow2[] = 'Mobile Orders';
            $headerRow2[] = 'Oracle Orders';
            $headerRow2[] = 'Mobile %';
        }
        $headerRow1[] = 'Growth';
        $headerRow2[] = '';
        $rows[] = $headerRow1;
        $rows[] = $headerRow2;

        foreach ($this->matrix as $sp => $cells) {
            $row = [$sp];
            foreach ($this->sortedMonths as $m) {
                $c = $cells[$m] ?? null;
                if ($c) {
                    $row[] = $c['total'];
                    $row[] = $c['mobile'];
                    $row[] = $c['total'] - $c['mobile'];
                    $row[] = $c['pct_label'];
                } else {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                }
            }
            $row[] = $this->growthRows[$sp] ?? '—';
            $rows[] = $row;
        }

        $totalRow = ['ALL SALESPERSONS'];
        foreach ($this->sortedMonths as $m) {
            $mob = $this->colTotals[$m]['mobile'];
            $tot = $this->colTotals[$m]['total'];
            $pct = $tot > 0 ? round(($mob / $tot) * 100, 2) . '%' : '0%';
            $totalRow[] = $tot;
            $totalRow[] = $mob;
            $totalRow[] = $tot - $mob;
            $totalRow[] = $pct;
        }
        $totalRow[] = $this->overallGrowth;
        $rows[] = $totalRow;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $monthCount = count($this->sortedMonths);
                $lastCol = 2 + $monthCount * 4; // Salesperson + 4 cols/month + Growth
                $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

                // Salesperson / Growth span both header rows (like rowspan="2").
                $sheet->mergeCells("A1:A2");
                $sheet->mergeCells("{$lastColLetter}1:{$lastColLetter}2");

                // Each month's 4 sub-columns merge on row 1 (like colspan="4").
                $col = 2;
                foreach ($this->sortedMonths as $m) {
                    $startLetter = Coordinate::stringFromColumnIndex($col);
                    $endLetter = Coordinate::stringFromColumnIndex($col + 3);
                    $sheet->mergeCells("{$startLetter}1:{$endLetter}1");
                    $col += 4;
                }

                $sheet->getStyle("A1:{$lastColLetter}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                for ($c = 1; $c <= $lastCol; $c++) {
                    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
                }
            },
        ];
    }
}
