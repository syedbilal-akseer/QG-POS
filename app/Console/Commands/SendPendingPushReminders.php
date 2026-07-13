<?php

namespace App\Console\Commands;

use App\Enums\OrderStatusEnum;
use App\Models\CustomerReceipt;
use App\Models\Order;
use App\Services\OrderReceiptNotifier;
use Illuminate\Console\Command;

/**
 * Hourly buzzer: notify the role-relevant users about orders/receipts
 * that have been pending push to Oracle for at least the threshold (default 24h).
 *
 * Schedule: every hour. Each run sends a fresh notification, so users keep
 * getting reminded until the item is pushed (true "buzzer" behavior).
 *
 *   php artisan notifications:pending-reminders
 *   php artisan notifications:pending-reminders --hours=12
 *   php artisan notifications:pending-reminders --dry-run
 */
class SendPendingPushReminders extends Command
{
    protected $signature = 'notifications:pending-reminders
                            {--hours=24 : Item must be older than this many hours}
                            {--dry-run  : List items that would be notified, send nothing}';

    protected $description = 'Send hourly push reminders for orders/receipts pending push to Oracle';

    public function handle(OrderReceiptNotifier $notifier): int
    {
        $hours    = (int) $this->option('hours');
        $cutoff   = now()->subHours($hours);
        $dryRun   = (bool) $this->option('dry-run');

        // Pending orders — not yet entered to Oracle, older than threshold,
        // and not cancelled (cancelled orders shouldn't reminder anyone).
        $pendingOrders = Order::query()
            ->whereNull('oracle_at')
            ->where('created_at', '<=', $cutoff)
            ->where('order_status', '!=', OrderStatusEnum::CANCELED)
            ->with('customer:id,customer_id,customer_name,ou_id')
            ->get();

        // Pending receipts — not yet entered to Oracle, older than threshold.
        $pendingReceipts = CustomerReceipt::query()
            ->whereNull('oracle_entered_at')
            ->where('created_at', '<=', $cutoff)
            ->with('customer:id,customer_id,customer_name,ou_id')
            ->get();

        $this->info(sprintf(
            'Threshold: > %dh   Pending orders: %d   Pending receipts: %d',
            $hours,
            $pendingOrders->count(),
            $pendingReceipts->count(),
        ));

        if ($dryRun) {
            foreach ($pendingOrders as $o) {
                $this->line(sprintf(
                    '  [order] #%s  age=%dh  ou=%s',
                    $o->order_number,
                    (int) now()->diffInHours($o->created_at),
                    optional($o->customer)->ou_id ?? '?',
                ));
            }
            foreach ($pendingReceipts as $r) {
                $this->line(sprintf(
                    '  [receipt] #%s  age=%dh  ou=%s',
                    $r->receipt_number,
                    (int) now()->diffInHours($r->created_at),
                    $r->ou_id ?? optional($r->customer)->ou_id ?? '?',
                ));
            }
            $this->info('--dry-run: nothing sent.');
            return self::SUCCESS;
        }

        foreach ($pendingOrders as $order) {
            $notifier->orderPendingReminder($order);
        }
        foreach ($pendingReceipts as $receipt) {
            $notifier->receiptPendingReminder($receipt);
        }

        $this->info('Reminders dispatched.');
        return self::SUCCESS;
    }
}
