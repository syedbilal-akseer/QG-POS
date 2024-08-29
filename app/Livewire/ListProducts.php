<?php

namespace App\Livewire;

use App\Models\Item;
use Livewire\Component;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class ListProducts extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(Item::query())
            ->columns([
                TextColumn::make('item_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primary_uom_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('secondary_uom_code')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                // ...
            ])
            ->actions([])
            ->bulkActions([
                // ...
            ])
            ->searchPlaceholder('Product Search');
    }

    public function render(): View
    {
        return view('livewire.list-products');
    }
}
