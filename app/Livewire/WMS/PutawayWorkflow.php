<?php

namespace App\Livewire\WMS;

use Livewire\Component;
use App\Models\WMS\Lpn;
use App\Models\WMS\Location;
use App\Models\WMS\Transaction;
use Illuminate\Support\Facades\DB;

class PutawayWorkflow extends Component
{
    public int    $step       = 1;   // 1=scan LPN, 2=scan bin, 3=confirm
    public string $scannedLpn = '';
    public string $scannedBin = '';

    public ?array $activeLpn = null;
    public ?array $activeBin = null;

    public string $message = '';
    public string $status  = '';  // success | error | warning

    // ── Step 1: Scan LPN ──────────────────────────────────────────────────
    public function updatedScannedLpn(string $value): void
    {
        $value = trim($value);
        if (!$value) return;

        $lpn = Lpn::with(['grnLine.grn', 'location'])->where('lpn_number', $value)->first();

        if (!$lpn) {
            $this->message = "LPN not found: {$value}";
            $this->status  = 'error';
            return;
        }

        if ($lpn->status === Lpn::STATUS_STORED && $lpn->location_id) {
            $this->message = "LPN {$value} is already stored at {$lpn->location->location_code}. Use Relocate to move it.";
            $this->status  = 'warning';
            return;
        }

        if (!in_array($lpn->status, [Lpn::STATUS_RECEIVED, Lpn::STATUS_STORED])) {
            $this->message = "LPN status is '{$lpn->status}' — cannot put away.";
            $this->status  = 'error';
            return;
        }

        $this->activeLpn = [
            'id'             => $lpn->id,
            'lpn_number'     => $lpn->lpn_number,
            'item_code'      => $lpn->item_code,
            'system_sub_lot' => $lpn->system_sub_lot,
            'lot_number'     => $lpn->lot_number,
            'mfg_date'       => $lpn->mfg_date,
            'expiry_date'    => $lpn->expiry_date,
            'quantity'       => $lpn->quantity,
            'uom'            => $lpn->uom,
            'ou_id'          => $lpn->ou_id,
            'grn_number'     => $lpn->grnLine?->grn?->grn_number,
            'grn_date'       => $lpn->grnLine?->grn?->received_date?->format('d M Y'),
            'cost_price'     => $lpn->grnLine?->cost_price,
            'current_location' => $lpn->location?->location_code,
        ];
        $this->step    = 2;
        $this->message = "LPN verified: {$lpn->lpn_number} — {$lpn->item_code}. Now scan destination bin.";
        $this->status  = 'success';
    }

    // ── Step 2: Scan Bin ──────────────────────────────────────────────────
    public function updatedScannedBin(string $value): void
    {
        $value = trim($value);
        if (!$value || $this->step !== 2) return;

        $location = Location::where('location_code', $value)
            ->whereIn('type', ['bin', 'rack'])
            ->first();

        if (!$location) {
            $this->message = "Bin/Rack not found: {$value}. Scan a valid bin QR.";
            $this->status  = 'error';
            return;
        }

        $this->activeBin = [
            'id'            => $location->id,
            'location_code' => $location->location_code,
            'type'          => $location->type,
            'description'   => $location->description,
        ];
        $this->step    = 3;
        $this->message = "Bin verified: {$location->location_code}. Confirm put-away.";
        $this->status  = 'success';
    }

    // ── Step 3: Confirm ───────────────────────────────────────────────────
    public function confirmPutaway(): void
    {
        if (!$this->activeLpn || !$this->activeBin) {
            $this->message = 'Session expired. Please restart.';
            $this->status  = 'error';
            return;
        }

        DB::transaction(function () {
            $lpn = Lpn::findOrFail($this->activeLpn['id']);

            $fromLocationId = $lpn->location_id;

            $lpn->update([
                'location_id' => $this->activeBin['id'],
                'status'      => Lpn::STATUS_STORED,
            ]);

            Transaction::create([
                'lpn_id'           => $lpn->id,
                'item_code'        => $lpn->item_code,
                'transaction_type' => 'put_away',
                'from_location_id' => $fromLocationId,
                'to_location_id'   => $this->activeBin['id'],
                'quantity'         => $lpn->quantity,
                'user_id'          => auth()->id(),
                'reference'        => 'Put-away to ' . $this->activeBin['location_code'],
            ]);
        });

        $binCode = $this->activeBin['location_code'];
        $lpnNum  = $this->activeLpn['lpn_number'];

        $this->resetWorkflow();
        $this->message = "Put-away confirmed: {$lpnNum} → {$binCode}";
        $this->status  = 'success';
    }

    public function resetWorkflow(): void
    {
        $this->reset(['step', 'scannedLpn', 'scannedBin', 'activeLpn', 'activeBin', 'message', 'status']);
        $this->step = 1;
    }

    public function render()
    {
        return view('livewire.w-m-s.putaway-workflow');
    }
}
