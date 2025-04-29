<?php

namespace App\Livewire\CRM\Expenses;

use App\Models\VisitExpense;
use Livewire\Component;

class ExpenseDetail extends Component
{

    public VisitExpense $expense;

    public function mount(VisitExpense $expense)
    {
        if ($expense->visit->monthlyVisitReport->salesperson_id != auth()->user()->id) {
            $this->notify('Unauthorized access', 'You are not authorized to view this visit.', 'danger');
            $this->redirectRoute('visits.all');
        }
    }

    public function render()
    {
        return view('livewire.crm.expenses.expense-detail', [
            'visit' => $this->expense,
            'title' => "Expense Details"
        ])->layoutData(['title' => "Expense Details"]);
    }
}
