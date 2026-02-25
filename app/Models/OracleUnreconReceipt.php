<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OracleUnreconReceipt extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'oracle';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apps.qg_pos_unrecon_receipts';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'org_id',
        'customer_number',
        'customer_name',
        'receipt_number',
        'receipt_date',
        'receipt_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'org_id' => 'integer',
        'receipt_date' => 'date',
        'receipt_amount' => 'decimal:2',
    ];

    /**
     * Scope to filter by organization unit.
     */
    public function scopeByOrgUnit($query, $ouId)
    {
        return $query->where('org_id', $ouId);
    }
}
