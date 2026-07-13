<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersExport implements FromCollection, WithHeadings
{
    protected $orders;

    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    public function collection()
    {
        return $this->orders->map(function ($order, $index) {
            return [
                $index + 1, // Sr No
                $order->order_number,
                $order->customer->customer_name,
                $order->salesperson->name,
                formatOrderItems($order),
                $order->order_status->name(),
                $order->discount,
                $order->sub_total,
                $order->total_amount,
                $order->created_at,
                $order->oracle_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Sr No',
            'Order Number',
            'Customer Name',
            'Salesperson Name',
            'Order Items',
            'Order Status',
            'Discount',
            'Subtotal',
            'Total Amount',
            'Placed At',
            'Oracle At',
        ];
    }
}
