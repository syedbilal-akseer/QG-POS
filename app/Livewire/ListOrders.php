<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\Order;
use Livewire\Component;
use App\Models\OrderType;
use App\Models\Warehouse;
use Filament\Tables\Table;
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
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Concerns\InteractsWithTable;


#[Title('Orders')]
class ListOrders extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

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
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr()),
                TextColumn::make('order_status')
                    ->label('Order Status')
                    ->badge()
                    ->colors(OrderStatusEnum::badgeColors())
                    ->formatStateUsing(fn($state) => $state->name())
                    ->sortable()
                    ->searchable(),
                TextColumn::make('pushedBy.name')
                    ->label('Pushed to Oracle By')
                    ->sortable()
                    ->searchable()
                    ->default('N/A')
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr()),
                TextColumn::make('created_at')
                    ->visibleFrom('md')
                    ->label('Order Date')
                    ->dateTime('F j, Y, g:i a')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('order_status')
                    ->label('Status')
                    ->options(OrderStatusEnum::asArray())
                    ->attribute('order_status')
                    ->placeholder('Select a status'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Order Date From')
                            ->native(false),
                        DatePicker::make('created_until')
                            ->label('Order Date Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['created_from'] && !$data['created_until']) {
                            return null; // No filter applied
                        }

                        $from = $data['created_from'] ? Carbon::parse($data['created_from'])->toFormattedDateString() : 'N/A';
                        $until = $data['created_until'] ? Carbon::parse($data['created_until'])->toFormattedDateString() : 'N/A';

                        return 'Order Date from ' . $from . ' to ' . $until;
                    }),

            ])
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
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->label('View Order')
                    ->action(fn(Order $record) => $this->openDetailModal($record)),
                Action::make('syncDetails')
                    ->icon('heroicon-m-cloud-arrow-up')
                    ->button()
                    ->label('View Sync Details')
                    ->action(fn(Order $record) => $this->openSyncDetailsModal($record))
                    ->visible(fn(Order $record) => $record->oracle_at !== null
                        && $record->orderItems->flatMap(fn($item) => $item->syncHistory)->isNotEmpty())
                    ->color('violet'),
                Action::make('delete')
                    ->icon('heroicon-m-trash')
                    ->button()
                    ->label('Delete')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Order')
                    ->modalDescription('Are you sure you want to delete this order? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete')
                    ->action(fn(Order $record) => $this->deleteOrder($record))
                    ->visible(fn() => auth()->user()->isAdmin())
                    ->color('danger')

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
        // Load the order along with its order items, item details, customer, and salesperson
        $this->order = $order->load(['orderItems.item', 'customer', 'salesperson']);

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

                    // Calculate unit selling price by deducting the per-unit discount
                    $unitDiscount = $orderItem->quantity > 0 ? ($orderItem->discount / $orderItem->quantity) : 0;
                    $unitSellingPrice = $orderItem->price - $unitDiscount;

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
                        'unit_list_price' => $orderItem->price, // Original price from price list
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
                        'order_number' => $this->order->order_number,
                        'line_number' => ($index + 1),
                        'item_code' => $orderItem->item->item_code ?? 'N/A',
                        'item_description' => $orderItem->item->item_description ?? 'N/A',
                        'price_list_id' => $this->order->customer->price_list_id,
                        'price_list_name' => $this->order->customer->price_list_name,
                        'original_price' => $orderItem->price,
                        'unit_discount' => $unitDiscount,
                        'final_unit_price' => $unitSellingPrice,
                        'calculate_price_flag' => 'N',
                        'unit_list_price' => $orderItem->price,
                        'line_data' => $orderLineData
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
