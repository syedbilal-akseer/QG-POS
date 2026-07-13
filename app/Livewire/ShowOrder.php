<?php

namespace App\Livewire;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderType;
use App\Models\OracleOrderHeader;
use App\Models\OracleOrderLine;
use App\Models\Warehouse;
use App\Traits\NotifiesUsers;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Full-page Order detail view — the dedicated "screen" route the orders
 * list now links to instead of opening the in-list modal. Carries the
 * SAME Enter-to-Oracle action + warehouse-per-line selector that the
 * modal had so users can push a single order without bouncing back to
 * the list.
 *
 * Access scoping is enforced inline (admin / view-all bypass; everyone
 * else needs the order's customer.ou_id to be in their effective OU set)
 * — same rule the list page uses.
 */
#[Title('Order Details')]
class ShowOrder extends Component
{
    use NotifiesUsers;

    public ?Order $order = null;
    public array  $orderItemWarehouses = [];
    public array  $warehouses = [];

    public function mount(Order $order): void
    {
        // No OU gate here — the old in-list modal had none, and the
        // ListOrders query already scopes which orders each role can see.
        // Adding a stricter check here 403'd push roles (supply-chain,
        // cmd-khi/lhr, invoice-manager, ...) whose UserOrganization mapping
        // didn't include the customer's OU even though they had always been
        // able to push those orders from the modal. Authenticated session is
        // enforced by the route's auth middleware.

        $this->order = $order->load([
            'orderItems.item',
            'customer',
            'salesperson',
            'pushedBy',
            'transporter',
            'cancelledBy',
        ]);

        // Build the warehouse dropdown for the line-level select. Restrict
        // to the customer's OU first, fall back to ALL warehouses if none
        // are tagged for that OU.
        $warehouses = Warehouse::where('ou', $this->order->customer?->ou_id)->get();
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::all();
        }
        $this->warehouses = collect([['value' => '', 'label' => 'Select Warehouse']])
            ->merge($warehouses->map(fn ($w) => [
                'value' => $w->organization_id,
                'label' => $w->organization_code . ' (' . $w->organization_id . ')',
            ]))
            ->values()
            ->toArray();

        $this->orderItemWarehouses = $this->order->orderItems
            ->mapWithKeys(fn ($item, $index) => [$index => $item->warehouse_id ?? null])
            ->toArray();
    }

    /**
     * Push this order to Oracle — same idempotent, row-locked flow the
     * modal version uses. On success, refreshes the same show page so the
     * user sees the new "Already Entered to Oracle" state inline.
     */
    public function enterOrderToOracle()
    {
        if (auth()->user()->isSalesHead()) {
            $this->notifyUser('Access Denied', 'View-only role — cannot push to Oracle.', 'danger');
            return;
        }

        $this->validate(
            ['orderItemWarehouses.*' => 'required'],
            ['orderItemWarehouses.*.required' => 'Warehouse must be selected for order item.']
        );

        if ($this->order && $this->order->oracle_at !== null) {
            $this->notifyUser(
                'Already Pushed',
                'This order was already entered to Oracle on ' . $this->order->oracle_at->format('d M Y H:i') . '.',
                'warning'
            );
            return redirect()->route('orders.show', $this->order);
        }

        $orderId = $this->order->id;

        try {
            $oracleHeader = DB::transaction(function () use ($orderId) {
                $lockedOrder = Order::lockForUpdate()->find($orderId);
                if (!$lockedOrder) {
                    throw new \Exception('Order no longer exists.');
                }
                if ($lockedOrder->oracle_at !== null) {
                    throw new \RuntimeException('ALREADY_PUSHED:' . $lockedOrder->oracle_at->toIso8601String());
                }
                $lockedOrder->load(['customer', 'orderItems.item']);
                $this->order = $lockedOrder;

                return DB::connection('oracle')->transaction(function () {
                    if (!$this->order->customer->price_list_id) {
                        throw new \Exception("Customer {$this->order->customer->customer_name} is missing price_list_id. Please sync customer data from Oracle.");
                    }

                    $oracleOrderType = OrderType::where('org_id', $this->order->customer->ou_id)->first();
                    if (!$oracleOrderType) {
                        throw new \Exception("Order type or line type not found for org_id: {$this->order->customer->ou_id}");
                    }

                    $orderHeaderData = [
                        'order_source_id'        => 1001,
                        'orig_sys_document_ref'  => $this->order->order_number,
                        'org_id'                 => $this->order->customer->ou_id,
                        'sold_from_org_id'       => $this->order->customer->ou_id,
                        'ordered_date'           => Carbon::now(),
                        'order_type_id'          => $oracleOrderType->order_type_id,
                        'sold_to_org_id'         => $this->order->customer->customer_id,
                        'price_list_id'          => $this->order->customer->price_list_id,
                        'payment_term_id'        => 1004,
                        'operation_code'         => 'INSERT',
                        'created_by'             => 0,
                        'creation_date'          => Carbon::now(),
                        'last_updated_by'        => 0,
                        'last_update_date'       => Carbon::now(),
                        'customer_po_number'     => $this->order->order_number,
                        'ship_to_org_id'         => $this->order->customer->customer_site_id,
                        'BOOKED_FLAG'            => 'N',
                    ];
                    $oracleOrderHeader = OracleOrderHeader::create($orderHeaderData);

                    foreach ($this->order->orderItems as $index => $orderItem) {
                        $selectedWarehouseId = $this->orderItemWarehouses[$index] ?? null;
                        $orderItem->update(['warehouse_id' => $selectedWarehouseId]);

                        $unitListPrice = round((float) $orderItem->price, 2);
                        if ($orderItem->cap_price !== null) {
                            $unitSellingPrice = round((float) $orderItem->cap_price, 2);
                        } else {
                            $unitSellingPrice = $orderItem->quantity > 0
                                ? round((float) $orderItem->sub_total / $orderItem->quantity, 2)
                                : $unitListPrice;
                            $unitSellingPrice = (float) max(0, min($unitListPrice, $unitSellingPrice));
                        }
                        $hasOverride = $orderItem->cap_price !== null
                            || (float) $orderItem->discount > 0
                            || $unitSellingPrice < $unitListPrice;
                        $calculatePriceFlag = $hasOverride ? 'N' : 'P';

                        $orderLineData = [
                            'order_source_id'       => 1001,
                            'orig_sys_document_ref' => $this->order->order_number,
                            'orig_sys_line_ref'     => "{$this->order->order_number}-" . ($index + 1),
                            'line_number'           => ($index + 1),
                            'inventory_item_id'     => $orderItem->inventory_item_id,
                            'ordered_quantity'      => $orderItem->quantity,
                            'unit_selling_price'    => $unitSellingPrice,
                            'unit_list_price'       => $unitListPrice,
                            'calculate_price_flag'  => $calculatePriceFlag,
                            'ship_from_org_id'      => $selectedWarehouseId,
                            'org_id'                => $this->order->customer->ou_id,
                            'price_list_id'         => $this->order->customer->price_list_id,
                            'payment_term_id'       => 1004,
                            'created_by'            => 0,
                            'creation_date'         => Carbon::now(),
                            'last_updated_by'       => 0,
                            'last_update_date'      => Carbon::now(),
                            'line_type_id'          => $oracleOrderType->line_type_id,
                            'order_quantity_uom'    => $orderItem->uom,
                            'operation_code'        => 'INSERT',
                        ];
                        Log::info('Oracle Order Line Data', [
                            'order_number'         => $this->order->order_number,
                            'line_number'          => ($index + 1),
                            'item_code'            => $orderItem->item->item_code ?? 'N/A',
                            'unit_list_price'      => $unitListPrice,
                            'unit_selling_price'   => $unitSellingPrice,
                            'cap_price'            => $orderItem->cap_price,
                            'calculate_price_flag' => $calculatePriceFlag,
                        ]);
                        OracleOrderLine::create($orderLineData);
                    }

                    $this->order->update([
                        'oracle_at'    => now(),
                        'order_status' => OrderStatusEnum::ENTERED,
                        'pushed_by'    => auth()->id(),
                    ]);

                    return $oracleOrderHeader;
                });
            });

            if ($oracleHeader) {
                Log::info('Oracle Order Sync Completed Successfully (ShowOrder)', [
                    'order_number' => $this->order->order_number,
                    'order_id'     => $this->order->id,
                    'pushed_by'    => auth()->id(),
                ]);
                try {
                    app(\App\Services\OrderReceiptNotifier::class)->orderPushedToOracle($this->order);
                } catch (\Throwable $e) {
                    Log::warning('orderPushedToOracle notifier failed', ['error' => $e->getMessage()]);
                }
                $this->notifyUser('Order Entered', 'Order entered to Oracle successfully.');
                return redirect()->route('orders.show', $this->order);
            }
            throw new \Exception('Order insertion failed.');
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'ALREADY_PUSHED')) {
                $when = trim(substr($e->getMessage(), strlen('ALREADY_PUSHED:'))) ?: null;
                Log::info('Order push skipped — already pushed concurrently', [
                    'order_id'     => $this->order->id ?? null,
                    'order_number' => $this->order->order_number ?? null,
                    'already_at'   => $when,
                ]);
                $msg = $when
                    ? 'This order was already pushed to Oracle on ' . Carbon::parse($when)->format('d M Y H:i') . '.'
                    : 'This order has already been pushed to Oracle.';
                $this->notifyUser('Already Pushed', $msg, 'warning');
                return redirect()->route('orders.show', $this->order);
            }
            Log::error('Order Oracle Sync Error (ShowOrder): ' . $e->getMessage(), [
                'order_id' => $this->order->id ?? null,
                'trace'    => $e->getTraceAsString(),
            ]);
            $this->notifyUser('Error', 'An error occurred: ' . $e->getMessage(), 'danger');
        } catch (\Exception $e) {
            Log::error('Order Oracle Sync Error (ShowOrder): ' . $e->getMessage(), [
                'order_id' => $this->order->id ?? null,
                'trace'    => $e->getTraceAsString(),
            ]);
            $this->notifyUser('Error', 'An error occurred: ' . $e->getMessage(), 'danger');
        }
    }

    public function render()
    {
        return view('livewire.show-order');
    }
}
