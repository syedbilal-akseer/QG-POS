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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'inventory_item_id',
        'item_code',
        'item_description',
        'primary_uom_code',
        'secondary_uom_code',
    ];
}
