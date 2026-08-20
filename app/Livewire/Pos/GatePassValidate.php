<?php

namespace App\Livewire\Pos;

use App\Models\WMS\GatePass;
use Livewire\Component;

/**
 * Security-gate scan screen — scan the QR (its raw content is the
 * qr_token), see the anchor order to visually confirm, validate. A pass can
 * only be validated once; scanning an already-validated or cancelled pass
 * shows why instead of silently succeeding.
 */
class GatePassValidate extends Component
{
    public string $scannedToken = '';
    public ?array $pass = null;

    public string $message = '';
    public string $status = '';

    public function updatedScannedToken(string $value): void
    {
        $value = trim($value);
        $this->scannedToken = '';
        if (! $value) {
            return;
        }

        $gatePass = GatePass::with(['order.customer', 'order.orderItems.item', 'createdBy'])
            ->where('qr_token', $value)
            ->first();

        if (! $gatePass) {
            $this->pass = null;
            $this->message = 'Gate pass not recognised — invalid or forged QR.';
            $this->status = 'error';
            return;
        }

        $this->pass = [
            'id' => $gatePass->id,
            'gate_pass_number' => $gatePass->gate_pass_number,
            'type' => $gatePass->type,
            'status' => $gatePass->status,
            'order_number' => $gatePass->order?->order_number,
            'customer_name' => $gatePass->order?->customer?->customer_name,
            'total_amount' => $gatePass->order?->total_amount,
            'item_count' => $gatePass->order?->orderItems->count() ?? 0,
            'created_by' => $gatePass->createdBy?->name,
            'created_at' => $gatePass->created_at->format('d M Y, h:i A'),
            'validated_at' => $gatePass->validated_at?->format('d M Y, h:i A'),
        ];

        if ($gatePass->status === GatePass::STATUS_VALIDATED) {
            $this->message = "Already validated at {$this->pass['validated_at']} — this pass cannot be reused.";
            $this->status = 'warning';
            return;
        }

        if ($gatePass->status === GatePass::STATUS_CANCELLED) {
            $this->message = 'This gate pass was cancelled — do not allow passage.';
            $this->status = 'error';
            return;
        }

        $this->message = "Pass loaded: {$gatePass->gate_pass_number}. Verify details, then validate.";
        $this->status = 'success';
    }

    public function validatePass(): void
    {
        if (! $this->pass || $this->pass['status'] !== GatePass::STATUS_PENDING) {
            return;
        }

        $gatePass = GatePass::find($this->pass['id']);
        $gatePass->update([
            'status' => GatePass::STATUS_VALIDATED,
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);

        $this->message = "Gate pass {$gatePass->gate_pass_number} validated — passage logged.";
        $this->status = 'success';
        $this->pass = null;
    }

    public function resetScan(): void
    {
        $this->reset(['scannedToken', 'pass', 'message', 'status']);
    }

    public function render()
    {
        return view('livewire.pos.gate-pass-validate');
    }
}
