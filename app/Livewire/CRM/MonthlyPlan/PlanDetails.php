<?php

namespace App\Livewire\CRM\MonthlyPlan;

use Livewire\Component;
use Filament\Tables\Table;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;
use App\Models\MonthlyTourPlan;
use Livewire\Attributes\Computed;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class PlanDetails extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public MonthlyTourPlan $monthlyTourPlan;

    public function mount(MonthlyTourPlan $monthlyTourPlan)
    {
        $user = auth()->user();

        // Check if the user is the creator of the plan
        if ($monthlyTourPlan->salesperson_id == $user->id) {
            $this->monthlyTourPlan = $monthlyTourPlan;
            return;
        }

        // Check if the user is the Line Manager of the salesperson
        if ($user->isManager() && $monthlyTourPlan->getLineManager()?->id == $user->id) {
            $this->monthlyTourPlan = $monthlyTourPlan;
            return;
        }

        // Check if the user is the HOD of the salesperson's department
        if ($user->isHOD() || $user->isAdmin() && $monthlyTourPlan->getHod()?->id == $user->id) {
            $this->monthlyTourPlan = $monthlyTourPlan;
            return;
        }

        // Deny access if the user doesn't match any of the conditions
        $this->notify('Unauthorized access', 'You are not authorized to access this tour plan.', 'danger');
        $this->redirectRoute('monthlyTourPlans.all');
    }


    public function table(Table $table): Table
    {
        return $table
            ->query(DayTourPlan::where('monthly_tour_plan_id', $this->monthlyTourPlan->id))
            ->columns([
                TextColumn::make('day')
                    ->label('Day')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('date')
                    ->label('Date')
                    ->sortable(),
                TextColumn::make('from_location')
                    ->label('From Location')
                    ->sortable(),
                TextColumn::make('to_location')
                    ->label('To Location')
                    ->sortable(),
                TextColumn::make('is_night_stay')
                    ->label('Night Stay')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No'),
            ])
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (DayTourPlan $record) {
                        $this->redirectRoute('dayTourPlans.details', ['dayTourPlan' => $record->id]);
                    })
                    ->color('violet'),

                // "View MVR" button visible if there are visits
                // Action::make('view_mvr')
                //     ->label('View MVR')
                //     ->icon('heroicon-o-eye')
                //     ->button()
                //     ->visible(fn(DayTourPlan $record) => $record->visits()->count() > 0) // Show if there are visits
                //     ->action(function (DayTourPlan $record) {
                //         $this->redirectRoute('visit.reportDetails', ['plan' => $record]);
                //     }),

                // "Add MVR" button visible if there are no visits
                Action::make('add_mvr')
                    ->label('Add MVR')
                    ->icon('heroicon-o-plus-circle') // Plus circle icon for adding MVR
                    ->button()
                    ->visible(fn(DayTourPlan $record) => $record->visits()->count() === 0 && $record->monthlyTourPlan->salesperson_id === auth()->user()->id) // Show only if no visits and salesperson matches
                    ->action(function (DayTourPlan $record) {
                        $this->reset();
                        $this->redirectRoute('visit.createMvr', ['dayTourPlan' => $record->id]);
                    }),
            ])
            ->defaultSort('date', 'asc'); // Optional sorting by date
    }


    #[Computed]
    public function tourPlan()
    {
        return $this->monthlyTourPlan ?? MonthlyTourPlan::findOrFail(request()->route('monthlyTourPlan'));
    }

    public function render()
    {
        // Dynamically set the title
        $title = $this->tourPlan ? "Tour Plan Details for {$this->tourPlan->month}" : 'Add New Plan';

        return view('livewire.crm.monthly-plan.plan-details', [
            'title' => $title,
        ])->layoutData(['title' => $title]);
    }
}
