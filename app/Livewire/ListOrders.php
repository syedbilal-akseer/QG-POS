<?php

namespace App\Livewire;

use App\Models\Order;
use Livewire\Component;
use Filament\Tables\Table;
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

    public $order;

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
                TextColumn::make('created_at')
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
            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ])
            ->deferLoading();
    }

    public function openDetailModal(Order $order)
    {
        $this->order = $order->load(['customer', 'orderItems.item.itemPrice']);
        $this->dispatch('open-modal', 'order_detail');
    }

    public function closeDetailModal()
    {
        $this->reset('order');
        $this->dispatch('close');
    }

    public function render()
    {
        return view('livewire.list-orders');
    }
}
