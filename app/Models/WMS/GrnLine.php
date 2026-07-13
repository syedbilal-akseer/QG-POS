<?php

namespace App\Models\WMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GrnLine extends Model
{
    use HasFactory;

    protected $table = 'wms_grn_lines';

    protected $fillable = [
        'grn_id',
        'item_code',
        'description',
        'supplier_lot_number',
        'system_sub_lot',
        'mfg_date',
        'expiry_date',
        'ordered_qty',
        'received_qty',
        'cost_price',
        'pallet_size',
        'lpns_generated',
    ];

    protected $casts = [
        'cost_price'      => 'decimal:4',
        'lpns_generated'  => 'boolean',
    ];

    public function grn()
    {
        return $this->belongsTo(Grn::class, 'grn_id');
    }

    public function lpns()
    {
        return $this->hasMany(Lpn::class, 'grn_line_id');
    }
}
