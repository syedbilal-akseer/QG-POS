<?php

namespace App\Models\WMS;

use Illuminate\Database\Eloquent\Model;

class WmsItemUomRule extends Model
{
    protected $table = 'wms_item_uom_rules';

    protected $fillable = [
        'item_code',
        'uom_level',
        'qty_per_parent',
        'barcode_prefix',
        'label_size',
    ];

    protected $casts = [
        'qty_per_parent' => 'decimal:4',
    ];

    /**
     * Get the full UOM hierarchy for an item, ordered from largest to smallest.
     * Returns: pallet → carton → box → unit
     */
    public static function hierarchyFor(string $itemCode): \Illuminate\Support\Collection
    {
        $order = ['pallet' => 1, 'carton' => 2, 'box' => 3, 'unit' => 4];
        return static::where('item_code', $itemCode)
            ->get()
            ->sortBy(fn($r) => $order[$r->uom_level] ?? 99);
    }

    /**
     * How many base units are in one of this UOM level.
     * e.g. "carton" = qty_per_parent * "box" qty_per_parent * ... until "unit"
     */
    public static function unitsIn(string $itemCode, string $uomLevel): float
    {
        $hierarchy = static::hierarchyFor($itemCode);
        $levels = $hierarchy->pluck('qty_per_parent', 'uom_level')->toArray();

        $order = ['pallet', 'carton', 'box', 'unit'];
        $start = array_search($uomLevel, $order);
        if ($start === false) return 1;

        $multiplier = 1;
        for ($i = $start; $i < count($order) - 1; $i++) {
            $level = $order[$i];
            if (isset($levels[$order[$i + 1]])) {
                $multiplier *= $levels[$order[$i + 1]];
            }
        }
        return $multiplier;
    }

    /**
     * Get the child UOM level (next level down in hierarchy).
     */
    public static function childUomOf(string $uomLevel): ?string
    {
        $map = ['pallet' => 'carton', 'carton' => 'box', 'box' => 'unit'];
        return $map[$uomLevel] ?? null;
    }
}
