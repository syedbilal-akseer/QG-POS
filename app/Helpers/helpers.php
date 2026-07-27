<?php

use App\Models\Order;
use Filament\Notifications\Notification;

if (! function_exists('notify')) {
    /**
     * Notify the user with a Filament notification.
     *
     * @param  string  $title  The title of the notification.
     * @param  string  $body  The body content of the notification.
     * @param  string  $status  The status of the notification ('success', 'danger', etc.) - Optional, defaults to 'success'.
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

if (! function_exists('formatOrderItems')) {
    /**
     * Format the order items for export.
     *
     * @param  Order  $order  The order object containing order items.
     * @return string A formatted string representing the order items.
     */
    function formatOrderItems(Order $order): string
    {
        return $order->orderItems->map(function ($orderItem) {
            return $orderItem->item->item_description.
                ' (Qty: '.$orderItem->quantity.
                ', UOM: '.$orderItem->uom.
                ', Discount: '.$orderItem->discount.
                ', Subtotal: '.$orderItem->sub_total.')';
        })->join('; ');
    }

}

if (! function_exists('normalizeSalespersonKey')) {
    /**
     * Reduce a salesperson name to a comparable key so that formatting
     * differences between Oracle's free-text salesperson field (e.g.
     * "Aqib, Mr. Muhammad") and the portal's auto-generated user name
     * (e.g. "Muhammad Aqib") don't cause a false non-match. Honorifics and
     * the extremely common optional first name "Muhammad" (and its common
     * spellings/abbreviations) are dropped, remaining words are sorted so
     * word order doesn't matter, e.g. both reduce to "aqib".
     *
     * @param  string|null  $name
     */
    function normalizeSalespersonKey(?string $name): string
    {
        if (!$name) {
            return '';
        }

        $dropWords = ['mr', 'mrs', 'ms', 'mstr', 'muhammad', 'mohammad', 'mohammed', 'muhammed', 'md'];

        $words = preg_split('/[\s,\.]+/', mb_strtolower(trim($name)));
        $words = array_filter($words, fn ($w) => $w !== '' && !in_array($w, $dropWords, true));
        sort($words);

        return implode(' ', $words);
    }
}

if (!function_exists('isManager')) {
    /**
     * Check if the authenticated user is a Line Manager.
     *
     * @return bool
     */
    function isManager(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $roleName = $user->role?->name ?? $user->role ?? null;
        return $roleName === 'line-manager';
    }
}

if (!function_exists('isHod')) {
    /**
     * Check if the authenticated user is a Head of Department (HOD).
     *
     * @return bool
     */
    function isHod(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $roleName = $user->role?->name ?? $user->role ?? null;
        return $roleName === 'hod';
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if the authenticated user is an Admin.
     *
     * @return bool
     */
    function isAdmin(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $roleName = $user->role?->name ?? $user->role ?? null;
        return $roleName === 'admin';
    }
}

if (!function_exists('isSupplyChain')) {
    /**
     * Check if the authenticated user is a Supply Chain user.
     *
     * @return bool
     */
    function isSupplyChain(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $roleName = $user->role?->name ?? $user->role ?? null;
        return $roleName === 'supply-chain';
    }
}

if (!function_exists('isAccountUser')) {
    /**
     * Check if the authenticated user is an Account user.
     *
     * @return bool
     */
    function isAccountUser(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        $roleName = $user->role?->name ?? $user->role ?? null;
        return $roleName === 'account-user';
    }
}
