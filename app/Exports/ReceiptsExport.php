<?php

namespace App\Exports;

use App\Models\CustomerReceipt;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class ReceiptsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;
    protected $status;

    public function __construct($startDate = null, $endDate = null, $status = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
    }

    public function collection()
    {
        $query = CustomerReceipt::with(['customer', 'createdBy', 'enteredBy']);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($this->startDate));
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($this->endDate));
        }

        if ($this->status === 'pending') {
            $query->whereNull('oracle_entered_at');
        } elseif ($this->status === 'pushed') {
            $query->whereNotNull('oracle_entered_at');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Receipt #',
            'Date',
            'Customer Name',
            'Customer Code',
            'OU ID',
            'Salesperson',
            'Currency',
            'Amount',
            'Payment Type',
            'Reference',
            'Oracle Status',
            'Pushed By',
            'Pushed At',
            'Remarks'
        ];
    }

    public function map($receipt): array
    {
        return [
            $receipt->receipt_number,
            $receipt->created_at->format('Y-m-d'),
            $receipt->customer->customer_name ?? 'N/A',
            $receipt->customer->customer_number ?? 'N/A',
            $receipt->customer->ou_id ?? 'N/A',
            $receipt->createdBy->name ?? 'N/A',
            $receipt->currency,
            $receipt->total_amount,
            ucwords(str_replace('_', ' ', $receipt->receipt_type)),
            $receipt->cheque_no ?? '',
            $receipt->oracle_entered_at ? 'Pushed' : 'Pending',
            $receipt->enteredBy->name ?? '',
            $receipt->oracle_entered_at ? Carbon::parse($receipt->oracle_entered_at)->format('Y-m-d H:i:s') : '',
            $receipt->description ?? $receipt->comments ?? ''
        ];
    }
}
