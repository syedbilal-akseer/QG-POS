<?php

namespace App\Notifications;

use App\Models\ReturnedCheque;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChequeReturnedNotification extends Notification
{
    use Queueable;

    protected $returnedCheque;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReturnedCheque $returnedCheque)
    {
        $this->returnedCheque = $returnedCheque;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $cheque = $this->returnedCheque->cheque;
        $receipt = $this->returnedCheque->customerReceipt;
        
        return [
            'returned_cheque_id' => $this->returnedCheque->id,
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'cheque_no' => $cheque->cheque_no,
            'cheque_amount' => $cheque->cheque_amount,
            'customer_name' => $receipt->customer->customer_name ?? 'N/A',
            'reason' => $this->returnedCheque->reason,
            'title' => 'Cheque Returned / Bounced',
            'message' => "Cheque #{$cheque->cheque_no} for {$receipt->customer->customer_name} has been returned. Reason: {$this->returnedCheque->reason}",
        ];
    }
}
