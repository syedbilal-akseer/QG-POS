<?php

namespace App\Livewire\CRM\Visit;

use Filament\Tables;
use App\Models\Visit;
use Livewire\Component;
use App\Models\Warehouse;
use App\Models\DayTourPlan;
use App\Models\VisitExpense;
use App\Traits\NotifiesUsers;
use Livewire\Attributes\Title;
use App\Models\MonthlyTourPlan;
use App\Models\MonthlyVisitReport;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

#[Title('Visits')]
class ManageVisit extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $visits;
    public ?array $expenseFormData = [
        'visit_id' => null,
        'expenses' => [],
    ];

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(MonthlyVisitReport::where('salesperson_id', auth()->user()->id))
            ->columns([
                Tables\Columns\TextColumn::make('month')->label('Month')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('monthlyTourPlan.month')->label('Tour Plan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('visits_count')->label('Total Visits')->counts('visits'), // Count visits
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View MVRs')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (MonthlyVisitReport $record) {
                        $this->reset();
                        $this->redirectRoute('visit.reportDetails', ['plan' => $record]);
                    })
                    ->color('violet'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function viewDetails(MonthlyVisitReport $monthlyVisitReport)
    {
        // Load all the visits for the selected MonthlyVisitReport
        $this->visits = $monthlyVisitReport->visits;

        // Open the modal
        $this->dispatch('open-modal', 'visit_details_modal');
    }

    public function openAddExpenseModal($visitId)
    {
        $this->resetExpenseForm();
        $this->expenseFormData['visit_id'] = $visitId;
        $this->addExpense();
        $this->dispatch('open-modal', 'expense_add_modal');
    }

    public function addExpense()
    {
        $visit = Visit::find($this->expenseFormData['visit_id']);
        $date = optional($visit->dayTourPlan)->date;

        $this->expenseFormData['expenses'][] = [
            'expense_type' => null,
            'expense_details' => [  // Change here
                [
                    'date' => $date,
                    'description' => '',
                    'amount' => 0,
                    'details' => '',
                ],
            ],
        ];
    }

    public function removeExpense($index)
    {
        array_splice($this->expenseFormData['expenses'], $index, 1);
    }

    private function resetExpenseForm()
    {
        $this->expenseFormData = [
            'visit_id' => null,
            'expenses' => [],
        ];
    }

    public function submitExpense()
    {
        // Define validation rules for expenses
        $rules = [];
        $messages = [];

        foreach ($this->expenseFormData['expenses'] as $index => $expense) {
            $rules['expenseFormData.expenses.' . $index . '.expense_type'] = 'required|string';
            $rules['expenseFormData.expenses.' . $index . '.expense_details'] = 'required|array';

            foreach ($expense['expense_details'] as $detailIndex => $detail) {
                $rules['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.date'] = 'required|date';
                $rules['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.description'] = 'required|string|max:255';
                $rules['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.amount'] = 'required|numeric|min:0';
            }

            // Custom messages for expenses
            $messages['expenseFormData.expenses.' . $index . '.expense_type.required'] = 'Expense type is required.';
            $messages['expenseFormData.expenses.' . $index . '.expense_details.required'] = 'Expense details are required.';

            foreach ($expense['expense_details'] as $detailIndex => $detail) {
                $messages['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.date.required'] = 'Date is required for expense detail.';
                $messages['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.description.required'] = 'Description is required for expense detail.';
                $messages['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.amount.required'] = 'Amount is required for expense detail.';
                $messages['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.amount.numeric'] = 'Amount must be a valid number.';
            }
        }

        // Validate the entire expense form data using defined rules and messages
        $this->validate($rules, $messages);

        // Calculate total from expense details
        foreach ($this->expenseFormData['expenses'] as $index => $expense) {
            $total = 0;
            foreach ($expense['expense_details'] as $detail) {
                $total += $detail['amount'];
            }
            // Set the total for the expense
            $this->expenseFormData['expenses'][$index]['total'] = $total;
        }

        // Now you can save each expense to the database
        foreach ($this->expenseFormData['expenses'] as $expense) {
            VisitExpense::create([
                'visit_id' => $this->expenseFormData['visit_id'],
                'expense_type' => $expense['expense_type'],
                'expense_details' => $expense['expense_details'], // Store as JSON
                'total' => $expense['total'],
                'status' => 'pending',
                'line_manager_approval' => false,
                'hod_approval' => false,
                'rejection_reason' => null,
            ]);
        }

        // Display success message and close the modal
        $this->notifyUser('Success', 'Expense claim submitted successfully!');
        $this->resetExpenseForm();
        $this->dispatch('close-modal', 'expense_add_modal');
    }

    public function approveLineManager($visitId)
    {
        $visit = Visit::find($visitId);
        if ($visit) {
            $visit->update(['line_manager_approval' => true, 'status' => 'pending']);
            $this->notifyUser('Approved', 'Visit Approved Successfully');
        }
    }

    public function approveHod($visitId)
    {
        $visit = Visit::find($visitId);
        if ($visit) {
            $visit->update(['hod_approval' => true, 'status' => 'Approved']);
            $this->notifyUser('Approved', 'Visit Approved Successfully');
        }
    }

    public function rejectVisit($visitId)
    {
        $visit = Visit::find($visitId);
        if ($visit) {
            $visit->update(['status' => 'Not Approved', 'rejection_reason' => $this->rejectionReason[$visitId]]);
            $this->dispatch('toast', 'Visit rejected. Reason: ' . $this->rejectionReason[$visitId]);
        }
    }

    public function render()
    {
        return view('livewire.crm.visit.manage-visit');
    }
}
