<?php

namespace App\Livewire\WMS;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\WMS\Lpn;
use App\Models\WMS\Location;
use App\Models\WMS\Transaction;
use App\Models\WMS\WmsItemUomRule;

class LpnManager extends Component
{
    public $lpns        = [];
    public $search      = '';
    public $filter_uom  = '';
    public $selectedLpn = null;

    // Break-bulk
    public $showBreakModal      = false;
    public $breakLpnId          = null;
    public $breakLpnData        = null;
    public $breakChildUom       = null;
    public $breakQtyPerParent   = null;
    public $breakPreviewCount   = 0;

    // Relocate
    public $showRelocateModal   = false;
    public $relocateLpnId       = null;
    public $relocateNewBin      = '';

    public function mount()
    {
        $this->loadLpns();
    }

    public function updatedSearch()
    {
        $this->loadLpns();
    }

    public function loadLpns()
    {
        $query = Lpn::with(['location', 'grnLine.grn'])
            ->active();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('lpn_number', 'like', '%' . $this->search . '%')
                  ->orWhere('item_code', 'like', '%' . $this->search . '%')
                  ->orWhere('system_sub_lot', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filter_uom) {
            $query->where('uom', $this->filter_uom);
        }

        $this->lpns = $query->latest()->limit(100)->get();
    }

    public function selectLpn(int $id): void
    {
        $this->selectedLpn = Lpn::with(['location', 'grnLine.grn', 'parent', 'children'])->find($id);
    }

    // ── Break-Bulk ──────────────────────────────────────────────────────

    public function openBreakModal(int $id): void
    {
        $lpn = Lpn::find($id);
        if (!$lpn || $lpn->uom === 'unit') return;

        $childUom = WmsItemUomRule::childUomOf($lpn->uom);

        // Look up conversion rule
        $rule = WmsItemUomRule::where('item_code', $lpn->item_code)
            ->where('uom_level', $childUom)
            ->first();

        $this->breakLpnId       = $id;
        $this->breakLpnData     = $lpn->toArray();
        $this->breakChildUom    = $childUom;
        $this->breakQtyPerParent = $rule?->qty_per_parent ?? null;
        $this->updateBreakPreview();
        $this->showBreakModal   = true;
    }

    public function updatedBreakQtyPerParent(): void
    {
        $this->updateBreakPreview();
    }

    private function updateBreakPreview(): void
    {
        $lpn = $this->breakLpnId ? Lpn::find($this->breakLpnId) : null;
        if (!$lpn || !$this->breakQtyPerParent || $this->breakQtyPerParent <= 0) {
            $this->breakPreviewCount = 0;
            return;
        }
        $this->breakPreviewCount = (int) ceil($lpn->quantity / $this->breakQtyPerParent);
    }

    public function confirmBreakBulk(): void
    {
        $lpn = Lpn::find($this->breakLpnId);
        if (!$lpn) return;

        $qtyPerChild = (float) $this->breakQtyPerParent;
        $childUom    = $this->breakChildUom ?? WmsItemUomRule::childUomOf($lpn->uom) ?? 'unit';

        if ($qtyPerChild <= 0) {
            session()->flash('error', 'Enter a valid quantity per ' . $childUom . '.');
            return;
        }

        DB::transaction(function () use ($lpn, $qtyPerChild, $childUom) {
            $remaining = (float) $lpn->quantity;
            $seq       = 1;

            while ($remaining > 0) {
                $qty = min($qtyPerChild, $remaining);
                $childLpn = Lpn::create([
                    'lpn_number'     => Lpn::generateNumber(),
                    'grn_line_id'    => $lpn->grn_line_id,
                    'item_code'      => $lpn->item_code,
                    'lot_number'     => $lpn->lot_number,
                    'system_sub_lot' => $lpn->system_sub_lot,
                    'mfg_date'       => $lpn->mfg_date,
                    'expiry_date'    => $lpn->expiry_date,
                    'quantity'       => $qty,
                    'uom'            => $childUom,
                    'parent_lpn_id'  => $lpn->id,
                    'location_id'    => $lpn->location_id,  // inherit same bin
                    'status'         => Lpn::STATUS_STORED,
                    'ou_id'          => $lpn->ou_id,
                ]);

                // Save the rule for future use if not already saved
                WmsItemUomRule::firstOrCreate(
                    ['item_code' => $lpn->item_code, 'uom_level' => $childUom],
                    ['qty_per_parent' => $qtyPerChild]
                );

                $remaining -= $qty;
                $seq++;
            }

            // Mark parent as broken
            $lpn->update(['status' => Lpn::STATUS_BROKEN, 'quantity' => 0]);

            Transaction::create([
                'lpn_id'           => $lpn->id,
                'item_code'        => $lpn->item_code,
                'transaction_type' => 'break_bulk',
                'from_location_id' => $lpn->location_id,
                'to_location_id'   => $lpn->location_id,
                'quantity'         => $lpn->quantity,
                'user_id'          => auth()->id(),
                'reference'        => "Broken into {$childUom}s — " . ($seq - 1) . " child LPNs created",
            ]);
        });

        $this->showBreakModal = false;
        $this->reset(['breakLpnId', 'breakLpnData', 'breakChildUom', 'breakQtyPerParent', 'breakPreviewCount']);
        $this->loadLpns();
        session()->flash('message', 'Break-bulk complete. Child LPN labels ready to print.');
    }

    // ── Relocate ────────────────────────────────────────────────────────

    public function openRelocateModal(int $id): void
    {
        $this->relocateLpnId  = $id;
        $this->relocateNewBin = '';
        $this->showRelocateModal = true;
    }

    public function confirmRelocate(): void
    {
        $lpn = Lpn::find($this->relocateLpnId);
        if (!$lpn) return;

        $bin = Location::where('location_code', trim($this->relocateNewBin))
            ->whereIn('type', ['bin', 'rack'])
            ->first();

        if (!$bin) {
            session()->flash('error', 'Bin not found: ' . $this->relocateNewBin);
            $this->showRelocateModal = false;
            return;
        }

        DB::transaction(function () use ($lpn, $bin) {
            $fromId = $lpn->location_id;
            $lpn->update(['location_id' => $bin->id, 'status' => Lpn::STATUS_STORED]);
            Transaction::create([
                'lpn_id'           => $lpn->id,
                'item_code'        => $lpn->item_code,
                'transaction_type' => 'relocate',
                'from_location_id' => $fromId,
                'to_location_id'   => $bin->id,
                'quantity'         => $lpn->quantity,
                'user_id'          => auth()->id(),
                'reference'        => "Relocated to {$bin->location_code}",
            ]);
        });

        $this->showRelocateModal = false;
        $this->reset(['relocateLpnId', 'relocateNewBin']);
        $this->loadLpns();
        session()->flash('message', 'LPN relocated successfully.');
    }

    public function render()
    {
        return view('livewire.w-m-s.lpn-manager');
    }
}
