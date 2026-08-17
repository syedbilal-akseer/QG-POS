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
        'discounted_price',
        'previous_price',
        'price_changed',
        'price_updated_at',
        'price_updated_by',
        'price_type',
        'start_date_active',
        'end_date_active',
    ];

    protected $casts = [
        'list_price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'price_changed' => 'boolean',
        'price_updated_at' => 'datetime',
        'start_date_active' => 'datetime',
        'end_date_active' => 'datetime',
    ];

    /**
     * Several independent code paths write start_date_active/end_date_active
     * on this table (the sync:oracle-items-price command, the admin "Sync
     * Oracle" button, CSV price import, and the Oracle push-back flow), and
     * more than one of them updates only one of the two date columns on an
     * existing row. That has produced rows where end_date_active ends up
     * earlier than start_date_active — e.g. a row closed out by yesterday's
     * deactivation run, then given a fresh start_date_active by today's
     * price change without its stale end_date_active being cleared. Such a
     * row can never satisfy "start <= today <= end" again, so it silently
     * disappears from every customer-facing price/search query forever,
     * even though it looks like a normal current-priced row in the item_prices
     * table. Rather than patch every write path, treat this as a data
     * invariant: end_date_active may never be before start_date_active.
     */
    protected static function booted(): void
    {
        static::saving(function (ItemPrice $itemPrice) {
            if (
                $itemPrice->start_date_active
                && $itemPrice->end_date_active
                && $itemPrice->end_date_active->lt($itemPrice->start_date_active)
            ) {
                \Log::warning('ItemPrice: dropped inverted end_date_active (was before start_date_active)', [
                    'item_price_id'    => $itemPrice->id,
                    'item_code'        => $itemPrice->item_code,
                    'price_list_id'    => $itemPrice->price_list_id,
                    'price_list_name'  => $itemPrice->price_list_name,
                    'uom'              => $itemPrice->uom,
                    'start_date_active'=> $itemPrice->start_date_active->toDateTimeString(),
                    'rejected_end_date_active' => $itemPrice->end_date_active->toDateTimeString(),
                ]);

                $itemPrice->end_date_active = null;
            }
        });
    }

    /**
     * Get the item associated with the price.
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    /**
     * Get the customers associated with the price list.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'price_list_id', 'price_list_id');
    }

    /**
     * Get the user who updated the price.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'price_updated_by');
    }

    /**
     * Get the price type color for UI highlighting.
     */
    public function getPriceTypeColorAttribute()
    {
        return match(strtolower($this->price_type)) {
            'corporate' => '#3B82F6', // Blue
            'wholesaler' => '#10B981', // Green  
            'hbm' => '#F59E0B', // Amber
            default => '#6B7280', // Gray
        };
    }

    /**
     * Get the price change percentage.
     */
    public function getPriceChangePercentageAttribute()
    {
        if (!$this->previous_price || $this->previous_price == 0) {
            return 0;
        }
        
        return round((($this->list_price - $this->previous_price) / $this->previous_price) * 100, 2);
    }

    /**
     * Get the formatted price change.
     */
    public function getFormattedPriceChangeAttribute()
    {
        if (!$this->price_changed || !$this->previous_price) {
            return null;
        }

        $change = $this->list_price - $this->previous_price;
        $percentage = $this->price_change_percentage;
        $symbol = $change >= 0 ? '+' : '';
        
        return "{$symbol}" . number_format($change, 2) . " ({$symbol}{$percentage}%)";
    }

    /**
     * Scope to get only changed prices.
     */
    public function scopeChanged($query)
    {
        return $query->where('price_changed', true);
    }

    /**
     * Scope to filter by price type.
     */
    public function scopeByPriceType($query, $type)
    {
        return $query->where('price_type', $type);
    }
}
