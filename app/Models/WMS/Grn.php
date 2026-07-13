<?php

namespace App\Models\WMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grn extends Model
{
    use HasFactory;

    protected $table = 'wms_grns';

    protected $fillable = [
        'grn_number',
        'po_number',
        'supplier_name',
        'received_date',
        'status',
        'ou_id',
        'warehouse_name',
    ];

    protected $casts = [
        'received_date' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(GrnLine::class, 'grn_id');
    }

    public function lpns()
    {
        return $this->hasManyThrough(Lpn::class, GrnLine::class, 'grn_id', 'grn_line_id');
    }
}
