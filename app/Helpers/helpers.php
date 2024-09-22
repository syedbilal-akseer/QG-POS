<?php

use Filament\Notifications\Notification;

if (!function_exists('notify')) {
    /**
     * Notify the user with a Filament notification.
     *
     * @param  string  $title   The title of the notification.
     * @param  string  $body    The body content of the notification.
     * @param  string  $status  The status of the notification ('success', 'danger', etc.) - Optional, defaults to 'success'.
     * @return void
     */
    function notify(string $title, string $body, string $status = 'success'): void
    {
        // Make sure the status method (like success(), danger(), etc.) exists
        if (in_array($status, ['success', 'danger', 'warning', 'info'])) {
            Notification::make()
                ->$status() // Calls the dynamic method like success(), danger(), etc.
                ->title($title)
                ->body($body)
                ->send();
        } else {
            // Fallback to a default notification type if invalid status is provided
            Notification::make()
                ->info()
                ->title('Invalid Notification Status')
                ->body('An invalid status was provided for the notification.')
                ->send();
        }
    }
}
