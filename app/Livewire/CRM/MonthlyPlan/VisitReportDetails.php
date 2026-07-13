<?php

namespace App\Livewire\CRM\MonthlyPlan;

use App\Models\Visit;
use Livewire\Component;
use App\Traits\NotifiesUsers;
use App\Models\MonthlyVisitReport;
use Filament\Tables\Table;
use Livewire\Attributes\Computed;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class VisitReportDetails extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public MonthlyVisitReport $monthlyVisitReport;

    public function mount(MonthlyVisitReport $monthlyVisitReport)
    {
        if ($monthlyVisitReport->salesperson_id != auth()->user()->salesperson_id) {
            $this->notify('Unauthorized access', 'You are not authorized to access this tour plan.', 'danger');
            $this->redirectRoute('monthlyTourPlans.all', navigate: true);
        } else {
            $this->monthlyVisitReport = $monthlyVisitReport;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Visit::where('monthly_visit_report_id', $this->monthlyVisitReport->id))
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->sortable(),
                TextColumn::make('outlet_type')
                    ->label('Outlet Type')
                    ->sortable(),
                TextColumn::make('shop_category')
                    ->label('Shop Category')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (Visit $record) {
                        $this->reset();
                        $this->redirectRoute('visit.details', ['visit' => $record->id]);
                    })
                    ->color('violet'),
                Action::make('add_expense')
                    ->label('Add Expense')
                    ->icon('heroicon-o-plus-circle') // Plus circle icon for adding MVR
                    ->button()
                    ->action(function (Visit $record) {
                        $this->reset();
                        $this->redirectRoute('visit.addExpense', ['visit' => $record->id]);
                    }),
            ])
            ->defaultSort('created_at', 'asc'); // Optional sorting by date
    }

    #[Computed]
    public function tourPlan()
    {
        return $this->monthlyVisitReport;
    }

    public function render()
    {
        $title = $this->tourPlan ? "Market Visits Reports for {$this->tourPlan->month}" : 'Add New Plan';

        return view('livewire.crm.monthly-plan.visit-report-details', [
            'title' => $title,
        ])->layoutData(['title' => $title]);
    }
}
