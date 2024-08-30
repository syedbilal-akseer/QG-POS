<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id',
        'price_list_name',
        'item_id',
        'item_code',
        'item_description',
        'uom',
        'list_price',
        'start_date_active',
        'end_date_active',
    ];

    /**
     * Get the item associated with the price.
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'inventory_item_id');
    }

    /**
     * Get the customers associated with the price list.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'price_list_id', 'price_list_id');
    }
}
