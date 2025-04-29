<?php

namespace App\Traits;

use Filament\Notifications\Notification;

trait NotifiesUsers
{
    /**
     * Notify the user with a Filament notification.
     *
     * @param  string  $title   The title of the notification.
     * @param  string  $body    The body content of the notification.
     * @param  string  $status  The status of the notification ('success', 'danger', etc.) - Optional, defaults to 'success'.
     * @param  bool    $toDatabase  Whether to send the notification to the database - Optional, defaults to false.
     * @param  mixed   $recipient  The recipient of the database notification - Optional, defaults to the authenticated user.
     * @return void
     */
    public function notifyUser(
        string $title,
        string $body,
        string $status = 'success',
        bool $toDatabase = false,
        $recipient = null
    ): void {
        // Validate that the status method exists (success(), danger(), etc.)
        $validStatuses = ['success', 'danger', 'warning', 'info'];

        if (!in_array($status, $validStatuses)) {
            $status = 'info';
        }

        // Determine the notification mode
        $sendRealTime = !$toDatabase; // Real-time only if not sending to the database

        if ($sendRealTime) {
            // Create the notification
            Notification::make()
                ->$status() // Dynamic method call based on status
                ->title($title)
                ->body($body)
                ->send();
        }

        if ($toDatabase) {
            $recipient = $recipient ?? auth()->user();
            if ($recipient) {
                Notification::make()
                    ->$status()
                    ->title($title)
                    ->body($body)
                    ->sendToDatabase($recipient);
            }
        }
    }

    /**
     * A shorthand method for notifying the user.
     *
     * @param  string  $title   The title of the notification.
     * @param  string  $body    The body content of the notification.
     * @param  string  $status  The status of the notification ('success', 'danger', etc.) - Optional, defaults to 'success'.
     * @param  bool    $toDatabase  Whether to send the notification to the database - Optional, defaults to false.
     * @param  mixed   $recipient  The recipient of the database notification - Optional, defaults to the authenticated user.
     * @return void
     */
    public function notify(
        string $title,
        string $body,
        string $status = 'success',
        bool $toDatabase = false,
        $recipient = null
    ): void {
        $this->notifyUser($title, $body, $status, $toDatabase, $recipient);
    }
}
