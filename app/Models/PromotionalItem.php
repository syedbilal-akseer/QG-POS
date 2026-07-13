<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'price_list_name',
        'image_path',
        'original_price',
        'discounted_price',
        'is_active',
        'buy_qty',
        'free_qty',
        'free_item_code',
        'scheme_start_date',
        'scheme_end_date',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'original_price'    => 'decimal:2',
        'discounted_price'  => 'decimal:2',
        'buy_qty'           => 'integer',
        'free_qty'          => 'integer',
        'scheme_start_date' => 'date',
        'scheme_end_date'   => 'date',
    ];

    /**
     * The promoted item (purchased item).
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    /**
     * The free item, when the scheme awards a DIFFERENT item.
     * (When free_item_code is null, the free item is the same as `item`.)
     */
    public function freeItem()
    {
        return $this->belongsTo(Item::class, 'free_item_code', 'item_code');
    }

    /**
     * Is the scheme currently active (date window + is_active flag)?
     */
    public function isSchemeActive(): bool
    {
        if (!$this->is_active) return false;
        $today = now()->toDateString();
        if ($this->scheme_start_date && $this->scheme_start_date->toDateString() > $today) return false;
        if ($this->scheme_end_date   && $this->scheme_end_date->toDateString()   < $today) return false;
        return true;
    }
}
