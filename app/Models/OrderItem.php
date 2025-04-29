<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'inventory_item_id',
        'warehouse_id',
        'quantity',
        'ob_quantity', // oracle quantity
        'discount',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'inventory_item_id', 'inventory_item_id');
    }

    public function syncHistory()
    {
        return $this->hasMany(OrderSyncHistory::class, 'item_id', 'id');
    }
}
