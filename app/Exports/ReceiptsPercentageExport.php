<?php

namespace App\Exports;

use App\Services\SalespersonSegmentResolver;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Structurally identical to SalesOrderPercentageExport — different labels
 * (Receipt vs Orders), same pivoted two-row-header layout.
 */
class ReceiptsPercentageExport implements FromArray, WithEvents
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

        $headerRow1 = ['Segment', 'Salesperson'];
        $headerRow2 = ['', ''];
        foreach ($this->sortedMonths as $m) {
            $headerRow1[] = $m;
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow2[] = 'Total Receipt';
            $headerRow2[] = 'Mobile Receipt';
            $headerRow2[] = 'Oracle Receipt';
            $headerRow2[] = 'Mobile %';
        }
        $headerRow1[] = 'Growth';
        $headerRow2[] = '';
        $rows[] = $headerRow1;
        $rows[] = $headerRow2;

        foreach ($this->matrix as $sp => $cells) {
            $row = [SalespersonSegmentResolver::forSalesperson($sp), $sp];
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

        $totalRow = ['', 'ALL SALESPERSONS'];
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
                $lastCol = 3 + $monthCount * 4;
                $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

                $sheet->mergeCells("A1:A2");
                $sheet->mergeCells("B1:B2");
                $sheet->mergeCells("{$lastColLetter}1:{$lastColLetter}2");

                $col = 3;
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
