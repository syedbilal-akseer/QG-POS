<?php

namespace App\Actions;

use App\Exports\OrdersExport;
use App\Models\Order;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Builder;

class OrderExportAction
{
    /**
     * Handle the export action.
     */
    public function handle(array $filters = [], string $format = 'csv'): string
    {
        $ordersQuery = $this->applyFilters($filters);
    
        // Get current date to append to the file name
        $currentDate = Carbon::now()->format('Y-m-d');
    
        if ($format === 'csv') {
            return $this->exportCsv($ordersQuery->get(), $currentDate);
        } elseif ($format === 'excel') {
            return $this->exportExcel($ordersQuery->get(), $currentDate);
        } elseif ($format === 'pdf') {
            return $this->exportPdf($ordersQuery->get(), $currentDate);
        }
    
        throw new InvalidArgumentException('Invalid export format specified.');
    }

    /**
     * Apply filters to the order query.
     */
    public function applyFilters(array $filters): Builder
    {
        $query = Order::query();
    
        // Restrict results based on the user's role (non-admins only see their orders)
        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }
    
        // Filter by date range (start_date and end_date in d/m/Y format).
        // IMPORTANT: orders.created_at is stored in UTC. The filter dates come
        // in app-timezone (Asia/Karachi) terms — so we build the PKT day
        // boundary and convert to UTC before comparing on the full datetime.
        // Using whereDate() previously stripped the time portion and compared
        // a PKT-typed date against a UTC-typed date, which leaked the first
        // ~5 hours of "today" PKT into yesterday's filter (1:30 AM PKT today
        // = 8:30 PM UTC yesterday, so its UTC date matched a yesterday filter).
        $appTz = config('app.timezone', 'Asia/Karachi');

        if (! empty($filters['start_date'])) {
            $startDate = Carbon::createFromFormat('d/m/Y', $filters['start_date'], $appTz)
                ->startOfDay()
                ->setTimezone('UTC');
            $query->where('created_at', '>=', $startDate);
        }

        if (! empty($filters['end_date'])) {
            $endDate = Carbon::createFromFormat('d/m/Y', $filters['end_date'], $appTz)
                ->endOfDay()
                ->setTimezone('UTC');
            $query->where('created_at', '<=', $endDate);
        }
    
        // Filter by customer_id
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
    
        // Filter by user_id (formerly salesperson_id)
        if (! empty($filters['salesperson_id'])) {
            $query->where('user_id', $filters['salesperson_id']);  // Use user_id instead of salesperson_id
        }
    
        return $query;
    }
    

    /**
     * Export orders as CSV and store it temporarily.
     */
    protected function exportCsv(Collection $orders, string $currentDate): string
    {
        $filePath = storage_path("app/public/orders_export_{$currentDate}.csv");

        // Create CSV file
        $csvFile = fopen($filePath, 'w');
        fputcsv($csvFile, $this->getColumns());

        $counter = 1;
        foreach ($orders as $order) {
            $orderData = $this->mapOrderData($order);
            array_unshift($orderData, $counter++);
            fputcsv($csvFile, $orderData);
        }

        fclose($csvFile);

        // Return the file path or URL (if serving publicly)
        return asset("storage/orders_export_{$currentDate}.csv");
    }

    /**
     * Export orders as Excel and store it temporarily.
     */
    protected function exportExcel(Collection $orders, string $currentDate): string
    {
        $filePath = "orders_export_{$currentDate}.xlsx";
        Excel::store(new OrdersExport($orders), $filePath, 'public');

        return asset("storage/{$filePath}");
    }

    /**
     * Export orders as PDF and store it temporarily.
     */
    protected function exportPdf(Collection $orders, string $currentDate): string
    {
        $filePath = storage_path("app/public/orders_export_{$currentDate}.pdf");

        $dompdf = new Dompdf;
        $html = view('exports.orders_pdf', ['orders' => $orders])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        file_put_contents($filePath, $dompdf->output());

        return asset("storage/orders_export_{$currentDate}.pdf");
    }

    /**
     * Get the CSV columns.
     */
    protected function getColumns(): array
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

    /**
     * Map the order data to match the columns.
     */
    protected function mapOrderData(Order $order): array
    {
        return [
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
    }
}
