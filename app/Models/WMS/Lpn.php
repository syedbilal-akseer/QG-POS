<?php

namespace App\Models\WMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lpn extends Model
{
    use HasFactory;

    protected $table = 'wms_lpns';

    protected $fillable = [
        'lpn_number',
        'grn_line_id',
        'item_code',
        'lot_number',
        'system_sub_lot',
        'mfg_date',
        'expiry_date',
        'quantity',
        'uom',
        'parent_lpn_id',
        'location_id',
        'status',
        'ou_id',
    ];

    // Status constants
    const STATUS_RECEIVED = 'received';
    const STATUS_STORED   = 'stored';
    const STATUS_PICKED   = 'picked';
    const STATUS_BROKEN   = 'broken';

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function grnLine()
    {
        return $this->belongsTo(GrnLine::class, 'grn_line_id');
    }

    public function parent()
    {
        return $this->belongsTo(Lpn::class, 'parent_lpn_id');
    }

    public function children()
    {
        return $this->hasMany(Lpn::class, 'parent_lpn_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'lpn_id');
    }

    /**
     * Scope: only active stock (not picked or broken)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PICKED, self::STATUS_BROKEN]);
    }

    /**
     * Scope: filter by warehouse OU
     */
    public function scopeForOu($query, string $ouId)
    {
        return $query->where('ou_id', $ouId);
    }

    /**
     * Generate a unique LPN number in format: LPN-YYYYMMDD-XXXXXX
     */
    public static function generateNumber(): string
    {
        do {
            $number = 'LPN-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('lpn_number', $number)->exists());

        return $number;
    }
}
