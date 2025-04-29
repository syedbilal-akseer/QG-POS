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
        return $table
            ->query(Order::query())
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order Number')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.customer_name')
                    ->label('Customer Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('order_status')
                    ->label('Order Status')
                    ->badge()
                    ->colors(OrderStatusEnum::badgeColors())
                    ->formatStateUsing(fn($state) => $state->name())
                    ->sortable()
                    ->searchable(),
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
                    ->color('violet')

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
                    // ->orWhereHas('warehouse', function ($q) use ($searchTerm) {
                    //     $q->where('warehouse_name', 'like', $searchTerm)
                    //         ->orWhere('warehouse_number', 'like', $searchTerm);
                    // })

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
        // Load the order along with its order items and customer
        $this->order = $order->load(['orderItems', 'customer']);

        // Filter the warehouses based on the customer's ou_id
        $this->warehouses = Warehouse::where('ou', $this->order->customer->ou_id)->get();

        // Initialize the orderItemWarehouses array with existing warehouse IDs or null
        $this->orderItemWarehouses = $this->order->orderItems->mapWithKeys(function ($item, $index) {
            return [$index => $item->warehouse_id ?? null];
        })->toArray();

        // Dispatch the event to open the order detail modal
        $this->dispatch('open-modal', 'order_detail');
    }

    public function openSyncDetailsModal(Order $order)
    {
        // Load order items along with the sync history (discrepancies)
        $this->orderDetails = $order->load(['orderItems.syncHistory']);
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
        $this->validate([
            'orderItemWarehouses.*' => 'required',
        ], [
            'orderItemWarehouses.*.required' => 'Warehouse must be selected for order item.',
        ]);

        try {
            $order = DB::connection('oracle')->transaction(function () {
                // Fetch the relevant order type and line type based on ou_id
                $oracleOrderType = OrderType::where('org_id', $this->order->customer->ou_id)->first();

                if (!$oracleOrderType) {
                    throw new \Exception("Order type or line type not found for org_id: {$this->order->customer->ou_id}");
                }

                // Create Oracle Order Header
                $oracleOrderHeader = OracleOrderHeader::create([
                    'order_source_id' => 1001,
                    'orig_sys_document_ref' => $this->order->order_number,
                    'org_id' => $this->order->customer->ou_id, // Customer OU ID
                    'sold_from_org_id' => $this->order->customer->ou_id, // Customer OU ID
                    'ordered_date' => Carbon::now(),
                    'order_type_id' => $oracleOrderType->order_type_id,
                    'sold_to_org_id' => $this->order->customer->customer_id,
                    'payment_term_id' => 1004,
                    'operation_code' => 'INSERT',
                    'created_by' => 0,
                    'creation_date' => Carbon::now(),
                    'last_updated_by' => 0,
                    'last_update_date' => Carbon::now(),
                    'customer_po_number' => $this->order->order_number,
                    'ship_to_org_id' => $this->order->customer->customer_site_id,
                    'BOOKED_FLAG' => 'N',
                ]);

                // Create Oracle Order Lines
                foreach ($this->order->orderItems as $index => $orderItem) {
                    $selectedWarehouseId = $this->orderItemWarehouses[$index] ?? null;

                    // Update the local order item with the selected warehouse
                    $orderItem->update(['warehouse_id' => $selectedWarehouseId]);

                    logger("Item Price: $orderItem->price");
                    OracleOrderLine::create([
                        'order_source_id' => 1001,
                        // 'order_source' => "POS",
                        'orig_sys_document_ref' => $this->order->order_number,
                        'orig_sys_line_ref' => "{$this->order->order_number}-" . ($index + 1),
                        'line_number' => ($index + 1),
                        'inventory_item_id' => $orderItem->inventory_item_id,
                        'ordered_quantity' => $orderItem->quantity,
                        'unit_selling_price' => $orderItem->price,
                        // 'unit_selling_price' => $orderItem->item->itemPrice->where('price_list_id', $this->order->customer->price_list_id)->first()->list_price,
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
                    ]);
                }

                // Update the order's oracle_at timestamp to mark it as successfully entered into Oracle
                $this->order->update(['oracle_at' => now()]);
                $this->order->update(['order_status' => OrderStatusEnum::ENTERED]);

                return $oracleOrderHeader;
            });

            if ($order) {
                $this->reset('order');
                $this->dispatch('close');
                $this->notifyUser('Order Entered', 'Order entered to Oracle successfully.');
            } else {
                throw new \Exception('Order insertion failed.');
            }
        } catch (\Exception $e) {
            $this->notifyUser('Error', 'An error occurred while entering the order to Oracle.', 'danger');
            // $this->notifyUser('Error', 'An error occurred: ' . $e->getMessage(), 'danger');
        }
        // $this->reset('order');
        // $this->notifyUser('Feature Limitation', 'This feature will not work on cPanel.', 'danger');
        // $this->dispatch('close');
    }

    public function render()
    {
        $pageTitle = "Orders List";
        return view('livewire.list-orders', compact('pageTitle'));
    }
}
