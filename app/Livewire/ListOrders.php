<?php

namespace App\Livewire;

use App\Models\Order;
use Livewire\Component;
use App\Models\Warehouse;
use Filament\Tables\Table;
use App\Enums\OrderStatusEnum;
use App\Models\OracleOrderLine;
use App\Models\OracleOrderHeader;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class ListOrders extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $order, $orderDetails;
    public $warehouses;
    public $orderItemWarehouses = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest())
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
                    ->visible(fn(Order $record) => $record->orderItems->flatMap(fn($item) => $item->syncHistory)->isNotEmpty())
                    ->color('violet')

            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ])
            ->deferLoading();
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
        $this->order = $order->load(['orderItems']);
        $this->warehouses = Warehouse::all();

        // Initialize the orderItemWarehouses array with existing warehouse IDs or null
        $this->orderItemWarehouses = $this->order->orderItems->mapWithKeys(function ($item, $index) {
            return [$index => $item->warehouse_id ?? null];
        })->toArray();

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
                // Create Oracle Order Header
                $oracleOrderHeader = OracleOrderHeader::create([
                    'order_source_id' => $this->order->order_number,
                    'orig_sys_document_ref' => $this->order->order_number,
                    'customer_id' => $this->order->customer->customer_id,
                    'customer_name' => $this->order->customer->customer_name,
                    'customer_number' => $this->order->customer->customer_number,
                    'price_list_id' => $this->order->customer->price_list_id,
                    'created_by' => auth()->user()->id,
                    'ordered_date' => now(),
                    'sold_to_org_id' => 1641,
                    'creation_date' => now(),
                    'order_source' => "POS",
                    'last_updated_by' => auth()->user()->id,
                    'last_update_date' => now(),
                    'operation_code' => 'INSERT',
                ]);

                // Create Oracle Order Lines
                foreach ($this->order->orderItems as $index => $orderItem) {
                    $selectedWarehouseId = $this->orderItemWarehouses[$index] ?? null;

                    // Update the local order item with the selected warehouse
                    $orderItem->update(['warehouse_id' => $selectedWarehouseId]);

                    OracleOrderLine::create([
                        'order_source_id' => $oracleOrderHeader->id,
                        'orig_sys_document_ref' => $this->order->order_number,
                        'orig_sys_line_ref' => $this->order->order_number,
                        'line_number' => $index,
                        'inventory_item_id' => $orderItem->inventory_item_id,
                        'ordered_quantity' => $orderItem->quantity,
                        'org_id' => $selectedWarehouseId,
                        'created_by' => auth()->user()->id,
                        'creation_date' => now(),
                        'last_updated_by' => auth()->user()->id,
                        'last_update_date' => now(),
                        'operation_code' => "INSERT",
                    ]);
                }

                // Update the order's oracle_at timestamp to mark it as successfully entered into Oracle
                $this->order->update(['oracle_at' => now()]);

                return $oracleOrderHeader;
            });

            if ($order) {
                $this->reset('order');
                $this->dispatch('close');
                $this->dispatch('toast-success', 'Order entered to Oracle successfully.');
            } else {
                throw new \Exception('Order insertion failed.');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast-error', 'An error occurred while entering the order to Oracle.');
            // $this->dispatch('toast-error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.list-orders');
    }
}
