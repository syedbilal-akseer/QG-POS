<?php

namespace App\Models\WMS;

use Illuminate\Database\Eloquent\Model;

class CycleCountLine extends Model
{
    protected $table = 'wms_cycle_count_lines';

    protected $fillable = [
        'cycle_count_id',
        'item_code',
        'system_qty',
        'counted_qty',
        'variance',
    ];

    protected $casts = [
        'system_qty' => 'decimal:3',
        'counted_qty' => 'decimal:3',
        'variance' => 'decimal:3',
    ];

    public function cycleCount()
    {
        return $this->belongsTo(CycleCount::class);
    }
}
