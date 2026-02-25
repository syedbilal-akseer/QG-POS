<?php

namespace App\Livewire\CRM\Visit;

use App\Models\Visit;
use Livewire\Component;
use Filament\Tables\Table;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;
use Livewire\Attributes\Computed;
use App\Models\MonthlyVisitReport;
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

    public $plan; // Can be either MonthlyVisitReport or DayTourPlan
    public string $type; // Type of plan (monthly or daily)

    public function mount($plan)
    {
        if ($plan instanceof MonthlyVisitReport) {
            if ($plan->salesperson_id != auth()->user()->id) {
                $this->notify('Unauthorized access', 'You are not authorized to access this tour plan.', 'danger');
                $this->redirectRoute('visits.all');
            }
            $this->type = 'monthly';
        } elseif ($plan instanceof DayTourPlan) {
            if ($plan->monthlyTourPlan->salesperson_id != auth()->user()->id) {
                $this->notify('Unauthorized access', 'You are not authorized to access this day tour plan.', 'danger');
                $this->redirectRoute('monthlyTourPlans.all');
            }
            $this->type = 'daily';
        } else {
            abort(404, 'Invalid plan type.');
        }

        $this->plan = $plan;
    }

    public function table(Table $table): Table
    {
        $query = ($this->type === 'monthly')
            ? Visit::where('monthly_visit_report_id', $this->plan->id)
            : $this->plan->visits()->getQuery();

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('outlet_type')
                    ->label('Outlet Type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('shop_category')
                    ->label('Shop Category')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable(),
            ])
            ->actions([
                Action::make('edit_mvr')
                    ->label('Edit MVR')
                    ->icon('heroicon-o-pencil')
                    ->button()
                    ->visible(fn(Visit $record) => $record->status === 'pending') // Show if the status is pending
                    ->action(function (Visit $record) {
                        $this->reset();
                        $this->redirectRoute('visit.createMvr', [
                            'dayTourPlan' => $record->dayTourPlan->id,
                            'visitId' => $record->id,
                        ]);
                    }),

                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (Visit $record) {
                        $this->reset();
                        $this->redirectRoute('visit.details', ['visit' => $record->id]);
                    })
                    ->color('gray'),

                Action::make('view_expenses')
                    ->label('View Expenses')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->visible(fn(Visit $record) => $record->expenses()->count() > 0)
                    ->action(function (Visit $record) {
                        $this->reset();
                        $this->redirectRoute('visit.viewExpenses', ['visit' => $record->id]);
                    })
                    ->color('gray'),

                Action::make('add_expense')
                    ->label('Add Expense')
                    ->icon('heroicon-o-plus-circle')
                    ->button()
                    ->visible(fn(Visit $record) => $record->expenses()->count() === 0)
                    ->action(function (Visit $record) {
                        $this->reset();
                        $this->redirectRoute('expense.addExpense', ['visit' => $record->id]);
                    })
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'asc');
    }

    public function render()
    {
        $title = $this->type === 'monthly'
            ? "Market Visits Reports for {$this->plan->month}"
            : "Market Visits Reports for {$this->plan->day} - {$this->plan->date}";

        return view('livewire.crm.visit.visit-report-details', [
            'title' => $title,
        ])->layoutData(['title' => $title]);
    }
}
