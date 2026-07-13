<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Eloquent model over the Oracle EBS item master view
 * `apps.qg_pos_item_master`. Used by sync:oracle-products and the
 * price-list import flow to look up Oracle-side item metadata.
 *
 * Note: this file used to contain a copy of the OrderItem class — the
 * filename and class name didn't match, so `App\Models\OracleItem` couldn't
 * be autoloaded and any caller of SyncOracleProducts hit a "Class not found"
 * fatal error.
 */
class OracleItem extends Model
{
    use HasFactory;

    protected $connection = 'oracle';
    protected $table      = 'apps.qg_pos_item_master';
    protected $primaryKey = 'inventory_item_id';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        'inventory_item_id',
        'item_code',
        'item_description',
        'primary_uom_code',
        'secondary_uom_code',
        'major_category',
        'minor_category',
        'sub_minor_category',
    ];
}
