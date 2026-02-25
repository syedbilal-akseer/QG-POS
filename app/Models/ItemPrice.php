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
        'previous_price' => 'decimal:2',
        'price_changed' => 'boolean',
        'price_updated_at' => 'datetime',
        'start_date_active' => 'datetime',
        'end_date_active' => 'datetime',
    ];

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
