<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

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
        };
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
