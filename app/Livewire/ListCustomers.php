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
use Illuminate\Database\Eloquent\Builder;
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
                TextColumn::make('contact_number')
                    ->searchable()
                    ->visibleFrom('md')
                    ->sortable(),
            ])
            ->filters([
                // Add any specific filters if needed
            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->label('View Details')
                    ->action(fn(Customer $record) => $this->openDetailModal($record)),
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
