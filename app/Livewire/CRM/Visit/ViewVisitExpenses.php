<?php

namespace App\Livewire\CRM\Visit;

use App\Models\Visit;
use App\Models\VisitExpense;
use Livewire\Component;
use Filament\Tables\Table;
use App\Traits\NotifiesUsers;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class ViewVisitExpenses extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public Visit $visit;

    public function mount(Visit $visit)
    {
        if($visit->monthlyVisitReport->salesperson_id != auth()->user()->id) {
            $this->notify('Unauthorized access', 'You are not authorized to view expenses.', 'danger');
            $this->redirectRoute('visits.all');
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->visit->expenses()->getQuery())
            ->columns([
                TextColumn::make('expense_type')
                    ->label('Type')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn($state) => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('total')
                    ->label('Total')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn($state) => ucwords($state)),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->button()
                    ->action(function (VisitExpense $record) {
                        $this->reset();
                        $this->redirectRoute('expense.addExpense', [
                            'visit' => $record->visit->id,
                            'expenseId' => $record->id,
                        ]);
                    })
                    ->visible(fn($record) => $record->status === 'pending')
                    ->color('violet'),
                    Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (VisitExpense $record) {
                        $this->reset();
                        $this->redirectRoute('expense.details', ['expense' => $record]);
                    }),

            ]);
    }

    public function render()
    {
        $title = 'View Visit Expenses';
        return view('livewire.crm.visit.view-visit-expenses', [
            'title' => $title,
            'visit' => $this->visit,
        ])->layoutData(['title' => $title]);
    }
}
