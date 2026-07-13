<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OracleQgReceiptsPercentage extends Model
{
    protected $connection = 'oracle';
    protected $table = 'apps.qg_receipts_percentage';
    
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $guarded = [];
}
