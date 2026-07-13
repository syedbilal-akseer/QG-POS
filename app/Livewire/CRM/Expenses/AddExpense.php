<?php

namespace App\Livewire\CRM\Expenses;

use App\Models\Visit;
use Livewire\Component;
use App\Models\VisitExpense;
use App\Traits\NotifiesUsers;
use Livewire\WithFileUploads;

class AddExpense extends Component
{
    use NotifiesUsers, WithFileUploads;
    public ?array $expenseFormData = [
        'visit_id' => null,
        'expenses' => [],
    ];

    public Visit $visit;

    public $expenseAttachments = [];

    public function mount(Visit $visit, $expenseId = null)
    {
        if ($visit->monthlyVisitReport->salesperson_id != auth()->user()->id) {
            $this->notify('Unauthorized access', 'You are not authorized to view this Day Tour Plan.', 'danger');
            $this->redirectRoute('visits.all');
        }

        if ($visit && $visit->exists) {
            $this->expenseFormData['visit_id'] = $visit->id;

            if ($expenseId) {
                $expense = VisitExpense::find($expenseId);
                // Preload the expense data for editing
                $this->expenseFormData['expenses'] =
                [
                    [
                        'id' => $expense->id,
                        'expense_type' => $expense->expense_type,
                        'expense_details' => $expense->expense_details,
                    ],
                ];
            } else {
                // If no expense ID, add a new expense
                $this->addExpense();
            }
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
            'total' => 0,
            'attachments' => [],
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
        $rules = [
            'expenseAttachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',  // Max 10MB
        ];
        $messages = [
            'expenseAttachments.*.*.mimes' => 'Only JPG, JPEG, PNG, GIF, BMP, PDF, DOC, DOCX files are allowed.',
            'expenseAttachments.*.*.max' => 'File size must be less than 10MB.',
        ];

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
        foreach ($this->expenseFormData['expenses'] as $index => $expense) {
            $expenseAttachments = isset($this->expenseAttachments[$index])
                ? $this->expenseAttachments[$index]
                : [];
            // Handle Expense Attachments
            $expenseAttachmentPaths = [];
            foreach ($expenseAttachments as $file) {
                $expenseAttachmentPaths[] = $file->store('expense-attachments', 'public');
            }

            // Update or create the expense
            if (isset($expense['id'])) {
                $expenseToUpdate = VisitExpense::find($expense['id']);

                // Preserve existing attachments if no new ones are uploaded
                $existingAttachments = $expenseToUpdate->attachments ?? [];
                $newAttachments = isset($expenseAttachments) ? $expenseAttachments : [];
                $uploadedAttachments = [];

                // Handle new attachments if provided
                foreach ($newAttachments as $file) {
                    $uploadedAttachments[] = $file->store('expense-attachments', 'public');
                }

                // Merge existing attachments with the new ones
                $allAttachments = array_merge($existingAttachments, $uploadedAttachments);

                // Update the expense record
                $expenseToUpdate->update([
                    'expense_type' => $expense['expense_type'],
                    'expense_details' => $expense['expense_details'],
                    'total' => $expense['total'],
                    'attachments' => $allAttachments, // Save merged attachments
                ]);
            } else {
                // Create a new expense
                $uploadedAttachments = [];
                foreach ($expenseAttachments as $file) {
                    $uploadedAttachments[] = $file->store('expense-attachments', 'public');
                }

                VisitExpense::create([
                    'visit_id' => $this->expenseFormData['visit_id'],
                    'expense_type' => $expense['expense_type'],
                    'expense_details' => $expense['expense_details'],
                    'total' => $expense['total'],
                    'status' => 'pending',
                    'attachments' => $uploadedAttachments,
                ]);
            }
        }

        // Display success message and close the modal
        $this->notifyUser('Success', 'Expense claim submitted successfully!');

        // Redirect to the Visit Report page
        $this->redirectRoute('visit.viewExpenses', ['visit' => $this->visit]);
    }

    public function render()
    {
        $title = $this->visit->dayTourPlan->day;
        $expenseTypes = [
            ['value' => 'business_meal', 'label' => 'Business Meal'],
            ['value' => 'fuel', 'label' => 'Fuel'],
            ['value' => 'tools', 'label' => 'Tools'],
            ['value' => 'travel', 'label' => 'Travel'],
            ['value' => 'license_fee', 'label' => 'License Fee'],
            ['value' => 'mobile_cards', 'label' => 'Mobile Cards'],
            ['value' => 'courier', 'label' => 'Courier'],
            ['value' => 'stationery', 'label' => 'Stationery'],
            ['value' => 'legal_fees', 'label' => 'Legal Fees'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return view('livewire.crm.expenses.add-expense', [
            'title' => $title,
            'expenseTypes' => $expenseTypes,
        ])->layoutData(['title' => $title]);
    }
}
