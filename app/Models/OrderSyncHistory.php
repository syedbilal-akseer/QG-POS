<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSyncHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_id',
        'previous_quantity',
        'new_quantity',
        'synced_at',
    ];
}
