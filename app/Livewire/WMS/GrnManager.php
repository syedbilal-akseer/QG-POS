<?php

namespace App\Livewire\WMS;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\WMS\Grn;
use App\Models\WMS\GrnLine;
use App\Models\WMS\Lpn;

class GrnManager extends Component
{
    public $grns = [];

    // GRN header fields
    public $po_number      = '';
    public $supplier_name  = '';
    public $ou_id          = '';
    public $warehouse_name = '';

    // GRN line fields
    public $item_code           = '';
    public $description         = '';
    public $supplier_lot_number = '';
    public $mfg_date            = '';
    public $expiry_date         = '';
    public $received_qty        = 0;
    public $cost_price          = '';
    public $pallet_size         = '';

    // LPN generation preview
    public $lpn_preview = [];
    public $lastGrnLineId = null;

    public function mount()
    {
        $this->loadGrns();
    }

    public function loadGrns()
    {
        $this->grns = Grn::with('lines')->latest()->limit(50)->get();
    }

    public function updatedPalletSize()
    {
        $this->previewLpns();
    }

    public function updatedReceivedQty()
    {
        $this->previewLpns();
    }

    private function previewLpns()
    {
        $this->lpn_preview = [];
        $qty = (float) $this->received_qty;
        $size = (int) $this->pallet_size;
        if ($qty <= 0 || $size <= 0) return;

        $count = (int) ceil($qty / $size);
        $remaining = $qty;
        for ($i = 1; $i <= min($count, 5); $i++) {
            $this->lpn_preview[] = [
                'seq'  => $i,
                'qty'  => min($size, $remaining),
                'uom'  => 'pallet',
            ];
            $remaining -= $size;
        }
        if ($count > 5) {
            $this->lpn_preview[] = ['seq' => '...', 'qty' => '...', 'uom' => "({$count} total)"];
        }
    }

    public function saveGrn()
    {
        $this->validate([
            'po_number'   => 'required',
            'item_code'   => 'required',
            'received_qty' => 'required|numeric|min:1',
            'expiry_date' => 'nullable|date',
            'cost_price'  => 'nullable|numeric|min:0',
            'pallet_size' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () {
            $grn = Grn::create([
                'grn_number'     => 'GRN-' . strtoupper(Str::random(6)),
                'po_number'      => $this->po_number,
                'supplier_name'  => $this->supplier_name,
                'received_date'  => now(),
                'status'         => 'completed',
                'ou_id'          => $this->ou_id ?: null,
                'warehouse_name' => $this->warehouse_name ?: null,
            ]);

            $system_sub_lot = 'SYS-LOT-' . strtoupper(Str::random(8));

            $line = GrnLine::create([
                'grn_id'              => $grn->id,
                'item_code'           => $this->item_code,
                'description'         => $this->description ?: null,
                'supplier_lot_number' => $this->supplier_lot_number ?: null,
                'system_sub_lot'      => $system_sub_lot,
                'mfg_date'            => empty($this->mfg_date) ? null : $this->mfg_date,
                'expiry_date'         => empty($this->expiry_date) ? null : $this->expiry_date,
                'received_qty'        => $this->received_qty,
                'cost_price'          => !empty($this->cost_price) ? $this->cost_price : null,
                'pallet_size'         => !empty($this->pallet_size) ? (int)$this->pallet_size : null,
                'lpns_generated'      => false,
            ]);

            // Auto-generate LPNs if pallet_size given
            if (!empty($this->pallet_size) && (int)$this->pallet_size > 0) {
                $this->generateLpnsForLine($line, $grn);
                $line->update(['lpns_generated' => true]);
            }

            $this->lastGrnLineId = $line->id;
        });

        $this->reset([
            'po_number', 'supplier_name', 'item_code', 'description',
            'supplier_lot_number', 'mfg_date', 'expiry_date', 'received_qty',
            'cost_price', 'pallet_size', 'lpn_preview',
        ]);
        $this->loadGrns();
        session()->flash('message', 'GRN created and LPN labels generated. Print labels from the GRN list below.');
    }

    private function generateLpnsForLine(GrnLine $line, Grn $grn): void
    {
        $remaining = (float) $line->received_qty;
        $palletSize = (int) $line->pallet_size;
        $uom = $palletSize > 1 ? 'pallet' : 'unit';

        while ($remaining > 0) {
            $qty = min($palletSize, $remaining);
            Lpn::create([
                'lpn_number'     => Lpn::generateNumber(),
                'grn_line_id'    => $line->id,
                'item_code'      => $line->item_code,
                'lot_number'     => $line->supplier_lot_number,
                'system_sub_lot' => $line->system_sub_lot,
                'mfg_date'       => $line->mfg_date,
                'expiry_date'    => $line->expiry_date,
                'quantity'       => $qty,
                'uom'            => $uom,
                'status'         => Lpn::STATUS_RECEIVED,
                'ou_id'          => $grn->ou_id,
                'location_id'    => null,
            ]);
            $remaining -= $qty;
        }
    }

    public function generateLpnsManual(int $lineId): void
    {
        $line = GrnLine::with('grn')->findOrFail($lineId);
        if ($line->lpns_generated) {
            session()->flash('error', 'LPNs already generated for this line.');
            return;
        }
        if (!$line->pallet_size) {
            session()->flash('error', 'Set pallet/carton size first before generating LPNs.');
            return;
        }
        DB::transaction(function () use ($line) {
            $this->generateLpnsForLine($line, $line->grn);
            $line->update(['lpns_generated' => true]);
        });
        $this->loadGrns();
        session()->flash('message', 'LPNs generated successfully.');
    }

    public function render()
    {
        return view('livewire.w-m-s.grn-manager');
    }
}
