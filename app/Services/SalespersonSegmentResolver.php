<?php

namespace App\Services;

use App\Models\User;

/**
 * Salesperson -> business-segment lookup (Commercial / Corporate / Directors /
 * HBM / KHI / Others / POS) for the sales-order percentage report. Segment is
 * synced from Oracle's qg_all_users view (SALESPERSON_SEGMENT column) into
 * users.segment by `sync:oracle-users` — this class only reads the local
 * users table, it never queries Oracle directly.
 */
class SalespersonSegmentResolver
{
    public const DEFAULT_SEGMENT = 'Others';

    /** @var array<string, string>|null salesperson_name => segment, built once per request */
    private static ?array $lookup = null;

    public static function forSalesperson(string $salesperson): string
    {
        return self::lookup()[$salesperson] ?? self::DEFAULT_SEGMENT;
    }

    /** @return array<string, string> */
    private static function lookup(): array
    {
        if (self::$lookup === null) {
            self::$lookup = User::query()
                ->whereNotNull('salesperson_name')
                ->whereNotNull('segment')
                ->pluck('segment', 'salesperson_name')
                ->all();
        }

        return self::$lookup;
    }
}
