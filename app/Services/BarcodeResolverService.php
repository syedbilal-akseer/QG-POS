<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPacking;

/**
 * Resolves a scanned QR / barcode string to an item + packing level.
 *
 * Accepts:
 *   1. JSON payload  →  {"c":"0071-0009","l":"CARTON"}      (Phase 1 default)
 *   2. Plain item code → "0071-0009"                          (returns item + all 4 levels)
 *   3. Supplier barcode → matched against item_packings.supplier_barcode
 *
 * Returns a structured array — never throws on invalid input. The caller
 * inspects `success` and renders accordingly.
 */
class BarcodeResolverService
{
    /**
     * @param string $scanned The raw string from the QR / barcode reader.
     * @return array {
     *   success: bool,
     *   reason: string|null,
     *   item: array|null,
     *   matched_level: int|null,
     *   packing: array|null,           // when a specific level was matched
     *   all_levels: array|null,        // when only the item was matched (no level)
     * }
     */
    public function resolve(string $scanned): array
    {
        $scanned = trim($scanned);

        if ($scanned === '') {
            return $this->fail('Empty scan input.');
        }

        // 1) JSON payload — the canonical Phase 1 format.
        if ($this->looksLikeJson($scanned)) {
            return $this->resolveJson($scanned);
        }

        // 2) Supplier barcode — direct match against the column.
        if ($supplierMatch = ItemPacking::query()
                ->where('supplier_barcode', $scanned)
                ->where('is_active', true)
                ->with('item')
                ->first()) {
            return $this->successForPacking($supplierMatch, 'supplier');
        }

        // 3) Plain item code — return the item with all of its packing levels.
        $item = Item::query()
            ->where('item_code', $scanned)
            ->orWhere('inventory_item_id', $scanned)
            ->first();
        if ($item) {
            return $this->successForItemOnly($item);
        }

        return $this->fail('Code did not match any item or packing level.');
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    protected function looksLikeJson(string $s): bool
    {
        return str_starts_with($s, '{') && str_ends_with($s, '}');
    }

    protected function resolveJson(string $payload): array
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return $this->fail('Malformed JSON payload.');
        }

        $itemCode = isset($data['c']) ? (string) $data['c'] : null;
        $levelKey = isset($data['l']) ? strtoupper((string) $data['l']) : null;

        if (!$itemCode) {
            return $this->fail('Missing "c" (item code) in payload.');
        }

        // Try exact match on item_code + uom_code (level token).
        if ($levelKey) {
            $packing = ItemPacking::query()
                ->where('item_code', $itemCode)
                ->where('uom_code', $levelKey)
                ->where('is_active', true)
                ->with('item')
                ->first();

            if ($packing) {
                return $this->successForPacking($packing, 'json');
            }

            // Fall through: maybe the item exists but the level doesn't —
            // tell the caller what valid levels are available so they can
            // render a useful error.
            $item = Item::where('item_code', $itemCode)->first();
            if ($item) {
                return $this->fail(
                    "Item {$itemCode} found, but level '{$levelKey}' is not configured.",
                    $item
                );
            }

            return $this->fail("No item found with code {$itemCode}.");
        }

        // Item code without level — return all levels.
        $item = Item::where('item_code', $itemCode)->first();
        if (!$item) {
            return $this->fail("No item found with code {$itemCode}.");
        }
        return $this->successForItemOnly($item);
    }

    protected function successForPacking(ItemPacking $packing, string $matchedVia): array
    {
        return [
            'success'       => true,
            'reason'        => null,
            'matched_via'   => $matchedVia,    // 'json' | 'supplier' | 'item_code'
            'matched_level' => $packing->level,
            'item'          => $this->itemPayload($packing->item),
            'packing'       => $this->packingPayload($packing),
            'all_levels'    => null,
        ];
    }

    protected function successForItemOnly(Item $item): array
    {
        $levels = ItemPacking::where('item_code', $item->item_code)
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        return [
            'success'       => true,
            'reason'        => null,
            'matched_via'   => 'item_code',
            'matched_level' => null,
            'item'          => $this->itemPayload($item),
            'packing'       => null,
            'all_levels'    => $levels->map(fn ($p) => $this->packingPayload($p))->all(),
        ];
    }

    protected function fail(string $reason, ?Item $item = null): array
    {
        return [
            'success'       => false,
            'reason'        => $reason,
            'matched_via'   => null,
            'matched_level' => null,
            'item'          => $item ? $this->itemPayload($item) : null,
            'packing'       => null,
            'all_levels'    => null,
        ];
    }

    protected function itemPayload(?Item $item): ?array
    {
        if (!$item) return null;
        return [
            'item_code'         => $item->item_code,
            'inventory_item_id' => $item->inventory_item_id,
            'item_description'  => $item->item_description,
            'primary_uom'       => $item->primary_uom_code,
            'secondary_uom'     => $item->secondary_uom_code,
        ];
    }

    protected function packingPayload(ItemPacking $p): array
    {
        return [
            'level'              => $p->level,
            'level_code'         => $p->level_code,
            'uom_code'           => $p->uom_code,
            'uom_label'          => $p->uom_label,
            'conversion_to_base' => $p->conversion_to_base !== null ? (float) $p->conversion_to_base : null,
            'barcode_payload'    => $p->barcode_payload,
            'supplier_barcode'   => $p->supplier_barcode,
        ];
    }
}
