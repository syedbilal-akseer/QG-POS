<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemPacking;
use Illuminate\Http\Request;

/**
 * Print-friendly page that renders QR codes for an item's packing levels.
 *
 * QR rendering happens CLIENT-SIDE via the qrcode-generator JS library
 * (loaded from a CDN) — so no PHP/composer dependency is needed and the
 * generated QR can be printed crisply at any size.
 */
class QrLabelController extends Controller
{
    /**
     * GET /admin/items/{itemCode}/qr-labels
     *
     * Renders an HTML page with QR codes for ALL packing levels of an item.
     * Open in a browser → Ctrl+P / Cmd+P → print.
     */
    public function showItem(Request $request, string $itemCode)
    {
        $item     = Item::where('item_code', $itemCode)->firstOrFail();
        $packings = ItemPacking::where('item_code', $itemCode)
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        return view('admin.qr-labels.item', [
            'item'     => $item,
            'packings' => $packings,
        ]);
    }

    /**
     * GET /admin/items/qr-labels?codes=0071-0009,0070-0001,...
     *
     * Bulk QR sheet — print all packing levels for many items at once.
     */
    /**
     * GET /admin/items/qr-labels/search-items?q=...
     *
     * Lightweight search-as-you-type endpoint for the Add Item combobox.
     * Returns up to 30 matches by item_code OR item_description (case-insensitive).
     */
    public function searchItems(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Item::query()
            ->select('item_code', 'item_description')
            ->whereNotNull('item_code');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('item_code', 'like', "%{$q}%")
                  ->orWhere('item_description', 'like', "%{$q}%");
            });
        }

        $items = $query
            ->orderBy('item_code')
            ->limit(30)
            ->get();

        return response()->json([
            'items' => $items->map(fn ($i) => [
                'item_code'        => $i->item_code,
                'item_description' => $i->item_description,
            ])->values(),
        ]);
    }

    /**
     * POST /admin/items/qr-labels/store
     *
     * Add packing definitions for a single item via the UI.
     * Replaces existing rows if the item already has packings.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code'         => 'required|string|max:64|exists:items,item_code',
            'primary_uom'       => 'required|string|max:32',
            'secondary_uom'     => 'nullable|string|max:32',
            'primary_packing'   => 'nullable|string|max:32',
            'secondary_packing' => 'nullable|string|max:32',
            'secondary_to_primary' => 'nullable|numeric|min:0',
            'packing_to_secondary' => 'nullable|numeric|min:0',
            'carton_to_packing'    => 'nullable|numeric|min:0',
        ]);

        $itemCode = $validated['item_code'];

        // Compute cumulative conversion-to-base for each level (nullable if SC
        // hasn't supplied the factor yet — same logic as the artisan import).
        $convL2 = $validated['secondary_to_primary'] ?? null;
        $convL3 = ($convL2 !== null && isset($validated['packing_to_secondary']))
            ? $convL2 * (float) $validated['packing_to_secondary'] : null;
        $convL4 = ($convL3 !== null && isset($validated['carton_to_packing']))
            ? $convL3 * (float) $validated['carton_to_packing'] : null;

        $defs = [
            [ItemPacking::LEVEL_PRIMARY_UOM,       'PRIMARY_UOM',       $validated['primary_uom'],       1.0],
            [ItemPacking::LEVEL_SECONDARY_UOM,     'SECONDARY_UOM',     $validated['secondary_uom']     ?? null, $convL2],
            [ItemPacking::LEVEL_PRIMARY_PACKING,   'PRIMARY_PACKING',   $validated['primary_packing']   ?? null, $convL3],
            [ItemPacking::LEVEL_SECONDARY_PACKING, 'SECONDARY_PACKING', $validated['secondary_packing'] ?? null, $convL4],
        ];

        // Wipe any existing packings for this item so re-saves are clean.
        ItemPacking::where('item_code', $itemCode)->delete();

        foreach ($defs as [$level, $levelCode, $uomLabel, $conversion]) {
            if (!$uomLabel) continue;
            $uomCode = strtoupper(trim($uomLabel));

            ItemPacking::create([
                'item_code'          => $itemCode,
                'level'              => $level,
                'level_code'         => $levelCode,
                'uom_code'           => $uomCode,
                'uom_label'          => $uomLabel,
                'conversion_to_base' => $conversion,
                'barcode_payload'    => ItemPacking::makeBarcodePayload($itemCode, $uomCode),
                'is_active'          => true,
            ]);
        }

        return redirect()
            ->route('items.qr-labels.bulk')
            ->with('success', "Packing definitions saved for {$itemCode}.");
    }

    public function bulk(Request $request)
    {
        $codes = collect(explode(',', (string) $request->query('codes', '')))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // No codes selected → show the picker page (lists all items that have
        // packings configured + a search box and checkboxes).
        if (empty($codes)) {
            $configuredItems = Item::query()
                ->whereIn('item_code', ItemPacking::query()->distinct()->pluck('item_code'))
                ->orderBy('item_code')
                ->get(['item_code', 'item_description']);

            return view('admin.qr-labels.picker', [
                'items' => $configuredItems,
            ]);
        }

        $packings = ItemPacking::whereIn('item_code', $codes)
            ->where('is_active', true)
            ->orderBy('item_code')
            ->orderBy('level')
            ->get()
            ->groupBy('item_code');

        $items = Item::whereIn('item_code', $codes)
            ->get()
            ->keyBy('item_code');

        return view('admin.qr-labels.bulk', [
            'packings' => $packings,
            'items'    => $items,
        ]);
    }
}
