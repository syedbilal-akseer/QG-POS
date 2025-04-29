<?php

namespace App\Livewire;

use App\Models\ItemPrice;
use Livewire\Component;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;

class ListItemPrice extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(ItemPrice::query()->latest())
            ->columns([
                TextColumn::make('item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_description')
                    ->label('Item Description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_list_name')
                    ->label('Price List Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('Unit of Measure')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('list_price')
                    ->label('List Price')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date_active')
                    ->label('Start Date')
                    ->dateTime('F j, Y')
                    ->sortable(),
                TextColumn::make('end_date_active')
                    ->label('End Date')
                    ->dateTime('F j, Y')
                    ->sortable(),
            ])
            ->filters([
                // Add any filters if needed
            ])
            ->actions([
                // Define any row actions here
            ])
            ->bulkActions([
                // Define any bulk actions here
            ])
            ->searchPlaceholder('Search Item Prices');
    }

    public function render(): View
    {
        return view('livewire.list-item-price');
    }
}
