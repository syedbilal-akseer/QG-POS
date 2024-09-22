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
     * @return void
     */
    public function notifyUser(string $title, string $body, string $status = 'success'): void
    {
        // Validate that the status method exists (success(), danger(), etc.)
        if (in_array($status, ['success', 'danger', 'warning', 'info'])) {
            Notification::make()
                ->$status() // Dynamic method call based on status
                ->title($title)
                ->body($body)
                ->send();
        } else {
            // Fallback to a default notification type if the status is invalid
            Notification::make()
                ->info()
                ->title('Invalid Notification Status')
                ->body('An invalid status was provided.')
                ->send();
        }
    }
}
