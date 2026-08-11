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
     * Exclude packing-material items (cartons, labels, caps, etc.) — these are
     * not sellable products and should never appear in the mobile ordering flow.
     */
    public function scopeExcludePackingMaterial($query)
    {
        return $query->where(function ($q) {
            $q->where('major_category', '!=', 'PACKING MATERIAL')
              ->orWhereNull('major_category');
        })->where(function ($q) {
            $q->where('minor_category', '!=', 'PACKING MATERIAL')
              ->orWhereNull('minor_category');
        });
    }

    /**
     * Get the item prices associated with the item.
     */
    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class, 'item_code', 'item_code');
    }

    /**
     * Multi-level packing definitions for this item (KG / TB / BOX / CARTON).
     */
    public function packings()
    {
        return $this->hasMany(ItemPacking::class, 'item_code', 'item_code')
            ->orderBy('level');
    }

    /**
     * Get the item prices associated with the item.
     */
    public function itemPrice()
    {
        return $this->belongsTo(ItemPrice::class, 'item_code', 'item_code');
    }

    /**
     * Get the promotional item associated with the item.
     */
    public function promotionalItem()
    {
        return $this->hasOne(PromotionalItem::class, 'item_code', 'item_code');
    }
}