<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OracleOrderType extends Model
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
    protected $table = 'apps.QG_TRAN_MAPP';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = '';

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
}
