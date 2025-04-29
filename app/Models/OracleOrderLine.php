<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OracleOrderLine extends Model
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
    protected $table = 'apps.oe_lines_iface_all';

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
        'orig_sys_line_ref',
        'line_number',
        'inventory_item_id',
        'ordered_quantity',
        'ship_from_org_id',
        'org_id',
        'unit_selling_price',
        'price_list_id',
        'payment_term_id',
        'created_by',
        'creation_date',
        'last_updated_by',
        'last_update_date',
        'line_type_id',
        'operation_code'
    ];
}
