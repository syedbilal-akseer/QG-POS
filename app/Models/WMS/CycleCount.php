<?php

namespace App\Models\WMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CycleCount extends Model
{
    protected $table = 'wms_cycle_counts';

    protected $fillable = [
        'location_id',
        'counted_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function countedBy()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function lines()
    {
        return $this->hasMany(CycleCountLine::class);
    }
}
