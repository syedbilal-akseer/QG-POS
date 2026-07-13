<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view over APPS.QG_POS_CUST_MONTHLY_COLL_V — customer-wise,
 * month-wise CLEARED collections (cleared receipts only). Used by the
 * salesperson dashboard for the receivables / collection progress.
 *
 * Column names may come back from the oci8 driver in any case — use
 * `valueOf()` for safe lookup with multiple candidate names.
 */
class OracleCustMonthlyColl extends Model
{
    protected $connection = 'oracle';
    protected $table      = 'apps.qg_pos_cust_monthly_coll_v';
    public    $incrementing = false;
    public    $timestamps   = false;

    public static function valueOf($row, array $candidates, $default = null)
    {
        $attrs = method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
        $byLower = [];
        foreach ($attrs as $k => $v) {
            $byLower[strtolower($k)] = $v;
        }
        foreach ($candidates as $name) {
            $key = strtolower($name);
            if (array_key_exists($key, $byLower) && $byLower[$key] !== null) {
                return $byLower[$key];
            }
        }
        return $default;
    }
}
