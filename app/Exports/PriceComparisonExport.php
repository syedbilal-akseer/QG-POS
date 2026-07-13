<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PriceComparisonExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $comparisonData;
    protected $uploadInfo;

    public function __construct(array $comparisonData, array $uploadInfo = [])
    {
        $this->comparisonData = $comparisonData;
        $this->uploadInfo = $uploadInfo;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->comparisonData as $item) {
            $rows[] = [
                $item['item_code'],
                $item['item_description'] ?? 'N/A',
                $item['uom'] ?? 'N/A',
                $item['price_list_name'] ?? 'N/A',
                $item['previous_price'] ?? 'New Item',
                number_format($item['list_price'], 2),
                $item['price_change'] ? number_format($item['price_change'], 2) : 'N/A',
                ucfirst($item['price_status']),
                $item['start_date_active'] ?? 'N/A'
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Item Code',
            'Description',
            'UOM',
            'Price List',
            'Previous Price',
            'New Price',
            'Difference',
            'Status',
            'Start Date'
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Item Code
            'B' => 40, // Description
            'C' => 10, // UOM
            'D' => 20, // Price List
            'E' => 15, // Previous Price
            'F' => 15, // New Price
            'G' => 15, // Difference
            'H' => 12, // Status
            'I' => 15, // Start Date
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styling
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
        ]);

        // Add upload info as a title if provided
        if (!empty($this->uploadInfo)) {
            $sheet->insertNewRowBefore(1, 3);

            $uploadDate = $this->uploadInfo['uploaded_at'] ?? date('Y-m-d H:i:s');
            $uploadBy = $this->uploadInfo['uploaded_by'] ?? 'System';
            $totalItems = count($this->comparisonData);

            $sheet->setCellValue('A1', 'Price Comparison Report');
            $sheet->setCellValue('A2', "Upload Date: {$uploadDate} | Uploaded By: {$uploadBy} | Total Items: {$totalItems}");

            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16],
            ]);

            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 12],
            ]);
        }

        // Apply conditional formatting for status
        $lastRow = count($this->comparisonData) + 1;
        if (!empty($this->uploadInfo)) {
            $lastRow += 3; // Account for title rows
        }

        // Color code based on status
        for ($row = (!empty($this->uploadInfo) ? 5 : 2); $row <= $lastRow; $row++) {
            $status = $sheet->getCell("H{$row}")->getValue();

            switch (strtolower($status)) {
                case 'new':
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D1ECF1'], // Light blue
                        ],
                    ]);
                    break;
                case 'increased':
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF3CD'], // Light yellow
                        ],
                    ]);
                    break;
                case 'decreased':
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8D7DA'], // Light red
                        ],
                    ]);
                    break;
                case 'same':
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8F9FA'], // Light gray
                        ],
                    ]);
                    break;
            }
        }

        return [];
    }
}