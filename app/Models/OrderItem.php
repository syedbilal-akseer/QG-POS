<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'inventory_item_id',
        'warehouse_id',
        'quantity',
        'ob_quantity', // oracle quantity
        'price',       // Unit price (REQUIRED for discount calculation)
        'uom',         // Unit of measure
        'sub_total',   // Subtotal after discount
        'discount',    // Discount amount
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'inventory_item_id', 'inventory_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'organization_id');
    }

    public function syncHistory()
    {
        return $this->hasMany(OrderSyncHistory::class, 'item_id', 'id');
    }
}
