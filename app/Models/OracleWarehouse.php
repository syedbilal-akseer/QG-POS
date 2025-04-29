<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OracleWarehouse extends Model
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
    protected $table = 'apps.qg_pos_warehouses';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'warehouse_id';  

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
        'organization_id',
        'organization_code',
        'ou_id',
    ];
}
