<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarcodeScanLog;
use App\Models\Item;
use App\Models\ItemPacking;
use App\Services\BarcodeResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BarcodeController extends Controller
{
    public function __construct(protected BarcodeResolverService $resolver) {}

    /**
     * Resolve a scanned QR / barcode.
     *
     * POST /api/scan/resolve
     * Body: { "code": "<scanned string>" }
     *
     * The scanned string can be:
     *   - The Phase 1 JSON QR payload:  {"c":"0071-0009","l":"CARTON"}
     *   - A bare item code:             "0071-0009"   (returns all 4 levels)
     *   - A pre-printed supplier barcode (matched against item_packings.supplier_barcode)
     *
     * Every scan is logged to barcode_scan_logs whether it succeeded or not.
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:1024',
        ]);

        $raw    = (string) $request->input('code');
        $result = $this->resolver->resolve($raw);

        // Log every scan — feeds the "capture learnings" feedback loop.
        BarcodeScanLog::create([
            'user_id'           => Auth::id(),
            'raw_input'         => mb_substr($raw, 0, 1024),
            'matched_item_code' => $result['item']['item_code'] ?? null,
            'matched_level'     => $result['matched_level']     ?? null,
            'success'           => (bool) $result['success'],
            'failure_reason'    => $result['reason'] ?? null,
            'device'            => $request->header('X-Device-Id'),
            'app_version'       => $request->header('X-App-Version'),
        ]);

        return response()->json([
            'success' => (bool) $result['success'],
            'status'  => $result['success'] ? 200 : 404,
            'message' => $result['success']
                ? ($result['matched_level']
                    ? 'Code resolved to a specific packing level.'
                    : 'Code resolved to an item — pick a level to proceed.')
                : ($result['reason'] ?? 'Could not resolve scanned code.'),
            'data' => $result,
        ], $result['success'] ? 200 : 404);
    }

    /**
     * Return the QR JSON payload + metadata for one item × level (for the
     * mobile app to render the QR client-side).
     *
     * GET /api/scan/qr/{itemCode}/{level?}
     *   level (optional): 1..4   or   PRIMARY_UOM | SECONDARY_UOM | PRIMARY_PACKING | SECONDARY_PACKING
     *
     * If no level is given, returns all 4 levels.
     */
    public function qr(Request $request, string $itemCode, ?string $level = null): JsonResponse
    {
        $query = ItemPacking::where('item_code', $itemCode)->where('is_active', true);

        if ($level !== null) {
            if (is_numeric($level)) {
                $query->where('level', (int) $level);
            } else {
                $query->where('level_code', strtoupper($level));
            }
        }

        $rows = $query->orderBy('level')->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'No packing levels found for this item.',
            ], 404);
        }

        $item = Item::where('item_code', $itemCode)->first();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'QR payloads retrieved.',
            'data' => [
                'item' => $item ? [
                    'item_code'        => $item->item_code,
                    'item_description' => $item->item_description,
                ] : ['item_code' => $itemCode, 'item_description' => null],
                'qr_codes' => $rows->map(fn ($p) => [
                    'level'              => $p->level,
                    'level_code'         => $p->level_code,
                    'uom_code'           => $p->uom_code,
                    'uom_label'          => $p->uom_label,
                    'qr_payload'         => $p->barcode_payload,    // the JSON string the QR encodes
                    'conversion_to_base' => $p->conversion_to_base !== null ? (float) $p->conversion_to_base : null,
                ])->all(),
            ],
        ], 200);
    }
}
