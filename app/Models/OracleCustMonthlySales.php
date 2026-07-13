<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view over APPS.QG_POS_CUST_MONTHLY_SALES_V — customer-wise,
 * month-wise net sales as reported by Oracle EBS. Used by the salesperson
 * dashboard's day-to-month / day-to-year breakdown.
 *
 * Common column shapes seen in this codebase's Oracle views:
 *   - customer identifier: customer_id  /  customer_number
 *   - period: year + month, OR period_year + period_month, OR period_date
 *   - amount: net_amount / sales_amount / amount
 *
 * Use Eloquent's getAttributes() / row->X accessors lazily — strict-mode
 * would throw on a missing column. Helper `valueOf()` covers both cases.
 */
class OracleCustMonthlySales extends Model
{
    protected $connection = 'oracle';
    protected $table      = 'apps.qg_pos_cust_monthly_sales_v';
    public    $incrementing = false;
    public    $timestamps   = false;

    /**
     * Read a column from a row, accepting any of the candidate column names
     * (case-insensitive), returning the first non-null value. Lets the same
     * code work whether the Oracle driver returns lower-, upper- or mixed-case
     * column names.
     */
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
