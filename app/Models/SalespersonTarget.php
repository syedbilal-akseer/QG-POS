<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalespersonTarget extends Model
{
    protected $fillable = [
        'user_id',
        'primary_name',
        'salesman_name',
        'year',
        'month',
        'receipt_target',
        'sales_target',
        'unit',
        'period_start_date',
    ];

    protected $casts = [
        'year'              => 'integer',
        'month'             => 'integer',
        'receipt_target'    => 'decimal:6',  // 6dp because TARGET.xlsx uses 9dp millions
        'sales_target'      => 'decimal:6',
        'period_start_date' => 'date',
    ];

    /**
     * Conversion factor from the stored unit to PKR rupees.
     */
    public const UNIT_FACTORS = [
        'PKR'           => 1,
        'THOUSAND_PKR'  => 1_000,
        'MILLION_PKR'   => 1_000_000,
    ];

    /**
     * The stored receipt_target converted to actual PKR rupees.
     * Use this everywhere data is returned to the mobile / portal so the
     * UI never has to know about the unit.
     */
    public function getReceiptTargetPkrAttribute(): float
    {
        $factor = self::UNIT_FACTORS[$this->unit] ?? 1;
        return (float) $this->receipt_target * $factor;
    }

    /**
     * Same conversion for the sales_target column. Sales targets share the
     * row's `unit` field with the receipt target — both columns in TARGET.xlsx
     * are expressed in the same unit per row.
     */
    public function getSalesTargetPkrAttribute(): float
    {
        $factor = self::UNIT_FACTORS[$this->unit] ?? 1;
        return (float) $this->sales_target * $factor;
    }

    /**
     * Multiply any aggregate sum by the right factor.
     * Useful when summing across rows that share the same unit.
     */
    public static function toPkr(float $rawValue, ?string $unit): float
    {
        $factor = self::UNIT_FACTORS[$unit ?? 'MILLION_PKR'] ?? 1_000_000;
        return $rawValue * $factor;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve "Anjum, Tahir" / "Tahir Anjum" → users.id.
     * Tries: exact users.name, oracle_user_name, salesperson_name (the exact
     * Oracle string synced by sync:oracle-users — catches rows where
     * TARGET.xlsx happens to use the same format Oracle does), then a
     * swapped "First Last" form against users.name.
     */
    public static function resolveUserId(?string $primaryName, ?string $salesmanName): ?int
    {
        $candidates = collect([$primaryName, $salesmanName])->filter()->unique();

        foreach ($candidates as $name) {
            $name = trim($name);
            if ($name === '') continue;

            // Try direct match
            $user = User::where('name', $name)->first();
            if ($user) return $user->id;

            // Try oracle_user_name
            $user = User::where('oracle_user_name', $name)->first();
            if ($user) return $user->id;

            // Try salesperson_name (exact Oracle string, synced from qg_all_users)
            $user = User::where('salesperson_name', $name)->first();
            if ($user) return $user->id;

            // Try swapping "Last, First" → "First Last"
            if (str_contains($name, ',')) {
                $parts = array_map('trim', explode(',', $name, 2));
                if (count($parts) === 2) {
                    $swapped = $parts[1] . ' ' . $parts[0];
                    $user = User::where('name', $swapped)->first();
                    if ($user) return $user->id;
                }
            }
        }

        return null;
    }
}
