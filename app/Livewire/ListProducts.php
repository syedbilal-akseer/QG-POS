<?php

namespace App\Livewire;

use App\Models\Item;
use Livewire\Component;
use Filament\Tables\Table;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

#[Title('Products')]
class ListProducts extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(Item::with('itemPrice'))  // Eager load itemPrice relationship
            ->columns([
                TextColumn::make('item_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primary_uom_code')
                    ->searchable()
                    ->visibleFrom('md')
                    ->sortable(),
                TextColumn::make('secondary_uom_code')
                    ->searchable()
                    ->visibleFrom('md')
                    ->sortable(),
                // Add a column for the price
                TextColumn::make('itemPrice.list_price')  // Access the price from the eager-loaded itemPrice
                    ->label('Price')  // You can change the label to something more suitable
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return $state ? 'Rs ' . number_format($state, 2) : 'N/A'; // Format price or show 'N/A' if no price exists
                    }),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->actions([])
            ->bulkActions([
                // Add bulk actions if needed
            ])
            ->searchPlaceholder('Product Search');
    }

    public function render(): View
    {
        return view('livewire.list-products');
    }
}