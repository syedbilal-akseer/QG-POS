<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'ou_id',
        'ou_name',
        'customer_name',
        'customer_number',
        'customer_id',
        'customer_site_id',
        'salesperson',
        'city',
        'area',
        'address1',
        'contact_number',
        'email_address',
        'overall_credit_limit',
        'credit_days',
        'nic',
        'ntn',
        'sales_tax_registration_num',
        'category_code',
        'creation_date',
        'price_list_id',
        'price_list_name',
    ];

    /**
     * Get the orders for the customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the item prices associated with the customer's price list.
     */
    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class, 'price_list_id', 'price_list_id');
    }

    /**
     * Get the items associated with the customer's price list.
     */
    public function items()
    {
        return $this->hasManyThrough(
            Item::class,
            ItemPrice::class,
            'price_list_id',      // Foreign key on item_prices (Price list ID that matches customer’s price list)
            'inventory_item_id',  // Foreign key on items (Links to item_id in item_prices)
            'price_list_id',      // Local key on customers (Matches with price_list_id in item_prices)
            'item_id'             // Local key on item_prices (Matches with inventory_item_id in items)
        )->select('items.*', 'item_prices.list_price as item_price', 'item_prices.uom as item_uom');
    }

    // /**
    //  * Get the item prices associated with the customer's price list.
    //  */
    // public function itemPrices()
    // {
    //     return $this->hasMany(ItemPrice::class, 'price_list_id', 'price_list_id');
    // }

    // /**
    //  * Get the items associated with the customer's price list.
    //  */
    // public function items()
    // {
    //     return $this->hasManyThrough(Item::class, ItemPrice::class, 'price_list_id', 'inventory_item_id', 'price_list_id', 'item_id');
    // }
}
