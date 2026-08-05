<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CustomerReceipt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Centralizes the four push-notification trigger points so trigger
 * logic lives in one place instead of being scattered across controllers.
 *
 *   - Order created          → notify supply-chain + scm-lhr (scoped to OU)
 *   - Order pushed to Oracle → notify the salesperson who placed it
 *   - Receipt created        → notify admin + cmd-* (scoped to OU)
 *   - Receipt pushed to Oracle → notify the salesperson who created it
 *
 * Recipient filtering for "created" events is OU-scoped so e.g. cmd-khi
 * only hears about Karachi receipts and supply-chain only hears about
 * orders for OUs in their UserOrganization assignments. Admin always
 * matches because their getAllowedOuIds() returns every OU.
 *
 * Each trigger swallows exceptions internally so a notification failure
 * never aborts the underlying business operation.
 */
class OrderReceiptNotifier
{
    public function __construct(protected FcmService $fcm) {}

    public function orderCreated(Order $order): void
    {
        try {
            $orderOuId = $this->resolveOrderOuId($order);

            $title = 'New Order Received';
            $body  = sprintf(
                'Order #%s from %s — Rs %s',
                $order->order_number,
                optional($order->customer)->customer_name ?? 'customer',
                number_format((float) ($order->sub_total ?? 0), 2),
            );
            $data = [
                'type'         => 'order_created',
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'ou_id'        => (string) ($orderOuId ?? ''),
            ];

            // Candidates: supply-chain (push orders to Oracle), scm-lhr (Lahore
            // warehouse), and admin (sees everything).
            $candidates = User::query()
                ->whereIn('role', ['supply-chain', 'scm-lhr', 'admin'])
                ->whereNotNull('fcm_token')
                ->get();

            $recipients = $this->filterByOu($candidates, $orderOuId, 'getAllowedOuIds');

            $this->fcm->sendToUsers($recipients, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('orderCreated notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function orderPushedToOracle(Order $order): void
    {
        try {
            $title = 'Order Entered to Oracle';
            $body  = sprintf('Your order #%s has been entered to Oracle.', $order->order_number);
            $data  = [
                'type'         => 'order_pushed',
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ];

            $salesperson = User::find($order->user_id ?? $order->salesperson_id);
            $this->fcm->sendToUser($salesperson, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('orderPushedToOracle notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function receiptCreated(CustomerReceipt $receipt): void
    {
        try {
            $receiptOuId = $this->resolveReceiptOuId($receipt);

            $title = 'New Receipt Submitted';
            $body  = sprintf(
                'Receipt #%s — %s — Rs %s',
                $receipt->receipt_number,
                optional($receipt->customer)->customer_name ?? 'customer',
                number_format((float) ($receipt->receipt_amount ?? 0), 2),
            );
            $data = [
                'type'           => 'receipt_created',
                'receipt_id'     => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'ou_id'          => (string) ($receiptOuId ?? ''),
            ];

            // Candidates: admin (everywhere), cmd-khi (Karachi), cmd-lhr (Lahore)
            $candidates = User::query()
                ->whereIn('role', ['admin', 'cmd-khi', 'cmd-lhr', 'cmd-head'])
                ->whereNotNull('fcm_token')
                ->get();

            $recipients = $this->filterByOu($candidates, $receiptOuId, 'getAllowedReceiptOuIds');

            $this->fcm->sendToUsers($recipients, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('receiptCreated notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function receiptPushedToOracle(CustomerReceipt $receipt): void
    {
        try {
            $title = 'Receipt Entered to Oracle';
            $body  = sprintf('Your receipt #%s has been entered to Oracle.', $receipt->receipt_number);
            $data  = [
                'type'           => 'receipt_pushed',
                'receipt_id'     => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
            ];

            $salesperson = User::find($receipt->created_by);
            $this->fcm->sendToUser($salesperson, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('receiptPushedToOracle notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Order cancelled by salesperson or admin. Notify supply-chain (in the
     * order's OU) so they can remove it from their pending push list, plus
     * the original salesperson on the order if cancel was done by someone else.
     */
    public function orderCancelled(Order $order, string $reason): void
    {
        try {
            $ouId  = $this->resolveOrderOuId($order);
            $title = '❌ Order Cancelled';
            $body  = sprintf(
                'Order #%s cancelled — %s',
                $order->order_number,
                \Illuminate\Support\Str::limit($reason, 80),
            );
            $data = [
                'type'                => 'order_cancelled',
                'order_id'            => $order->id,
                'order_number'        => $order->order_number,
                'cancellation_reason' => $reason,
                'ou_id'               => (string) ($ouId ?? ''),
            ];

            // Supply-chain + scm-lhr + admin scoped to OU
            $recipients = User::query()
                ->whereIn('role', ['supply-chain', 'scm-lhr', 'admin'])
                ->whereNotNull('fcm_token')
                ->get();
            $recipients = $this->filterByOu($recipients, $ouId, 'getAllowedOuIds');

            // Also notify the salesperson on the order, if different from the canceller
            $salespersonId = $order->user_id ?? $order->salesperson_id;
            if ($salespersonId && $salespersonId !== ($order->cancelled_by ?? null)) {
                $salesperson = User::find($salespersonId);
                if ($salesperson && $salesperson->fcm_token) {
                    $recipients = $recipients->push($salesperson)->unique('id');
                }
            }

            $this->fcm->sendToUsers($recipients, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('orderCancelled notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Receipt cancelled. Notify the creator + admin / cmd-* scoped to OU.
     */
    public function receiptCancelled(CustomerReceipt $receipt, string $reason): void
    {
        try {
            $ouId  = $this->resolveReceiptOuId($receipt);
            $title = '❌ Receipt Cancelled';
            $body  = sprintf(
                'Receipt #%s cancelled — %s',
                $receipt->receipt_number,
                \Illuminate\Support\Str::limit($reason, 80),
            );
            $data = [
                'type'                => 'receipt_cancelled',
                'receipt_id'          => $receipt->id,
                'receipt_number'      => $receipt->receipt_number,
                'cancellation_reason' => $reason,
                'ou_id'               => (string) ($ouId ?? ''),
            ];

            $recipients = User::query()
                ->whereIn('role', ['admin', 'cmd-khi', 'cmd-lhr', 'cmd-head'])
                ->whereNotNull('fcm_token')
                ->get();
            $recipients = $this->filterByOu($recipients, $ouId, 'getAllowedReceiptOuIds');

            $creatorId = $receipt->created_by;
            if ($creatorId && $creatorId !== ($receipt->cancelled_by ?? null)) {
                $creator = User::find($creatorId);
                if ($creator && $creator->fcm_token) {
                    $recipients = $recipients->push($creator)->unique('id');
                }
            }

            $this->fcm->sendToUsers($recipients, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('receiptCancelled notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Hourly buzzer: order has been pending push to Oracle for ≥ 24h.
     * Sent to the same recipients as orderCreated (supply-chain + admin
     * scoped to the order's OU).
     */
    public function orderPendingReminder(Order $order): void
    {
        try {
            $orderOuId = $this->resolveOrderOuId($order);
            $hours     = (int) max(1, now()->diffInHours($order->created_at));

            $title = '⏰ Order Pending Push';
            $body  = sprintf(
                'Order #%s is %dh old and not yet pushed to Oracle.',
                $order->order_number,
                $hours,
            );
            $data = [
                'type'         => 'order_pending_reminder',
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'ou_id'        => (string) ($orderOuId ?? ''),
                'pending_hours'=> (string) $hours,
            ];

            $candidates = User::query()
                ->whereIn('role', ['supply-chain', 'scm-lhr', 'admin'])
                ->whereNotNull('fcm_token')
                ->get();

            $recipients = $this->filterByOu($candidates, $orderOuId, 'getAllowedOuIds');
            $this->fcm->sendToUsers($recipients, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('orderPendingReminder failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Hourly buzzer: receipt has been pending push to Oracle for ≥ 24h.
     */
    public function receiptPendingReminder(CustomerReceipt $receipt): void
    {
        try {
            $receiptOuId = $this->resolveReceiptOuId($receipt);
            $hours       = (int) max(1, now()->diffInHours($receipt->created_at));

            $title = '⏰ Receipt Pending Push';
            $body  = sprintf(
                'Receipt #%s is %dh old and not yet pushed to Oracle.',
                $receipt->receipt_number,
                $hours,
            );
            $data = [
                'type'           => 'receipt_pending_reminder',
                'receipt_id'     => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'ou_id'          => (string) ($receiptOuId ?? ''),
                'pending_hours'  => (string) $hours,
            ];

            $candidates = User::query()
                ->whereIn('role', ['admin', 'cmd-khi', 'cmd-lhr', 'cmd-head'])
                ->whereNotNull('fcm_token')
                ->get();

            $recipients = $this->filterByOu($candidates, $receiptOuId, 'getAllowedReceiptOuIds');
            $this->fcm->sendToUsers($recipients, $title, $body, $data);
        } catch (\Throwable $e) {
            \Log::warning('receiptPendingReminder failed', ['error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Filter a candidate user collection down to those whose allowed-OU
     * list (returned by $methodName on User) contains the entity's OU.
     *
     * If $entityOuId is null we can't determine where the entity belongs,
     * so we fall back to notifying every candidate (better an extra ping
     * than a missed alert).
     */
    protected function filterByOu(Collection $candidates, ?int $entityOuId, string $methodName): Collection
    {
        if ($entityOuId === null) {
            return $candidates;
        }

        return $candidates->filter(function (User $user) use ($entityOuId, $methodName) {
            if (!method_exists($user, $methodName)) {
                return true; // can't filter — keep them in
            }
            $allowed = $user->{$methodName}();
            return in_array((int) $entityOuId, array_map('intval', (array) $allowed), true);
        })->values();
    }

    /**
     * Order OU comes from the customer record.
     */
    protected function resolveOrderOuId(Order $order): ?int
    {
        $ouId = optional($order->customer)->ou_id;
        return $ouId !== null ? (int) $ouId : null;
    }

    /**
     * Receipt OU is stored directly on customer_receipts; fall back
     * to the customer's OU if the receipt didn't capture it.
     */
    protected function resolveReceiptOuId(CustomerReceipt $receipt): ?int
    {
        if ($receipt->ou_id !== null && $receipt->ou_id !== '') {
            return (int) $receipt->ou_id;
        }
        $ouId = optional($receipt->customer)->ou_id;
        return $ouId !== null ? (int) $ouId : null;
    }
}
