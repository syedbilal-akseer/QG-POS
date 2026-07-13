<?php

namespace App\Filament\Exports;

use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number'),
            ExportColumn::make('customer.customer_name'),
            ExportColumn::make('salesperson.name'),
            ExportColumn::make('orderItems')
                ->state(fn (Order $order) => $order->orderItems->map(function ($orderItem) {
                    return $orderItem->item->item_description.
                        ' (Qty: '.$orderItem->quantity.
                        ', UOM: '.$orderItem->uom.
                        ', Subtotal: '.$orderItem->sub_total.')';
                })->join('; ')),
            ExportColumn::make('order_status')
                ->formatStateUsing(fn ($state) => $state->name()),
            ExportColumn::make('sub_total'),
            ExportColumn::make('discount'),
            ExportColumn::make('total_amount'),

            ExportColumn::make('created_at')
                ->label('Order Placed at'),
            ExportColumn::make('oracle_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        // notify('Export Completed', $body, 'success');

        return $body;
    }
}
