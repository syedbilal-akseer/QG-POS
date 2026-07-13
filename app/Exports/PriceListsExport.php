<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PriceListsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $priceListData;
    protected $priceListOrder;
    protected $filters;

    public function __construct(array $priceListData, array $priceListOrder, array $filters = [])
    {
        $this->priceListData = $priceListData;
        $this->priceListOrder = $priceListOrder;
        $this->filters = $filters;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->priceListData as $item) {
            $row = [
                $item['item_code'],
                $item['item_description'],
                $item['uom'],
            ];

            // Add price columns in the same order as the table
            foreach ($this->priceListOrder as $priceListName) {
                $price = $item['prices'][$priceListName];
                $row[] = $price['exists'] ? number_format($price['list_price'], 2) : '-';
            }

            // Add updated date
            $row[] = $item['updated_at'] ? date('Y-m-d H:i', strtotime($item['updated_at'])) : '-';

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        $headings = [
            'Item Code',
            'Description',
            'UOM'
        ];

        // Add price list headers
        foreach ($this->priceListOrder as $priceListName) {
            $shortName = $this->getPriceListShortName($priceListName);
            $headings[] = $shortName;
        }

        $headings[] = 'Last Updated';

        return $headings;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 15, // Item Code
            'B' => 40, // Description
            'C' => 10, // UOM
        ];

        // Add price list columns
        $column = 'D';
        foreach ($this->priceListOrder as $priceListName) {
            $widths[$column] = 15;
            $column++;
        }

        // Last Updated column
        $widths[$column] = 18;

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        // Add title and filter info
        $sheet->insertNewRowBefore(1, 3);

        $filterText = $this->getFilterText();
        $exportDate = date('Y-m-d H:i:s');
        $totalItems = count($this->priceListData);

        $sheet->setCellValue('A1', 'Price Lists Export');
        $sheet->setCellValue('A2', "Export Date: {$exportDate} | Total Items: {$totalItems}");
        if (!empty($filterText)) {
            $sheet->setCellValue('A3', "Filters: {$filterText}");
        }

        // Title styling
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
        ]);

        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 12],
        ]);

        // Header styling
        $headerRow = empty($filterText) ? 4 : 5;
        $lastColumn = chr(68 + count($this->priceListOrder)); // D + number of price lists

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
        ]);

        return [];
    }

    private function getPriceListShortName(string $priceListName): string
    {
        $shortNames = [
            'Karachi - Trade Price' => 'KHI Trade',
            'Karachi - Wholesale' => 'KHI Wholesale',
            'Karachi - Corporate' => 'KHI Corporate',
            'Lahore - Trade Price' => 'LHR Trade',
            'Lahore - Wholesale' => 'LHR Wholesale',
            'Lahore - Corporate' => 'LHR Corporate',
            'QG HBM' => 'QG HBM',
        ];

        return $shortNames[$priceListName] ?? $priceListName;
    }

    private function getFilterText(): string
    {
        $filters = [];

        if (!empty($this->filters['search'])) {
            $filters[] = "Search: {$this->filters['search']}";
        }

        if (!empty($this->filters['price_type'])) {
            $filters[] = "Type: " . ucfirst($this->filters['price_type']);
        }

        if (!empty($this->filters['changed_only'])) {
            $filters[] = "Changed Only";
        }

        return implode(' | ', $filters);
    }
}