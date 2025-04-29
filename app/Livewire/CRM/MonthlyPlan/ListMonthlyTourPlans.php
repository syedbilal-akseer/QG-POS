<?php

namespace App\Livewire\CRM\MonthlyPlan;

use App\Models\Visit;
use Livewire\Component;
use App\Models\Warehouse;
use Filament\Tables\Table;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;
use Livewire\Attributes\Title;
use App\Models\MonthlyTourPlan;
use App\Models\MonthlyVisitReport;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;


use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;


#[Title('Old Monthly Tour Plan')]
class OldListMonthlyTourPlans extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $tourPlanId = null;
    public ?array $formData = [
        'salesperson_id' => null,
        'month' => '',  // Format: '2024-10'
        'day_plans' =>   [],  // Array of Day Plans
    ];

    public ?array $visitFormData = [
        'day_tour_plan_id' => null,
        'visits' => [],
    ];

    public $tourPlan;
    public $dayTourPlans = [];
    public $selectedDayTourPlanId;

    public function mount()
    {
        
    }

    // Define the table directly
    public function table(Table $table): Table
    {
        return $table
            ->query(MonthlyTourPlan::where('salesperson_id', auth()->user()->id))
            ->columns([
                TextColumn::make('month')
                    ->label('Month')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn($state) => ucwords($state))
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (MonthlyTourPlan $record) {
                        $this->reset();
                        $this->redirectRoute('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $record]);
                    })
                    ->color('violet'),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->button()
                    ->action(function (MonthlyTourPlan $record) {
                        $this->reset();
                        $this->redirectRoute('monthlyTourPlans.addNewPlan', ['monthlyTourPlan' => $record]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function addNewPlan()
    {
        $this->redirect(CreatePlan::class);
    }

    public function edit(MonthlyTourPlan $tourPlan)
    {
        $this->redirectRoute('monthlyTourPlans.addNewPlan', ['monthlyTourPlan' => $tourPlan]);
    }

    public function render()
    {
        return view('livewire.crm.monthly-plan.list-monthly-tour-plans-old');
    }

    public function transferTo($salespersonId, $reason = null)
    {
        $this->transferred_to = $salespersonId;
        $this->transfer_reason = $reason;
        $this->transfer_status = 'pending'; // Set status to pending
        $this->save();
    }

    public function acceptTransfer()
    {
        $this->transfer_status = 'accepted';
        $this->transferred_to = null; // Clear the transferred field
        $this->save();
    }

    public function rejectTransfer($reason = null)
    {
        $this->transfer_status = 'rejected';
        $this->transfer_reason = $reason;
        $this->transferred_to = null; // Clear the transferred field
        $this->save();
    }
}

#[Title('Monthly Tour Plan')]
class ListMonthlyTourPlans extends Component
{
    use NotifiesUsers;
    use WithPagination;

    public $search = '';
    public $perPage = 10;

    // Redirect to add new plan
    public function addNewPlan()
    {
        $this->redirect(CreatePlan::class);
    }

    // Redirect to edit existing plan
    public function editPlan($planId)
    {
        $this->redirectRoute('monthlyTourPlans.addNewPlan', ['monthlyTourPlan' => $planId]);
    }

    // Redirect to view plan details
    public function viewPlan($planId)
    {
        $this->redirectRoute('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $planId]);
    }

    public function render()
    {
        $plans = MonthlyTourPlan::where('salesperson_id', Auth::user()->id)
            ->when($this->search, fn ($query) => $query->where('month', 'like', "%{$this->search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.crm.monthly-plan.list-monthly-tour-plans', [
            'plans' => $plans,
        ]);
    }
}