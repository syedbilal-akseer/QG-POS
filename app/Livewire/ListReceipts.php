<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\CustomerReceipt;
use Livewire\Component;
use Filament\Tables\Table;
use App\Traits\NotifiesUsers;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

#[Title('Receipts')]
class ListReceipts extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $receipt;

    public function table(Table $table): Table
    {
        $query = CustomerReceipt::with(['customer', 'createdBy', 'enteredBy']);

        $user = auth()->user();

        // Base location-based filtering (like in ListOrders)
        if (!$user->isAdmin()) {
            // Apply similar logic as in ReceiptController
            if (($user->isCmdKhi() || $user->isCmdLhr()) && !$user->hasAllSalespeopleAccess()) {
                $assignedSalespeopleIds = $user->getAssignedSalespeopleIds();

                if (!empty($assignedSalespeopleIds)) {
                    $query->whereIn('created_by', $assignedSalespeopleIds);

                    $query->whereHas('customer', function ($customerQuery) use ($allowedOuIds) {
                        $customerQuery->whereIn('ou_id', $allowedOuIds);
                    });
                }
            } else {
                $allowedOuIds = $user->getAllowedReceiptOuIds();
                if (!empty($allowedOuIds)) {
                    $query->whereHas('customer', function ($customerQuery) use ($allowedOuIds) {
                        $customerQuery->whereIn('ou_id', $allowedOuIds);
                    });
                } else if ($user->isSalesPerson()) {
                    // If salesperson, only show their own receipts
                    $query->where('created_by', $user->id);
                } else {
                    $query->where('id', -1);
                }
            }
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('customer.customer_name')
                    ->label('Customer')
                    ->description(fn (CustomerReceipt $record): string => "#{$record->customer_id}")
                    ->sortable()
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label('Salesperson')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('currency')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('receipt_amount')
                    ->label('Total Amount')
                    ->money(fn (CustomerReceipt $record) => $record->currency === 'USD' ? 'USD' : 'PKR')
                    ->sortable(),
                TextColumn::make('receipt_type')
                    ->label('Payment Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash_only' => 'success',
                        'cheque_only' => 'info',
                        'cash_and_cheque' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash_only' => 'Cash Only',
                        'cheque_only' => 'Cheque Only',
                        'cash_and_cheque' => 'Cash & Cheque',
                        default => $state,
                    }),
                TextColumn::make('enteredBy.name')
                    ->label('Pushed By')
                    ->default('-')
                    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr()),
            ])
            ->filters([
                SelectFilter::make('location')
                    ->label('Location')
                    ->options([
                        'karachi' => 'Karachi',
                        'lahore' => 'Lahore',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['value'], function ($query, $value) {
                            if ($value === 'karachi') {
                                return $query->whereHas('customer', fn($q) => $q->whereIn('ou_id', [102, 103, 104, 105, 106]));
                            } elseif ($value === 'lahore') {
                                return $query->whereHas('customer', fn($q) => $q->whereIn('ou_id', [108, 109]));
                            }
                        });
                    }),
                
                SelectFilter::make('currency')
                    ->options([
                        'PKR' => 'PKR',
                        'USD' => 'USD',
                    ]),

                SelectFilter::make('receipt_type')
                    ->label('Type')
                    ->options([
                        'cash_only' => 'Cash Only',
                        'cheque_only' => 'Cheque Only',
                        'cash_and_cheque' => 'Cash & Cheque',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'pushed' => 'Pushed to Oracle',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['value'], function ($query, $value) {
                            if ($value === 'pending') {
                                return $query->whereNull('oracle_entered_at');
                            } elseif ($value === 'pushed') {
                                return $query->whereNotNull('oracle_entered_at');
                            }
                        });
                    }),

                SelectFilter::make('receipt_year')
                    ->label('Year')
                    ->options(array_combine(range(date('Y'), 2020), range(date('Y'), 2020)))
                    ->attribute('receipt_year'),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->label('View')
                    ->url(fn (CustomerReceipt $record): string => route('admin.receipts.show', $record)),
                Action::make('edit')
                    ->icon('heroicon-m-pencil-square')
                    ->button()
                    ->label('Edit')
                    ->url(fn (CustomerReceipt $record): string => route('admin.receipts.edit', $record))
                    ->visible(fn (CustomerReceipt $record): bool => !$record->oracle_entered_at),
                DeleteAction::make()
                    ->button()
                    ->visible(fn (): bool => auth()->user()->isAdmin()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);

        if (filled($search = $this->getTableSearch())) {
            $searchTerm = '%' . $search . '%';

            $query->where(function ($query) use ($searchTerm) {
                $query->where('receipt_number', 'like', $searchTerm)
                    ->orWhere('cheque_no', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhereHas('customer', function ($q) use ($searchTerm) {
                        $q->where('customer_name', 'like', $searchTerm)
                            ->orWhere('customer_id', 'like', $searchTerm);
                    });
            });
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.list-receipts', [
            'pageTitle' => 'Receipts List'
        ]);
    }
}
