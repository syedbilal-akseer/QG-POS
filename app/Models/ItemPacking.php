<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPacking extends Model
{
    public const LEVEL_PRIMARY_UOM       = 1; // base measurement (e.g. KG)
    public const LEVEL_SECONDARY_UOM     = 2; // individual unit  (e.g. TB / BTL)
    public const LEVEL_PRIMARY_PACKING   = 3; // small pack       (e.g. BOX)
    public const LEVEL_SECONDARY_PACKING = 4; // large pack       (e.g. CARTON)

    public const LEVEL_CODES = [
        self::LEVEL_PRIMARY_UOM       => 'PRIMARY_UOM',
        self::LEVEL_SECONDARY_UOM     => 'SECONDARY_UOM',
        self::LEVEL_PRIMARY_PACKING   => 'PRIMARY_PACKING',
        self::LEVEL_SECONDARY_PACKING => 'SECONDARY_PACKING',
    ];

    protected $fillable = [
        'item_code',
        'level',
        'level_code',
        'uom_code',
        'uom_label',
        'conversion_to_base',
        'barcode_payload',
        'supplier_barcode',
        'is_active',
    ];

    protected $casts = [
        'level'              => 'integer',
        'conversion_to_base' => 'decimal:6',
        'is_active'          => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    /**
     * Build the JSON QR payload for an item × level pair.
     * Format: {"c":"<item_code>","l":"<UOM_CODE>"}
     */
    public static function makeBarcodePayload(string $itemCode, string $uomCode): string
    {
        return json_encode(
            ['c' => $itemCode, 'l' => strtoupper($uomCode)],
            JSON_UNESCAPED_SLASHES
        );
    }
}
