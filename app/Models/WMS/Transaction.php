<?php

namespace App\Models\WMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'wms_transactions';
    protected $fillable = ['lpn_id', 'item_code', 'transaction_type', 'from_location_id', 'to_location_id', 'quantity', 'user_id', 'reference'];

    public function lpn()
    {
        return $this->belongsTo(Lpn::class, 'lpn_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
