<?php

namespace App\Livewire\CRM\MonthlyPlan;

use App\Models\Visit;
use Livewire\Component;
use App\Models\VisitExpense;
use App\Traits\NotifiesUsers;

class AddExpense extends Component
{
    use NotifiesUsers;
    public ?array $expenseFormData = [
        'visit_id' => null,
        'expenses' => [],
    ];

    public Visit $visit;

    public function mount(Visit $visit)
    {
        if($visit->monthlyVisitReport->salesperson_id != auth()->user()->salesperson_id){
            $this->notify('Unauthorized access', 'You are not authorized to view this Day Tour Plan.', 'danger');
            $this->redirectRoute('monthlyTourPlans.all', navigate: true);
        }

        if ($visit && $visit->exists) {
            $this->expenseFormData['visit_id'] = $visit->id;
            $this->addExpense();
        }
    }

    public function addExpense()
    {
        $date = optional($this->visit->dayTourPlan)->date;

        $this->expenseFormData['expenses'][] = [
            'expense_type' => null,
            'expense_details' => [
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
                $rules['expenseFormData.expenses.' . $index . '.expense_details.' . $detailIndex . '.date'] = 'required|date_format:d/m/Y';
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
    }

    public function render()
    {
        $title = $this->visit->dayTourPlan->day;
        return view('livewire.crm.monthly-plan.add-expense', [
            'title' => $title
        ])->layoutData(['title' => $title]);
    }
}
