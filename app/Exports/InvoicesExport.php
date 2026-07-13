<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = Invoice::with(['uploader']);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($this->startDate));
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($this->endDate));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Customer Name',
            'Customer Code',
            'Amount',
            'Processing Status',
            'Original Filename',
            'Page Range',
            'Uploaded By',
            'Uploaded At',
            'Notes'
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->customer_name,
            $invoice->customer_code,
            $invoice->total_amount,
            ucfirst($invoice->processing_status),
            $invoice->original_filename,
            $invoice->page_range,
            $invoice->uploader->name ?? 'Unknown',
            $invoice->uploaded_at ? $invoice->uploaded_at->format('Y-m-d H:i:s') : '',
            $invoice->notes
        ];
    }
}
