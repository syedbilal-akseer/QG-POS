<?php

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Services\BarcodeResolverService;
use App\Services\ItemPriceResolver;
use App\Services\OrderReceiptNotifier;
use App\Services\PromotionalSchemeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosTerminal extends Component
{
    // Walk-in customer this login sells against — assigned once by admin on
    // the user record (users.pos_customer_id), never resolved by name at
    // runtime. See PosCustomerAssignments for the admin-side assignment.
    public ?int $customerId = null; // customers.id (local PK)
    public ?array $customer = null; // lightweight display payload
    public bool $noWalkinAccount = false;

    // Scanning / search
    public string $barcodeInput = '';
    public ?string $scanError = null;
    public string $itemSearch = '';
    public array $itemResults = [];

    // Cart: inventory_item_id => line array
    public array $cart = [];

    public string $remarks = '';
    public ?string $checkoutError = null;
    public ?string $lastOrderNumber = null;

    public function mount(): void
    {
        $user = Auth::user()->load('posCustomer');
        $customer = $user->posCustomer;

        if (! $customer) {
            $this->noWalkinAccount = true;
            return;
        }

        $this->customerId = $customer->id;
        $this->customer = [
            'id' => $customer->id,
            'customer_name' => $customer->customer_name,
            'customer_number' => $customer->customer_number,
            'price_list_name' => $customer->price_list_name,
            'has_price_list' => (bool) $customer->price_list_id,
        ];
    }

    public function updatedItemSearch(): void
    {
        $term = trim($this->itemSearch);

        if ($term === '' || ! $this->customerId) {
            $this->itemResults = [];
            return;
        }

        $customer = Customer::find($this->customerId);
        if (! $customer) {
            $this->itemResults = [];
            return;
        }

        // Only show items that actually price on this customer's list —
        // otherwise a result could sit in the dropdown that fails with
        // "No price found" the moment it's clicked (addToCart runs the same
        // resolver). Widen the candidate pool before filtering since some
        // will drop out.
        $resolver = app(ItemPriceResolver::class);

        $candidates = Item::query()
            ->excludePackingMaterial()
            ->where(function ($q) use ($term) {
                $q->where('item_code', 'like', "%{$term}%")
                    ->orWhere('item_description', 'like', "%{$term}%");
            })
            ->limit(30)
            ->get(['inventory_item_id', 'item_code', 'item_description', 'primary_uom_code']);

        $this->itemResults = $candidates
            ->filter(fn ($item) => $resolver->resolve($item, $customer) !== null)
            ->take(10)
            ->map(fn ($item) => $item->toArray())
            ->values()
            ->toArray();
    }

    public function scanBarcode(): void
    {
        $this->scanError = null;
        $code = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if ($code === '') {
            $this->dispatch('barcode-scanned');
            return;
        }

        if (! $this->customerId) {
            $this->scanError = 'No walk-in account linked to your login.';
            $this->dispatch('barcode-scanned');
            return;
        }

        $resolved = app(BarcodeResolverService::class)->resolve($code);

        if (! $resolved['success'] || ! $resolved['item']) {
            $this->scanError = $resolved['reason'] ?? 'Barcode not recognised.';
            $this->dispatch('barcode-scanned');
            return;
        }

        $this->addToCart((int) $resolved['item']['inventory_item_id']);
    }

    public function addToCart(int $inventoryItemId): void
    {
        $this->checkoutError = null;

        // Bluetooth/USB scanners fire this on every scan — always hand focus
        // back to the scan box afterwards so repeat scanning never needs a
        // manual tap in between (mobile browsers don't reliably honour plain
        // HTML autofocus after a Livewire re-render).
        $this->dispatch('barcode-scanned');

        if (! $this->customerId) {
            $this->checkoutError = 'No walk-in account linked to your login.';
            return;
        }

        $customer = Customer::find($this->customerId);
        if (! $customer || ! $customer->price_list_id) {
            $this->checkoutError = 'This customer has no price list — cannot price items.';
            return;
        }

        if (isset($this->cart[$inventoryItemId])) {
            $this->cart[$inventoryItemId]['quantity']++;
            $this->recalculateLine($inventoryItemId);
            return;
        }

        $item = Item::where('inventory_item_id', $inventoryItemId)->first();
        if (! $item) {
            $this->scanError = 'Item not found.';
            return;
        }

        $itemPrice = app(ItemPriceResolver::class)->resolve($item, $customer);
        if (! $itemPrice) {
            $this->scanError = "No price found for {$item->item_code} on {$customer->price_list_name}.";
            return;
        }

        $this->cart[$inventoryItemId] = [
            'inventory_item_id' => $item->inventory_item_id,
            'item_code' => $item->item_code,
            'item_description' => $item->item_description,
            'uom' => $itemPrice->uom ?? $item->primary_uom_code,
            'quantity' => 1,
            'unit_price' => (float) $itemPrice->list_price,
            'line_total' => (float) $itemPrice->list_price,
        ];

        $this->itemSearch = '';
        $this->itemResults = [];
    }

    public function updateQuantity(int $inventoryItemId, $quantity): void
    {
        $quantity = (float) $quantity;

        if ($quantity <= 0) {
            $this->removeFromCart($inventoryItemId);
            return;
        }

        if (! isset($this->cart[$inventoryItemId])) {
            return;
        }

        $this->cart[$inventoryItemId]['quantity'] = $quantity;
        $this->recalculateLine($inventoryItemId);
    }

    public function removeFromCart(int $inventoryItemId): void
    {
        unset($this->cart[$inventoryItemId]);
    }

    protected function recalculateLine(int $inventoryItemId): void
    {
        $line = $this->cart[$inventoryItemId];
        $this->cart[$inventoryItemId]['line_total'] = round($line['unit_price'] * $line['quantity'], 2);
    }

    public function getSubTotalProperty(): float
    {
        return round(array_sum(array_column($this->cart, 'line_total')), 2);
    }

    public function checkout(): void
    {
        $this->checkoutError = null;

        if (! $this->customerId) {
            $this->checkoutError = 'No walk-in account linked to your login.';
            return;
        }

        if (empty($this->cart)) {
            $this->checkoutError = 'Cart is empty.';
            return;
        }

        $customer = Customer::find($this->customerId);
        if (! $customer) {
            $this->checkoutError = 'Customer no longer exists.';
            return;
        }

        $cartLines = $this->cart;
        $subTotal = $this->subTotal;

        try {
            $order = DB::transaction(function () use ($customer, $cartLines, $subTotal) {
                $order = $customer->orders()->create([
                    'user_id' => Auth::id(),
                    'remarks' => $this->remarks !== '' ? $this->remarks : null,
                ]);

                $paidLinesForPromo = [];

                foreach ($cartLines as $line) {
                    $order->orderItems()->create([
                        'inventory_item_id' => $line['inventory_item_id'],
                        'uom' => $line['uom'],
                        'quantity' => $line['quantity'],
                        'price' => $line['unit_price'],
                        'sub_total' => $line['line_total'],
                        'discount' => 0,
                    ]);

                    $paidLinesForPromo[] = [
                        'item_code' => $line['item_code'],
                        'quantity' => (int) $line['quantity'],
                        'uom' => $line['uom'],
                    ];
                }

                try {
                    app(PromotionalSchemeService::class)->applyToOrder($order, $paidLinesForPromo);
                } catch (\Throwable $e) {
                    \Log::warning('POS - Promo scheme application failed (order still placed)', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $order->update([
                    'sub_total' => $subTotal,
                    'discount' => 0,
                    'total_amount' => $subTotal,
                ]);

                return $order;
            });

            app(OrderReceiptNotifier::class)->orderCreated($order);

            $this->lastOrderNumber = $order->order_number;
            $this->cart = [];
            $this->remarks = '';

            notify('Order placed', "Order #{$order->order_number} for {$customer->customer_name} — Rs " . number_format($subTotal, 2), 'success');
        } catch (\Throwable $e) {
            \Log::error('POS - checkout failed', ['error' => $e->getMessage()]);
            $this->checkoutError = 'Could not place order: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.pos.pos-terminal');
    }
}
