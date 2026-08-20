<?php

namespace App\Livewire\Pos;

use App\Models\WMS\CycleCount;
use App\Models\WMS\CycleCountLine;
use App\Models\WMS\Location;
use App\Models\WMS\Lpn;
use App\Services\BarcodeResolverService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Scan a location, review what the portal's own on-hand (wms_lpns) says
 * should be there, correct counts, submit. This compares against the
 * portal's local on-hand, not Oracle's — until qg_pos_onhand_by_locator_v
 * exists on the Oracle side, this is the only on-hand record available
 * (see the Scan & Sync Roadmap doc). Submitting records the count for
 * review; it does not silently rewrite LPN quantities.
 */
class CycleCountWorkflow extends Component
{
    public string $scannedLocation = '';
    public ?array $location = null;

    public string $scannedItem = '';
    public string $scanError = '';

    // item_code => ['item_code','item_description','uom','system_qty','counted_qty']
    public array $lines = [];

    public string $notes = '';
    public string $message = '';
    public string $status = '';

    public function updatedScannedLocation(string $value): void
    {
        $value = trim($value);
        if (! $value) {
            return;
        }

        $location = Location::where('location_code', $value)->first();

        if (! $location) {
            $this->message = "Location not found: {$value}";
            $this->status = 'error';
            return;
        }

        $this->location = [
            'id' => $location->id,
            'location_code' => $location->location_code,
            'type' => $location->type,
        ];

        $this->lines = Lpn::active()
            ->where('location_id', $location->id)
            ->selectRaw('item_code, uom, SUM(quantity) as qty')
            ->groupBy('item_code', 'uom')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->item_code => [
                    'item_code' => $row->item_code,
                    'uom' => $row->uom,
                    'system_qty' => (float) $row->qty,
                    'counted_qty' => (float) $row->qty,
                ]];
            })
            ->all();

        $this->message = "Location verified: {$location->location_code}. " . count($this->lines) . ' item(s) expected here.';
        $this->status = 'success';
    }

    public function updatedScannedItem(string $value): void
    {
        $value = trim($value);
        $this->scannedItem = '';
        if (! $value || ! $this->location) {
            return;
        }

        $this->scanError = '';

        if (isset($this->lines[$value])) {
            // Already listed — nothing to add, counter adjusts the qty field directly.
            return;
        }

        $resolved = app(BarcodeResolverService::class)->resolve($value);
        if (! $resolved['success'] || ! $resolved['item']) {
            $this->scanError = $resolved['reason'] ?? 'Barcode not recognised.';
            return;
        }

        $itemCode = $resolved['item']['item_code'];

        if (isset($this->lines[$itemCode])) {
            return;
        }

        // Found on the shelf but the portal had no LPN record of it at this
        // location — a genuine unexpected-stock line, system_qty is 0.
        $this->lines[$itemCode] = [
            'item_code' => $itemCode,
            'uom' => $resolved['item']['primary_uom'] ?? '',
            'system_qty' => 0.0,
            'counted_qty' => 1.0,
        ];
    }

    public function updateCounted(string $itemCode, $value): void
    {
        if (! isset($this->lines[$itemCode])) {
            return;
        }
        $this->lines[$itemCode]['counted_qty'] = max(0, (float) $value);
    }

    public function removeLine(string $itemCode): void
    {
        unset($this->lines[$itemCode]);
    }

    public function getVarianceCountProperty(): int
    {
        return collect($this->lines)
            ->filter(fn ($l) => (float) $l['counted_qty'] !== (float) $l['system_qty'])
            ->count();
    }

    public function submitCount(): void
    {
        if (! $this->location || empty($this->lines)) {
            $this->message = 'Nothing to submit — scan a location first.';
            $this->status = 'error';
            return;
        }

        $locationId = $this->location['id'];
        $locationCode = $this->location['location_code'];
        $lines = $this->lines;
        $notes = $this->notes;

        DB::transaction(function () use ($locationId, $lines, $notes) {
            $cycleCount = CycleCount::create([
                'location_id' => $locationId,
                'counted_by' => auth()->id(),
                'status' => 'open',
                'notes' => $notes !== '' ? $notes : null,
            ]);

            foreach ($lines as $line) {
                CycleCountLine::create([
                    'cycle_count_id' => $cycleCount->id,
                    'item_code' => $line['item_code'],
                    'system_qty' => $line['system_qty'],
                    'counted_qty' => $line['counted_qty'],
                    'variance' => $line['counted_qty'] - $line['system_qty'],
                ]);
            }
        });

        $this->message = "Cycle count submitted for {$locationCode} — " . count($lines) . ' item(s) recorded, ' . $this->varianceCount . ' with variance.';
        $this->status = 'success';
        $this->reset(['scannedLocation', 'location', 'lines', 'notes']);
    }

    public function render()
    {
        return view('livewire.pos.cycle-count-workflow');
    }
}
