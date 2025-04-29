<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'item_code',
        'item_description',
        'primary_uom_code',
        'secondary_uom_code',
        'major_category',
        'minor_category',
        'sub_minor_category',
    ];

    /**
     * Get the item prices associated with the item.
     */
    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class, 'item_id', 'inventory_item_id');
    }

    /**
     * Get the item prices associated with the item.
     */
    public function itemPrice()
    {
        return $this->belongsTo(ItemPrice::class, 'inventory_item_id', 'item_id');
    }
}
