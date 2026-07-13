<?php

namespace App\Livewire;

use App\Models\PromotionalItem;
use App\Models\Item;
use App\Models\ItemPrice;
use Livewire\Component;
use Filament\Tables\Table;
use Livewire\Attributes\Title;
use App\Traits\LogsActivity;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Facades\Cache;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

#[Title('Promotional Items')]
class ListPromotionalItems extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use LogsActivity;

    public function table(Table $table): Table
    {
        return $table
            ->query(PromotionalItem::query()->with(['item', 'item.itemPrice']))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->circular()
                    ->disk('public'),
                TextColumn::make('item_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_list_name')
                    ->label('Price List')
                    ->searchable()
                    ->placeholder('All Price Lists')
                    ->sortable(),
                TextColumn::make('item.item_description')
                    ->label('Description')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('original_price')
                    ->label('Original Price')
                    ->money('PKR')
                    ->sortable(),
                TextColumn::make('discounted_price')
                    ->label('Discounted Price')
                    ->money('PKR')
                    ->sortable(),
                TextColumn::make('savings')
                    ->label('Savings')
                    ->getStateUsing(fn ($record) => (float)$record->original_price - (float)$record->discounted_price)
                    ->money('PKR')
                    ->color('success'),
                TextColumn::make('discount_percent')
                    ->label('% Off')
                    ->getStateUsing(fn ($record) => $record->original_price > 0 
                        ? round((( (float)$record->original_price - (float)$record->discounted_price) / (float)$record->original_price) * 100) . '%' 
                        : '0%')
                    ->color('warning'),
                TextColumn::make('scheme')
                    ->label('Scheme')
                    ->getStateUsing(function ($record) {
                        if (!$record->buy_qty || !$record->free_qty) return '—';
                        $freeOf = $record->free_item_code ?: $record->item_code;
                        return "Buy {$record->buy_qty} get {$record->free_qty} free ({$freeOf})";
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('scheme_start_date')
                    ->label('Scheme Start')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('scheme_end_date')
                    ->label('Scheme End')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Select::make('item_code')
                            ->label('Search Item')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Item::where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_description', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($item) => [$item->item_code => "{$item->item_code} - {$item->item_description}"])
                                ->toArray())
                            ->getOptionLabelUsing(fn ($value): ?string => Item::where('item_code', $value)->first()?->item_code . ' - ' . Item::where('item_code', $value)->first()?->item_description)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $item = Item::with('itemPrice')->where('item_code', $state)->first();
                                if ($item && $item->itemPrice) {
                                    $set('original_price', $item->itemPrice->list_price);
                                    $set('discounted_price', $item->itemPrice->list_price * 0.9); // Default 10% discount
                                }
                            })
                            ->required(),
                        Select::make('price_list_name')
                            ->label('Price List')
                            ->placeholder('Apply to all price lists')
                            ->options(fn () => Cache::remember(
                                'promotional_items_price_list_name_options',
                                now()->addHour(),
                                fn () => ItemPrice::distinct()->pluck('price_list_name', 'price_list_name')->toArray()
                            ))
                            ->searchable(),
                        FileUpload::make('image_path')
                            ->label('Promotional Image')
                            ->image()
                            ->disk('public')
                            ->directory('promotions'),
                        TextInput::make('original_price')
                            ->numeric()
                            ->prefix('PKR'),
                        TextInput::make('discounted_price')
                            ->numeric()
                            ->prefix('PKR')
                            ->lte('original_price'),
                        TextInput::make('buy_qty')
                            ->label('Buy Qty (scheme)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('e.g. 10 — leave blank if no buy-N-get-M scheme'),
                        TextInput::make('free_qty')
                            ->label('Free Qty (scheme)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('e.g. 1 — number of free units'),
                        Select::make('free_item_code')
                            ->label('Free Item Code')
                            ->helperText('Leave blank if the free item is the same as the purchased item')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Item::where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_description', 'like', "%{$search}%")
                                ->limit(50)->get()
                                ->mapWithKeys(fn ($i) => [$i->item_code => "{$i->item_code} - {$i->item_description}"])
                                ->toArray())
                            ->getOptionLabelUsing(fn ($value): ?string => Item::where('item_code', $value)->value('item_description')
                                ? $value . ' - ' . Item::where('item_code', $value)->value('item_description')
                                : $value),
                        DatePicker::make('scheme_start_date')->label('Scheme Start')->native(false),
                        DatePicker::make('scheme_end_date')->label('Scheme End')->native(false),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->after(function ($record) {
                        $this->updateItemPrices($record);
                        $this->logActivity('Promotions', 'create', "Promotional item created for {$record->item_code}.", [
                            'item_code' => $record->item_code,
                            'price' => $record->discounted_price
                        ]);
                    })
                    ->modalHeading('Add Promotional Item'),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Select::make('item_code')
                            ->label('Item Code')
                            ->disabled()
                            ->required(),
                        Select::make('price_list_name')
                            ->label('Price List')
                            ->placeholder('Apply to all price lists')
                            ->options(fn () => Cache::remember(
                                'promotional_items_price_list_name_options',
                                now()->addHour(),
                                fn () => ItemPrice::distinct()->pluck('price_list_name', 'price_list_name')->toArray()
                            ))
                            ->searchable(),
                        FileUpload::make('image_path')
                            ->label('Promotional Image')
                            ->image()
                            ->disk('public')
                            ->directory('promotions'),
                        TextInput::make('original_price')
                            ->numeric()
                            ->prefix('PKR'),
                        TextInput::make('discounted_price')
                            ->numeric()
                            ->prefix('PKR')
                            ->lte('original_price'),
                        TextInput::make('buy_qty')
                            ->label('Buy Qty (scheme)')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('free_qty')
                            ->label('Free Qty (scheme)')
                            ->numeric()
                            ->minValue(1),
                        Select::make('free_item_code')
                            ->label('Free Item Code')
                            ->helperText('Leave blank if the free item is the same as the purchased item')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Item::where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_description', 'like', "%{$search}%")
                                ->limit(50)->get()
                                ->mapWithKeys(fn ($i) => [$i->item_code => "{$i->item_code} - {$i->item_description}"])
                                ->toArray())
                            ->getOptionLabelUsing(fn ($value): ?string => Item::where('item_code', $value)->value('item_description')
                                ? $value . ' - ' . Item::where('item_code', $value)->value('item_description')
                                : $value),
                        DatePicker::make('scheme_start_date')->label('Scheme Start')->native(false),
                        DatePicker::make('scheme_end_date')->label('Scheme End')->native(false),
                        Toggle::make('is_active'),
                    ])
                    ->before(function ($record) {
                        // Clear the current/old prices before applying new ones
                        $query = ItemPrice::where('item_code', $record->item_code);
                        if ($record->price_list_name) {
                            $query->where('price_list_name', $record->price_list_name);
                        }
                        $query->update(['discounted_price' => null]);
                    })
                    ->after(function ($record) {
                        $this->updateItemPrices($record);
                        $this->logActivity('Promotions', 'update', "Promotional item updated for {$record->item_code}.", [
                            'item_code' => $record->item_code,
                            'price' => $record->discounted_price
                        ]);
                    }),
                DeleteAction::make()
                    ->after(function ($record) {
                        // Clear discounted price when promotion is deleted
                        $query = ItemPrice::where('item_code', $record->item_code);
                        if ($record->price_list_name) {
                            $query->where('price_list_name', $record->price_list_name);
                        }
                        $query->update(['discounted_price' => null]);
                        $this->logActivity('Promotions', 'delete', "Promotional item deleted for {$record->item_code}.", [
                            'item_code' => $record->item_code
                        ]);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                $query = ItemPrice::where('item_code', $record->item_code);
                                if ($record->price_list_name) {
                                    $query->where('price_list_name', $record->price_list_name);
                                }
                                $query->update(['discounted_price' => null]);
                            }
                        }),
                ]),
            ])
            ->searchPlaceholder('Search Promotions');
    }

    private function updateItemPrices($record)
    {
        if (!$record->is_active) {
            $discount = null;
        } else {
            $discount = $record->discounted_price;
        }

        $query = ItemPrice::where('item_code', $record->item_code);
        
        if ($record->price_list_name) {
            $query->where('price_list_name', $record->price_list_name);
        }

        $query->update(['discounted_price' => $discount]);
    }

    public function render(): View
    {
        return view('livewire.list-promotional-items');
    }
}
