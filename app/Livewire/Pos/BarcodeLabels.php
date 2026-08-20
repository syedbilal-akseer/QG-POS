<?php

namespace App\Livewire\Pos;

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
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * Browse items and print Code128 labels (item_code only — see
 * ItemBarcodeLabelController) for the POS scan flow. Single print opens the
 * one-item PDF in a new tab; bulk print batches selected items into one PDF.
 */
#[Title('Barcode Labels')]
class BarcodeLabels extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(Item::query()->excludePackingMaterial())
            ->columns([
                TextColumn::make('item_code')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),
                TextColumn::make('item_description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('primary_uom_code')
                    ->label('UOM'),
            ])
            ->actions([
                Action::make('print')
                    ->label('Print Label')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Item $record) => route('pos.labels.single', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('printLabels')
                        ->label('Print Selected Labels')
                        ->icon('heroicon-o-printer')
                        ->action(function (Collection $records) {
                            $this->redirect(
                                route('pos.labels.batch', ['item_codes' => $records->pluck('item_code')->all()]),
                                navigate: false
                            );
                        }),
                ]),
            ])
            ->searchPlaceholder('Search item code or description...');
    }

    public function render(): View
    {
        return view('livewire.pos.barcode-labels');
    }
}
