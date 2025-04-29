<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    case SYNCED = 'synced'; // New status for orders synced to Oracle
    case ENTERED = 'entered'; // Enter to Oracle

    /**
     * Get the description for the status.
     *
     * @return string
     */
    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Order has been placed but not yet processed.',
            self::PROCESSING => 'Order is being processed.',
            self::COMPLETED => 'Order has been completed.',
            self::CANCELED => 'Order has been canceled.',
            self::SYNCED => 'Order has been synced with Oracle.', // Description for the new status
            self::ENTERED => 'Order has been entered to Oracle.', // Description for the new status
        };
    }

    /**
     * Get the name of the status.
     *
     * @return string
     */
    public function name(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::CANCELED => 'Canceled',
            self::SYNCED => 'Synced with Oracle', // Name for the new status
            self::ENTERED => 'Enter to Oracle', // Name for the new status
        };
    }

    /**
     * Get the badge colors for each status.
     *
     * @return array<string, string>
     */
    public static function badgeColors(): array
    {
        return [
            self::PENDING->value => 'warning',     // Yellow for pending
            self::PROCESSING->value => 'primary',  // Blue for processing
            self::COMPLETED->value => 'success',   // Green for completed
            self::CANCELED->value => 'danger',     // Red for canceled
            self::SYNCED->value => 'info',         // Light blue for synced
            self::ENTERED->value => 'info',         // Light blue for posted
        ];
    }

    /**
     * Get the color for a given status.
     *
     * @param  string $status
     * @return string
     */
    public static function color(string $status): string
    {
        $colors = self::badgeColors();
        return $colors[$status] ?? 'secondary'; // Default color if status is not found
    }

    /**
     * Get all statuses as an array of objects.
     *
     * @return array
     */
    public static function getValues(): array
    {
        return [
            (object)['id' => self::PENDING->value, 'name' => self::PENDING->name()],
            (object)['id' => self::PROCESSING->value, 'name' => self::PROCESSING->name()],
            (object)['id' => self::COMPLETED->value, 'name' => self::COMPLETED->name()],
            (object)['id' => self::CANCELED->value, 'name' => self::CANCELED->name()],
            (object)['id' => self::SYNCED->value, 'name' => self::SYNCED->name()], // New status object
            (object)['id' => self::ENTERED->value, 'name' => self::ENTERED->name()], // New status object
        ];
    }

    /**
     * Get all statuses as an associative array.
     *
     * @return array<string, string>
     */
    public static function asArray(): array
    {
        return [
            self::PENDING->value => self::PENDING->name(),
            self::PROCESSING->value => self::PROCESSING->name(),
            self::COMPLETED->value => self::COMPLETED->name(),
            self::CANCELED->value => self::CANCELED->name(),
            self::SYNCED->value => self::SYNCED->name(), // Add synced to the array
            self::ENTERED->value => self::ENTERED->name(), // Add posted to the array
        ];
    }

    /**
     * Get all status keys.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::getValues(), 'id');
    }

    /**
     * Get all status names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::getValues(), 'name');
    }
}
