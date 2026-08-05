<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Customer;
use Filament\Tables\Table;
use Livewire\Attributes\Title;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

#[Title('Customers')]
class ListCustomers extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $customer;

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query())
            ->columns([
                TextColumn::make('ou_id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_number')
                    ->label('Account Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('salesperson')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_number')
                    ->searchable()
                    ->visibleFrom('md')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('location')
                    ->label('Location')
                    ->placeholder('All locations')
                    ->options([
                        'karachi' => 'Karachi',
                        'lahore' => 'Lahore',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['value'], function ($query, $value) {
                            if ($value === 'karachi') {
                                return $query->whereIn('ou_id', [102, 103, 104, 105, 106]);
                            } elseif ($value === 'lahore') {
                                return $query->whereIn('ou_id', [108, 109]);
                            }
                        });
                    }),

                SelectFilter::make('salesperson')
                    ->label('Salesperson')
                    ->placeholder('All salespeople')
                    ->attribute('salesperson')
                    ->options(fn () => Cache::remember(
                        'list_customers_salesperson_filter_options',
                        now()->addMinutes(30),
                        fn () => Customer::query()
                            ->whereNotNull('salesperson')
                            ->where('salesperson', '!=', '')
                            ->distinct()
                            ->orderBy('salesperson')
                            ->pluck('salesperson', 'salesperson')
                            ->toArray()
                    ))
                    ->searchable()
                    ->preload(),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->label('View Details')
                    ->action(fn(Customer $record) => $this->openDetailModal($record)),
                // Jumps straight into that customer's document explorer
                // (Invoices/Builties tree) — only shown to roles that can
                // actually reach documents.* (see routes/web.php), so
                // cmd-khi/cmd-lhr users who can see this list don't get a
                // link into a route they're blocked from.
                Action::make('documents')
                    ->icon('heroicon-m-folder-open')
                    ->button()
                    ->color('gray')
                    ->label('Documents')
                    ->visible(fn (Customer $record) => filled($record->customer_id)
                        && (auth()->user()->isAdmin()
                            || auth()->user()->isInvoiceManager()
                            || auth()->user()->hasViewKhi()
                            || auth()->user()->hasViewLhr()
                            || auth()->user()->hasViewAll()))
                    ->url(fn (Customer $record) => route('documents.customer', $record->customer_id)),
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
            $query->where(function ($query) use ($search) {
                $searchTerm = '%' . $search . '%';

                $query->where('ou_id', 'like', $searchTerm)
                    ->orWhere('customer_name', 'like', $searchTerm)
                    ->orWhere('customer_number', 'like', $searchTerm)
                    ->orWhere('contact_number', 'like', $searchTerm)
                    ->orWhere('email_address', 'like', $searchTerm)
                    ->orWhere('city', 'like', $searchTerm)
                    ->orWhere('area', 'like', $searchTerm)
                    ->orWhere('address1', 'like', $searchTerm)
                    ->orWhere('nic', 'like', $searchTerm)
                    ->orWhere('ntn', 'like', $searchTerm)
                    ->orWhere('price_list_name', 'like', $searchTerm)
                    ->orWhere('customer_id', 'like', $searchTerm)
                    ->orWhere('ou_name', 'like', $searchTerm)
                    ->orWhere('customer_site_id', 'like', $searchTerm)
                    ->orWhere('salesperson', 'like', $searchTerm)
                    ->orWhere('creation_date', 'like', $searchTerm)
                    ->orWhere('price_list_id', 'like', $searchTerm);
            });
        }

        return $query;
    }

    public function openDetailModal(Customer $customer)
    {
        $this->customer = $customer;
        $this->dispatch('open-modal', 'customer_detail');
    }

    public function closeDetailModal()
    {
        $this->reset('customer');
        $this->dispatch('close');
    }

    public function render()
    {
        return view('livewire.list-customers');
    }
}
