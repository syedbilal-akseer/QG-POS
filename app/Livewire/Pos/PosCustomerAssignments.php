<?php

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\User;
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
use Filament\Forms\Components\Select;

/**
 * Admin-only, one-time-per-shop setup: bind each POS login to the walk-in
 * customer account it sells against. PosTerminal reads users.pos_customer_id
 * at mount and never resolves it by name matching — this screen is the only
 * place that value gets set. Hard-filtered to role=user — POS is a
 * salesperson tool, other roles are never assignable here.
 */
#[Title('POS Assignments')]
class PosCustomerAssignments extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->where('role', 'user')->with('posCustomer'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('posCustomer.customer_name')
                    ->label('Walk-in Account')
                    ->placeholder('Not assigned')
                    ->badge()
                    ->color(fn ($record) => $record->pos_customer_id ? 'success' : 'warning')
                    ->searchable(),
                TextColumn::make('posCustomer.customer_number')
                    ->label('Customer #'),
                TextColumn::make('posCustomer.price_list_name')
                    ->label('Price List')
                    ->placeholder('—'),
            ])
            ->actions([
                Action::make('assign')
                    ->label(fn ($record) => $record->pos_customer_id ? 'Change' : 'Assign')
                    ->icon('heroicon-o-link')
                    ->form([
                        Select::make('pos_customer_id')
                            ->label('Walk-in Customer')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Customer::where('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_number', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => "{$c->customer_name} ({$c->customer_number}) — {$c->price_list_name}"])
                                ->toArray())
                            ->getOptionLabelUsing(function ($value): ?string {
                                $c = Customer::find($value);
                                return $c ? "{$c->customer_name} ({$c->customer_number}) — {$c->price_list_name}" : null;
                            })
                            ->required(),
                    ])
                    ->fillForm(fn ($record) => ['pos_customer_id' => $record->pos_customer_id])
                    ->action(function ($record, array $data) {
                        $record->update(['pos_customer_id' => $data['pos_customer_id']]);
                        $customer = Customer::find($data['pos_customer_id']);
                        notify('Walk-in account assigned', "{$record->name} → {$customer?->customer_name}", 'success');
                    }),
                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => (bool) $record->pos_customer_id)
                    ->requiresConfirmation()
                    ->modalDescription('Remove this user\'s walk-in account link? They won\'t be able to use POS until reassigned.')
                    ->action(function ($record) {
                        $record->update(['pos_customer_id' => null]);
                        notify('Walk-in account removed', "{$record->name} is no longer linked to a POS account.", 'warning');
                    }),
            ])
            ->searchPlaceholder('Search salesperson name or email...');
    }

    public function render(): View
    {
        return view('livewire.pos.pos-customer-assignments');
    }
}
