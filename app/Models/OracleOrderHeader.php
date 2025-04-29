<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OracleOrderHeader extends Model
{
    use HasFactory;

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
    protected $table = 'apps.oe_headers_iface_all';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'inventory_item_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

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
        'order_source_id',
        'orig_sys_document_ref',
        'org_id',
        'sold_from_org_id',
        'ship_from_org_id',
        'ordered_date',
        'order_type_id',
        'sold_to_org_id',
        'payment_term_id',
        'operation_code',
        'created_by',
        'creation_date',
        'last_updated_by',
        'last_update_date',
        'customer_po_number',
        'ship_to_org_id',
        'BOOKED_FLAG'
    ];

    public function orderLines()
    {
        return $this->hasMany(OracleOrderLine::class, 'orig_sys_document_ref', 'orig_sys_document_ref');
    }
}
