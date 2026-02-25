<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OracleItemPrice extends Model
{
    protected $connection = 'oracle';
    protected $table = 'apps.qg_pos_item_price';
    
    
    // Oracle doesn't use Laravel's created_at/updated_at by default
    public $timestamps = false;
    
    // Disable primary key for view operations
    public $incrementing = false;
    protected $primaryKey = null;
    
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
    
    protected $casts = [
        'price_list_id' => 'integer',
        'item_id' => 'integer',
        'list_price' => 'decimal:2',
        'start_date_active' => 'datetime',
        'end_date_active' => 'datetime',
    ];

    /**
     * Scope to filter by price list
     */
    public function scopeByPriceList($query, $priceListId)
    {
        return $query->where('price_list_id', $priceListId);
    }

    /**
     * Scope to filter by item code
     */
    public function scopeByItemCode($query, $itemCode)
    {
        return $query->where('item_code', $itemCode);
    }

    /**
     * Scope to get active prices (current date between start and end date)
     */
    public function scopeActive($query)
    {
        $today = now()->format('Y-m-d');
        return $query->where('start_date_active', '<=', $today)
                    ->where(function($q) use ($today) {
                        $q->where('end_date_active', '>=', $today)
                          ->orWhereNull('end_date_active');
                    });
    }

    /**
     * Scope to search by item code or description
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('item_code', 'LIKE', "%{$term}%")
              ->orWhere('item_description', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->list_price, 2);
    }

    /**
     * Check if price is currently active
     */
    public function getIsActiveAttribute()
    {
        $today = now();
        $startDate = $this->start_date_active ? $this->start_date_active->startOfDay() : null;
        $endDate = $this->end_date_active ? $this->end_date_active->endOfDay() : null;

        if (!$startDate) return false;
        
        if ($startDate > $today) return false;
        
        if ($endDate && $endDate < $today) return false;
        
        return true;
    }
}
