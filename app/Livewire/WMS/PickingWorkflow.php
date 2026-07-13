<?php

namespace App\Livewire\WMS;

use Livewire\Component;
use App\Models\WMS\Lpn;
use App\Models\WMS\Location;
use App\Models\WMS\Transaction;
use Illuminate\Support\Facades\DB;

class PickingWorkflow extends Component
{
    public int    $step       = 1;   // 1=scan bin, 2=scan LPN, 3=confirm
    public string $scannedBin = '';
    public string $scannedLpn = '';

    public ?array $activeBin = null;
    public ?array $activeLpn = null;

    public float  $pickQty   = 0;
    public string $message   = '';
    public string $status    = '';   // success | error | warning

    // FIFO/FEFO block details shown to picker
    public ?array $blockDetails = null;

    // ── Step 1: Scan Bin ──────────────────────────────────────────────────
    public function updatedScannedBin(string $value): void
    {
        $value = trim($value);
        if (!$value) return;

        $location = Location::where('location_code', $value)->first();

        if (!$location) {
            $this->message = "Bin not found: {$value}";
            $this->status  = 'error';
            return;
        }

        $this->activeBin = [
            'id'            => $location->id,
            'location_code' => $location->location_code,
        ];
        $this->step    = 2;
        $this->message = "Bin verified: {$location->location_code}. Scan LPN.";
        $this->status  = 'success';
    }

    // ── Step 2: Scan LPN ──────────────────────────────────────────────────
    public function updatedScannedLpn(string $value): void
    {
        $value = trim($value);
        if (!$value || $this->step !== 2) return;

        $lpn = Lpn::with('grnLine.grn')
            ->where('lpn_number', $value)
            ->where('location_id', $this->activeBin['id'])
            ->where('status', Lpn::STATUS_STORED)
            ->first();

        if (!$lpn) {
            $this->message = "LPN not found in bin {$this->activeBin['location_code']} or not stored.";
            $this->status  = 'error';
            return;
        }

        // ── FEFO Check: is there an older-expiry lot for the same item in this bin?
        if ($lpn->expiry_date) {
            $fefoBlock = Lpn::where('location_id', $this->activeBin['id'])
                ->where('item_code', $lpn->item_code)
                ->where('status', Lpn::STATUS_STORED)
                ->where('expiry_date', '<', $lpn->expiry_date)
                ->where('quantity', '>', 0)
                ->orderBy('expiry_date')
                ->first();

            if ($fefoBlock) {
                $this->message = "FEFO BLOCK — Older expiry lot exists in this bin. Pick {$fefoBlock->lpn_number} (EXP: {$fefoBlock->expiry_date}) first.";
                $this->status  = 'error';
                $this->blockDetails = [
                    'type'       => 'FEFO',
                    'lpn_number' => $fefoBlock->lpn_number,
                    'expiry'     => $fefoBlock->expiry_date,
                    'qty'        => $fefoBlock->quantity,
                ];
                return;
            }
        }

        // ── FIFO Check: is there an older GRN-date stock for the same item in this warehouse?
        $lpnGrnDate = $lpn->grnLine?->grn?->received_date;
        if ($lpnGrnDate) {
            $ouId = $lpn->ou_id;

            $fifoQuery = Lpn::with('grnLine.grn')
                ->where('item_code', $lpn->item_code)
                ->where('status', Lpn::STATUS_STORED)
                ->where('quantity', '>', 0)
                ->where('id', '!=', $lpn->id);

            if ($ouId) {
                $fifoQuery->where('ou_id', $ouId);
            }

            $fifoBlock = $fifoQuery->get()->filter(function ($candidate) use ($lpnGrnDate) {
                $cDate = $candidate->grnLine?->grn?->received_date;
                return $cDate && $cDate < $lpnGrnDate;
            })->sortBy(fn($c) => $c->grnLine?->grn?->received_date)->first();

            if ($fifoBlock) {
                $fifoGrnDate = \Carbon\Carbon::parse($fifoBlock->grnLine?->grn?->received_date)->format('d M Y');
                $myGrnDate   = \Carbon\Carbon::parse($lpnGrnDate)->format('d M Y');
                $this->message = "FIFO WARNING — Stock received on {$fifoGrnDate} exists (LPN: {$fifoBlock->lpn_number}). Current LPN received {$myGrnDate}. Pick older GRN stock first.";
                $this->status  = 'warning';
                $this->blockDetails = [
                    'type'       => 'FIFO',
                    'lpn_number' => $fifoBlock->lpn_number,
                    'grn_date'   => $fifoGrnDate,
                    'location'   => $fifoBlock->location?->location_code ?? '—',
                    'qty'        => $fifoBlock->quantity,
                ];
                // FIFO is a warning (not hard block) — picker can override
                // Still load the LPN so they can proceed if they acknowledge
                $this->activeLpn = $this->lpnToArray($lpn);
                $this->pickQty   = $lpn->quantity;
                $this->step      = 3;
                return;
            }
        }

        // ── All clear ──
        $this->activeLpn    = $this->lpnToArray($lpn);
        $this->pickQty      = $lpn->quantity;
        $this->blockDetails = null;
        $this->step         = 3;
        $this->message      = "LPN verified: {$lpn->lpn_number}. Confirm quantity.";
        $this->status       = 'success';
    }

    // ── Step 3: Confirm Pick ──────────────────────────────────────────────
    public function confirmPick(): void
    {
        if (!$this->activeLpn || !$this->activeBin) {
            $this->message = 'Session expired. Please restart.';
            $this->status  = 'error';
            return;
        }

        if ($this->pickQty <= 0) {
            $this->message = 'Quantity must be greater than zero.';
            $this->status  = 'error';
            return;
        }

        if ($this->pickQty > $this->activeLpn['quantity']) {
            $this->message = "Cannot pick more than available ({$this->activeLpn['quantity']}).";
            $this->status  = 'error';
            return;
        }

        DB::transaction(function () {
            $lpn = Lpn::findOrFail($this->activeLpn['id']);

            Transaction::create([
                'lpn_id'           => $lpn->id,
                'item_code'        => $lpn->item_code,
                'transaction_type' => 'picking',
                'from_location_id' => $this->activeBin['id'],
                'quantity'         => $this->pickQty,
                'user_id'          => auth()->id(),
                'reference'        => 'Order picking — ' . ($this->blockDetails ? 'FIFO override acknowledged' : 'clean pick'),
            ]);

            $newQty = $lpn->quantity - $this->pickQty;
            if ($newQty <= 0) {
                $lpn->update(['status' => Lpn::STATUS_PICKED, 'location_id' => null, 'quantity' => 0]);
            } else {
                $lpn->update(['quantity' => $newQty]);
            }
        });

        $this->resetWorkflow();
        $this->message = 'Pick confirmed successfully.';
        $this->status  = 'success';
    }

    public function resetWorkflow(): void
    {
        $this->reset(['scannedBin', 'scannedLpn', 'activeBin', 'activeLpn', 'pickQty', 'blockDetails']);
        $this->step   = 1;
        $this->status = '';
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function lpnToArray(Lpn $lpn): array
    {
        return [
            'id'             => $lpn->id,
            'lpn_number'     => $lpn->lpn_number,
            'item_code'      => $lpn->item_code,
            'system_sub_lot' => $lpn->system_sub_lot,
            'lot_number'     => $lpn->lot_number,
            'expiry_date'    => $lpn->expiry_date,
            'mfg_date'       => $lpn->mfg_date,
            'quantity'       => $lpn->quantity,
            'uom'            => $lpn->uom,
            'ou_id'          => $lpn->ou_id,
            'grn_number'     => $lpn->grnLine?->grn?->grn_number,
            'grn_date'       => $lpn->grnLine?->grn?->received_date?->format('d M Y'),
        ];
    }

    public function render()
    {
        return view('livewire.w-m-s.picking-workflow');
    }
}
