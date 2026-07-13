<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\Order;
use Livewire\Component;
use App\Models\OrderType;
use App\Models\Warehouse;
use Filament\Tables\Table;
use App\Traits\LogsActivity;
use App\Traits\NotifiesUsers;
use App\Enums\OrderStatusEnum;
use Livewire\Attributes\Title;
use App\Models\OracleOrderLine;
use App\Models\OracleOrderHeader;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Exports\OrderExporter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\User;
use App\Models\Customer;
use App\Models\Transporter;


#[Title('Orders')]
class ListOrders extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;
    use LogsActivity;

    public $order, $orderDetails;
    public $warehouses = [];
    public $orderItemWarehouses = [];

    public function table(Table $table): Table
    {
        // Apply location-based filtering
        $query = Order::query();

        $user = auth()->user();

        // Debug logging
        \Log::info('ListOrders - Filtering', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_supply_chain' => $user->isSupplyChain(),
            'is_scm_lhr' => $user->isScmLhr(),
            'can_view_orders_from_location' => $user->canViewOrdersFromLocation(),
            'allowed_ou_ids' => $user->getAllowedOuIds(),
        ]);

        // Apply location filtering for LHR and KHI users, supply-chain users, and scm-lhr users
        if (!$user->isAdmin() && ($user->canViewOrdersFromLocation() || $user->isScmLhr())) {
            $allowedOuIds = $user->getAllowedOuIds();

            \Log::info('ListOrders - Applying OU filter', [
                'allowed_ou_ids' => $allowedOuIds,
                'is_empty' => empty($allowedOuIds),
            ]);

            if (!empty($allowedOuIds)) {
                $query->whereHas('customer', function ($customerQuery) use ($allowedOuIds) {
                    $customerQuery->whereIn('ou_id', $allowedOuIds);
                });
            } else {
                // If no allowed OU IDs, show no orders
                $query->where('id', -1);
                \Log::info('ListOrders - No OU IDs, hiding all orders');
            }
        } else {
            \Log::info('ListOrders - No filtering applied (admin or no location restrictions)');
        }


        return $table
            ->query($query)
            
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order Number')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.customer_name')
                    ->label('Customer Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('salesperson.name')
                    ->label('Salesperson')
                    ->sortable()
                    ->searchable()
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr()),
                TextColumn::make('order_status')
                    ->label('Order Status')
                    ->badge()
                    ->color(fn($state): string => OrderStatusEnum::color(
                        $state instanceof OrderStatusEnum ? $state->value : (string) $state
                    ))
                    ->formatStateUsing(fn($state) => $state instanceof OrderStatusEnum
                        ? $state->name()
                        : (OrderStatusEnum::tryFrom((string) $state)?->name() ?? $state)
                    )
                    ->sortable()
                    ->searchable(),
                TextColumn::make('pushedBy.name')
                    ->label('Pushed to Oracle By')
                    ->sortable()
                    ->searchable()
                    ->default('N/A')
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr()),
                TextColumn::make('created_at')
                    ->visibleFrom('md')
                    ->label('Order Date')
                    ->dateTime('F j, Y, g:i a')
                    ->sortable(),
            ])
            ->filters([
                // ── Status ─────────────────────────────────────────────────────
                SelectFilter::make('order_status')
                    ->label('Status')
                    ->options(OrderStatusEnum::asArray())
                    ->attribute('order_status')
                    ->multiple()
                    ->placeholder('Any status'),

                // ── Customer ───────────────────────────────────────────────────
                Filter::make('customer')
                    ->form([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->placeholder('All customers')
                            ->options(fn () => Customer::query()
                                ->whereNotNull('customer_name')
                                ->orderBy('customer_name')
                                ->limit(2000)
                                ->pluck('customer_name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $q, array $data): Builder =>
                        $q->when($data['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
                    )
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['customer_id'] ?? null)) return null;
                        $name = Customer::whereKey($data['customer_id'])->value('customer_name');
                        return $name ? "Customer: {$name}" : null;
                    }),

                // ── Salesperson (placed the order) ─────────────────────────────
                Filter::make('salesperson')
                    ->form([
                        Select::make('user_id')
                            ->label('Salesperson')
                            ->placeholder('All salespeople')
                            ->options(fn () => User::query()
                                ->whereIn('id', Order::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $q, array $data): Builder =>
                        $q->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                    )
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['user_id'] ?? null)) return null;
                        $name = User::whereKey($data['user_id'])->value('name');
                        return $name ? "Salesperson: {$name}" : null;
                    }),

                // ── Pushed By (whoever pushed it to Oracle) ────────────────────
                Filter::make('pushed_by_user')
                    ->form([
                        Select::make('pushed_by')
                            ->label('Pushed By')
                            ->placeholder('Anyone')
                            ->options(fn () => User::query()
                                ->whereIn('id', Order::query()->whereNotNull('pushed_by')->distinct()->pluck('pushed_by'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $q, array $data): Builder =>
                        $q->when($data['pushed_by'] ?? null, fn ($q, $id) => $q->where('pushed_by', $id))
                    )
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['pushed_by'] ?? null)) return null;
                        $name = User::whereKey($data['pushed_by'])->value('name');
                        return $name ? "Pushed by: {$name}" : null;
                    }),

                // ── Pushed to Oracle? ──────────────────────────────────────────
                TernaryFilter::make('oracle_at')
                    ->label('Pushed to Oracle')
                    ->placeholder('Any')
                    ->trueLabel('Pushed to Oracle')
                    ->falseLabel('Not yet pushed')
                    ->queries(
                        true:  fn (Builder $q) => $q->whereNotNull('oracle_at'),
                        false: fn (Builder $q) => $q->whereNull('oracle_at'),
                        blank: fn (Builder $q) => $q,
                    ),

                // ── Transporter ────────────────────────────────────────────────
                SelectFilter::make('transporter_id')
                    ->label('Transporter')
                    ->placeholder('Any transporter')
                    ->options(fn () => Transporter::query()
                        ->orderBy('description')
                        ->pluck('description', 'id')
                        ->toArray())
                    ->searchable(),

                // ── Order number (exact / partial match) ───────────────────────
                Filter::make('order_number')
                    ->form([
                        TextInput::make('order_number')
                            ->label('Order Number')
                            ->placeholder('e.g. 20260501'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder =>
                        $q->when($data['order_number'] ?? null, fn ($q, $n) => $q->where('order_number', 'like', "%{$n}%"))
                    )
                    ->indicateUsing(fn (array $data) =>
                        $data['order_number'] ? 'Order #' . $data['order_number'] : null
                    ),

                // ── Order date range ───────────────────────────────────────────
                Filter::make('created_at')
                    ->label('Order Date Between')
                    ->form([
                        DatePicker::make('created_from')->label('Order Date From')->native(false),
                        DatePicker::make('created_until')->label('Order Date Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['created_from'] && !$data['created_until']) return null;
                        $from  = $data['created_from']  ? Carbon::parse($data['created_from'])->toFormattedDateString()  : 'N/A';
                        $until = $data['created_until'] ? Carbon::parse($data['created_until'])->toFormattedDateString() : 'N/A';
                        return 'Order Date from ' . $from . ' to ' . $until;
                    }),

                // ── Pushed date range (when entered to Oracle) ─────────────────
                Filter::make('oracle_pushed_range')
                    ->label('Pushed Date Between')
                    ->form([
                        DatePicker::make('pushed_from')->label('Pushed From')->native(false),
                        DatePicker::make('pushed_until')->label('Pushed Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['pushed_from'],
                                fn(Builder $q, $date): Builder => $q->whereDate('oracle_at', '>=', $date),
                            )
                            ->when(
                                $data['pushed_until'],
                                fn(Builder $q, $date): Builder => $q->whereDate('oracle_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['pushed_from'] && !$data['pushed_until']) return null;
                        $from  = $data['pushed_from']  ? Carbon::parse($data['pushed_from'])->toFormattedDateString()  : 'N/A';
                        $until = $data['pushed_until'] ? Carbon::parse($data['pushed_until'])->toFormattedDateString() : 'N/A';
                        return 'Pushed from ' . $from . ' to ' . $until;
                    }),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class)
                    ->fileName(fn(Export $export): string => "orders-{$export->getKey()}")
                    ->formats([
                        ExportFormat::Xlsx,
                        ExportFormat::Csv,
                    ])
            ])
            ->actions([
                Action::make('view')
                    ->button()
                    ->icon(fn(Order $record) => $record->oracle_at
                        ? 'heroicon-m-check-circle'
                        : 'heroicon-m-eye')
                    ->label(fn(Order $record) => $record->oracle_at
                        ? 'View (Pushed to Oracle)'
                        : 'View')
                    ->color(fn(Order $record) => $record->oracle_at ? 'success' : 'primary')
                    ->action(fn(Order $record) => $this->openDetailModal($record)),

                Action::make('syncDetails')
                    ->button()
                    ->icon('heroicon-m-arrow-path')
                    ->label('Sync Details')
                    ->color('gray')
                    ->action(fn(Order $record) => $this->openSyncDetailsModal($record))
                    ->visible(fn(Order $record) => $record->oracle_at !== null
                        && $record->orderItems->flatMap(fn($item) => $item->syncHistory)->isNotEmpty()),

                Action::make('delete')
                    ->button()
                    ->icon('heroicon-m-trash')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Order')
                    ->modalDescription('Are you sure you want to delete this order? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete')
                    ->action(fn(Order $record) => $this->deleteOrder($record))
                    ->visible(fn(Order $record) => auth()->user()->isAdmin() && is_null($record->oracle_at)),
            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ])
            ->deferLoading()
            ->defaultSort('created_at', 'desc');
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);

        if (filled($search = $this->getTableSearch())) {
            $searchTerm = '%' . $search . '%';

            $query->where(function ($query) use ($searchTerm) {
                // Search in Order fields
                $query->where('order_number', 'like', $searchTerm)
                    ->orWhere('order_status', 'like', $searchTerm)

                    // Search in related Customer fields
                    ->orWhereHas('customer', function ($q) use ($searchTerm) {
                        $q->where('customer_name', 'like', $searchTerm)
                            ->orWhere('customer_number', 'like', $searchTerm)
                            ->orWhere('customer_id', 'like', $searchTerm)
                            ->orWhere('city', 'like', $searchTerm)
                            ->orWhere('area', 'like', $searchTerm)
                            ->orWhere('contact_number', 'like', $searchTerm)
                            ->orWhere('email_address', 'like', $searchTerm);
                    })

                    // Search in Salesperson
                    ->orWhereHas('salesperson', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', $searchTerm);
                    })

                    // Search in related Warehouse fields
                    ->orWhereHas('orderItems', function ($q) use ($searchTerm) {
                        $q->whereHas('warehouse', function ($wq) use ($searchTerm) {
                            $wq->where('organization_code', 'like', $searchTerm)
                               ->orWhere('organization_id', 'like', $searchTerm);
                        });
                    })

                    // Search in related OrderItem fields
                    ->orWhereHas('orderItems', function ($q) use ($searchTerm) {
                        $q->whereHas('item', function ($q) use ($searchTerm) {
                            $q->where('item_description', 'like', $searchTerm)
                                ->orWhere('item_code', 'like', $searchTerm);
                        });
                    });
            });
        }

        return $query;
    }

    public function openDetailModal(Order $order)
    {
        // Load the order along with its order items, item details, customer, salesperson, and transporter
        $this->order = $order->load(['orderItems.item', 'customer', 'salesperson', 'transporter']);

        // Fetch warehouses based on the customer's ou_id, fallback to all if none match
        $warehouses = Warehouse::where('ou', $this->order->customer->ou_id)->get();
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::all();
        }
        
        // Add default "Select Warehouse" option
        $warehouseOptions = collect([['value' => '', 'label' => 'Select Warehouse']]);
        
        // Transform the warehouse data into the format expected by the select component (value and label)
        $warehouseData = $warehouses->map(function ($warehouse) {
            return [
                'value' => $warehouse->organization_id,
                'label' => $warehouse->organization_code . ' (' . $warehouse->organization_id . ')',
            ];
        });
        
        $this->warehouses = $warehouseOptions->merge($warehouseData)->values()->toArray();

        // Initialize the orderItemWarehouses array with existing warehouse IDs or null
        $this->orderItemWarehouses = $this->order->orderItems->mapWithKeys(function ($item, $index) {
            return [$index => $item->warehouse_id ?? null];
        })->toArray();

        // Dispatch the event to open the order detail modal
        $this->dispatch('open-modal', 'order_detail');
    }

    public function openSyncDetailsModal(Order $order)
    {
        // Load order items along with the sync history, item details, customer, and salesperson
        $this->orderDetails = $order->load(['orderItems.syncHistory', 'orderItems.item', 'customer', 'salesperson']);
        // Dispatch to open the modal
        $this->dispatch('open-modal', 'order_sync_details');
    }

    public function closeDetailModal()
    {
        $this->reset('order');
        $this->dispatch('close');
    }

    public function enterOrderToOracle()
    {
        // Validate warehouses are selected
        $this->validate([
            'orderItemWarehouses.*' => 'required',
        ], [
            'orderItemWarehouses.*.required' => 'Warehouse must be selected for order item.',
        ]);

        try {
            $order = DB::connection('oracle')->transaction(function () {
                // Log customer data being used for Oracle sync
                \Log::info('Starting Oracle Order Sync', [
                    'order_number' => $this->order->order_number,
                    'order_id' => $this->order->id,
                    'customer' => [
                        'customer_id' => $this->order->customer->customer_id,
                        'customer_name' => $this->order->customer->customer_name,
                        'customer_number' => $this->order->customer->customer_number,
                        'ou_id' => $this->order->customer->ou_id,
                        'ou_name' => $this->order->customer->ou_name,
                        'price_list_id' => $this->order->customer->price_list_id,
                        'price_list_name' => $this->order->customer->price_list_name,
                        'customer_site_id' => $this->order->customer->customer_site_id,
                    ],
                    'total_items' => $this->order->orderItems->count(),
                    'total_amount' => $this->order->total_amount,
                ]);

                // Validate customer has required fields
                if (!$this->order->customer->price_list_id) {
                    throw new \Exception("Customer {$this->order->customer->customer_name} is missing price_list_id. Please sync customer data from Oracle.");
                }

                // Fetch the relevant order type and line type based on ou_id
                $oracleOrderType = OrderType::where('org_id', $this->order->customer->ou_id)->first();

                if (!$oracleOrderType) {
                    throw new \Exception("Order type or line type not found for org_id: {$this->order->customer->ou_id}");
                }

                // Prepare Oracle Order Header data
                $orderHeaderData = [
                    'order_source_id' => 1001,
                    'orig_sys_document_ref' => $this->order->order_number,
                    'org_id' => $this->order->customer->ou_id, // Customer OU ID
                    'sold_from_org_id' => $this->order->customer->ou_id, // Customer OU ID
                    'ordered_date' => Carbon::now(),
                    'order_type_id' => $oracleOrderType->order_type_id,
                    'sold_to_org_id' => $this->order->customer->customer_id,
                    'price_list_id' => $this->order->customer->price_list_id, // Add price_list_id to header
                    'payment_term_id' => 1004,
                    'operation_code' => 'INSERT',
                    'created_by' => 0,
                    'creation_date' => Carbon::now(),
                    'last_updated_by' => 0,
                    'last_update_date' => Carbon::now(),
                    'customer_po_number' => $this->order->order_number,
                    'ship_to_org_id' => $this->order->customer->customer_site_id,
                    'BOOKED_FLAG' => 'N',
                ];

                // Log the order header data being sent to Oracle
                \Log::info('Oracle Order Header Data', [
                    'order_number' => $this->order->order_number,
                    'customer_id' => $this->order->customer->customer_id,
                    'customer_name' => $this->order->customer->customer_name,
                    'header_data' => $orderHeaderData
                ]);

                // Create Oracle Order Header
                $oracleOrderHeader = OracleOrderHeader::create($orderHeaderData);

                // Create Oracle Order Lines
                foreach ($this->order->orderItems as $index => $orderItem) {
                    $selectedWarehouseId = $this->orderItemWarehouses[$index] ?? null;

                    // Update the local order item with the selected warehouse
                    $orderItem->update(['warehouse_id' => $selectedWarehouseId]);

                    // unit_list_price  → always the Oracle price-list price per unit
                    // unit_selling_price:
                    //   • cap_price set  → use cap_price directly (no discount applied)
                    //   • no cap_price   → derive from stored sub_total / qty (avoids float drift)
                    //                      capped between 0 and the list price
                    $unitListPrice = round((float) $orderItem->price, 2);

                    if ($orderItem->cap_price !== null) {
                        $unitSellingPrice = round((float) $orderItem->cap_price, 2);
                    } else {
                        $unitSellingPrice = $orderItem->quantity > 0
                            ? round((float) $orderItem->sub_total / $orderItem->quantity, 2)
                            : $unitListPrice;
                        $unitSellingPrice = (float) max(0, min($unitListPrice, $unitSellingPrice));
                    }

                    // Prepare Oracle Order Line data
                    $orderLineData = [
                        'order_source_id' => 1001,
                        // 'order_source' => "POS",
                        'orig_sys_document_ref' => $this->order->order_number,
                        'orig_sys_line_ref' => "{$this->order->order_number}-" . ($index + 1),
                        'line_number' => ($index + 1),
                        'inventory_item_id' => $orderItem->inventory_item_id,
                        'ordered_quantity' => $orderItem->quantity,
                        'unit_selling_price' => $unitSellingPrice,
                        'unit_list_price' => $unitListPrice, // Original price or cap price
                        'calculate_price_flag' => 'N', // Don't recalculate price, use our provided price
                        'ship_from_org_id' => $selectedWarehouseId,
                        'org_id' => $this->order->customer->ou_id, // Customer OU ID
                        'price_list_id' => $this->order->customer->price_list_id,
                        'payment_term_id' => 1004,
                        'created_by' => 0,
                        'creation_date' => Carbon::now(),
                        'last_updated_by' => 0,
                        'last_update_date' => Carbon::now(),
                        'line_type_id' => $oracleOrderType->line_type_id,
                        'order_quantity_uom' => $orderItem->uom,
                        'operation_code' => 'INSERT',
                    ];

                    // Log the order line data being sent to Oracle
                    \Log::info('Oracle Order Line Data', [
                        'order_number'       => $this->order->order_number,
                        'line_number'        => ($index + 1),
                        'item_code'          => $orderItem->item->item_code ?? 'N/A',
                        'item_description'   => $orderItem->item->item_description ?? 'N/A',
                        'price_list_id'      => $this->order->customer->price_list_id,
                        'price_list_name'    => $this->order->customer->price_list_name,
                        'quantity'           => $orderItem->quantity,
                        'unit_list_price'    => $unitListPrice,
                        'cap_price'          => $orderItem->cap_price,
                        'line_sub_total'     => $orderItem->sub_total,
                        'line_discount'      => $orderItem->discount,
                        'unit_selling_price' => $unitSellingPrice,
                        'line_data'          => $orderLineData,
                    ]);

                    OracleOrderLine::create($orderLineData);
                }

                // Update the order's oracle_at timestamp to mark it as successfully entered into Oracle
                $this->order->update([
                    'oracle_at' => now(),
                    'order_status' => OrderStatusEnum::ENTERED,
                    'pushed_by' => auth()->id()
                ]);

                return $oracleOrderHeader;
            });

            if ($order) {
                \Log::info('Oracle Order Sync Completed Successfully', [
                    'order_number' => $this->order->order_number,
                    'order_id' => $this->order->id,
                    'oracle_header_id' => $order->header_id ?? null,
                    'pushed_by' => auth()->id(),
                ]);

                // Push notification to the salesperson who placed the order
                app(\App\Services\OrderReceiptNotifier::class)->orderPushedToOracle($this->order);

                $this->reset('order');
                $this->dispatch('close');
                $this->notifyUser('Order Entered', 'Order entered to Oracle successfully.');
            } else {
                throw new \Exception('Order insertion failed.');
            }
        } catch (\Exception $e) {
            \Log::error('Order Oracle Sync Error: ' . $e->getMessage(), [
                'order_id' => $this->order->id ?? null,
                'order_number' => $this->order->order_number ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            $this->notifyUser('Error', 'An error occurred: ' . $e->getMessage(), 'danger');
        }
        // $this->reset('order');
        // $this->notifyUser('Feature Limitation', 'This feature will not work on cPanel.', 'danger');
        // $this->dispatch('close');
    }

    public function deleteOrder(Order $order)
    {
        try {
            // Check if user is admin
            if (!auth()->user()->isAdmin()) {
                $this->notifyUser('Unauthorized', 'You do not have permission to delete orders.', 'danger');
                return;
            }

            // Check if order has been synced to Oracle
            if ($order->oracle_at !== null) {
                $this->notifyUser('Cannot Delete', 'This order has already been sent to Oracle and cannot be deleted.', 'warning');
                return;
            }

            $orderNumber = $order->order_number;

            // Delete the order (order items will be cascade deleted if FK is set up)
            $order->delete();

            // Log the deletion
            \Log::info('Order Deleted', [
                'order_number' => $orderNumber,
                'order_id' => $order->id,
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
            ]);

            $this->notifyUser('Order Deleted', "Order #{$orderNumber} has been deleted successfully.");
        } catch (\Exception $e) {
            \Log::error('Order Deletion Error: ' . $e->getMessage(), [
                'order_id' => $order->id ?? null,
                'order_number' => $order->order_number ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            $this->notifyUser('Error', 'An error occurred while deleting the order: ' . $e->getMessage(), 'danger');
        }
    }

    public function render()
    {
        $pageTitle = "Orders List";
        return view('livewire.list-orders', compact('pageTitle'));
    }
}
