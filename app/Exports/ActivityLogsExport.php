<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ActivityLogsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $logs;

    public function __construct($logs)
    {
        $this->logs = $logs;
    }

    public function collection()
    {
        return $this->logs;
    }

    public function headings(): array
    {
        return [
            'ID',
            'User',
            'Action',
            'Module',
            'Description',
            'IP Address',
            'Date',
        ];
    }

    public function map($log): array
    {
        return [
            $log->id,
            $log->user_name,
            $log->action,
            $log->module,
            $log->description,
            $log->ip_address,
            $log->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
