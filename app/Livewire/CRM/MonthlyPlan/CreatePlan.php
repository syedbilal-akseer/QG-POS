<?php

namespace App\Livewire\CRM\MonthlyPlan;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;
use App\Models\MonthlyTourPlan;
use Illuminate\Validation\Rule;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;


class CreatePlan extends Component implements HasForms
{
    use InteractsWithForms;
    use NotifiesUsers;


    public $tourPlanId = null;
    public ?array $formData = [
        'salesperson_id' => null,
        'month' => '',  // Format: '2024-10'
        'day_plans' =>   [],  // Array of Day Plans
    ];

    public function mount(?MonthlyTourPlan $monthlyTourPlan = null)
    {
        if ($monthlyTourPlan && $monthlyTourPlan->exists && $monthlyTourPlan->salesperson_id != auth()->id()) {
            $this->notify('Unauthorized access', 'You are not authorized to access this tour plan.', 'danger');
            $this->redirectRoute('monthlyTourPlans.all');
        }

        if ($monthlyTourPlan && $monthlyTourPlan->exists) {
            // Set the ID of the tour plan being edited
            $this->tourPlanId = $monthlyTourPlan->id;

            // Load the basic tour plan data
            $this->formData = [
                'salesperson_id' => $monthlyTourPlan->salesperson_id,
                'month' => $monthlyTourPlan->month,
                'day_plans' => [], // Initialize day_plans as an empty array
            ];

            // Load related day plans and their key tasks
            foreach ($monthlyTourPlan->dayTourPlans as $dayPlan) {
                $this->formData['day_plans'][] = [
                    'date' => $dayPlan->date instanceof \Carbon\Carbon 
                        ? $dayPlan->date->format('d/m/Y') 
                        : (is_string($dayPlan->date) ? \Carbon\Carbon::parse($dayPlan->date)->format('d/m/Y') : $dayPlan->date),
                    'from_location' => $dayPlan->from_location,
                    'to_location' => $dayPlan->to_location,
                    'is_night_stay' => $dayPlan->is_night_stay,
                    'key_tasks' => is_string($dayPlan->key_tasks)
                        ? json_decode($dayPlan->key_tasks, true)
                        : (array) $dayPlan->key_tasks,
                ];
            }
        } else {
            // Initialize for new plan
            $this->tourPlanId = null;
            $this->formData['salesperson_id'] = auth()->id();
            $this->formData['month'] = now()->format('F Y');
            $this->formData['day_plans'] = [];
            $this->addDayPlan();
        }
    }

    public function addNewPlan()
    {
        $this->tourPlanId = null;
        $this->resetForm();
        $this->formData['salesperson_id'] = auth()->id();
    }

    public function addDayPlan()
    {
        $this->formData['day_plans'][] = [
            'date' => $this->getNextAvailableDate(),
            'from_location' => '',
            'to_location' => '',
            'is_night_stay' => false,
            'key_tasks' => [],
        ];
    }

    public function removeDayPlan($index)
    {
        unset($this->formData['day_plans'][$index]);
        $this->formData['day_plans'] = array_values($this->formData['day_plans']); // Re-index the array
    }

    public function addTask($dayIndex)
    {
        $this->formData['day_plans'][$dayIndex]['key_tasks'][] = '';
    }

    public function removeTask($dayIndex, $taskIndex)
    {
        unset($this->formData['day_plans'][$dayIndex]['key_tasks'][$taskIndex]);
        $this->formData['day_plans'][$dayIndex]['key_tasks'] = array_values($this->formData['day_plans'][$dayIndex]['key_tasks']); // Re-index the array
    }

    public function save()
    {
        // Validate form data with custom messages
        $this->validate([
            // 'formData.salesperson_id' => 'required|exists:users,salesperson_id',
            'formData.month' => [
                'required',
                'date_format:F Y',
                Rule::unique('monthly_tour_plans', 'month')
                    ->where(function ($query) {
                        return $query->where('salesperson_id', $this->formData['salesperson_id']);
                    })
                    ->ignore($this->tourPlanId),
            ],
            'formData.day_plans' => 'required|array',
            'formData.day_plans.*.date' => 'required|date_format:d/m/Y',
            'formData.day_plans.*.from_location' => 'required|string|max:255',
            'formData.day_plans.*.to_location' => 'required|string|max:255',
            'formData.day_plans.*.is_night_stay' => 'boolean',
            'formData.day_plans.*.key_tasks' => 'nullable|array',
            'formData.day_plans.*.key_tasks.*' => 'nullable|string',
        ], [
            'formData.salesperson_id.required' => 'The salesperson field is required.',
            'formData.salesperson_id.exists' => 'The selected salesperson is invalid.',
            'formData.month.required' => 'The month field is required.',
            'formData.month.date_format' => 'The month must be in the format "F Y" (e.g., "January 2024").',
            'formData.month.unique' => 'A tour plan for this month already exists.',
            'formData.day_plans.required' => 'The day plans field is required.',
            'formData.day_plans.*.date.required' => 'The date field is required.',
            'formData.day_plans.*.date.date_format' => 'The date must be in the format "d/m/Y" (e.g., "31/12/2024").',
            'formData.day_plans.*.from_location.required' => 'The from location field is required.',
            'formData.day_plans.*.to_location.required' => 'The to location field is required.',
            'formData.day_plans.*.is_night_stay.boolean' => 'The is night stay field must be a boolean.',
            'formData.day_plans.*.key_tasks.array' => 'The key tasks field must be an array.',
        ]);

        // Custom validation for from_location != to_location
        foreach ($this->formData['day_plans'] as $index => $dayPlan) {
            if (isset($dayPlan['from_location']) && isset($dayPlan['to_location']) && 
                $dayPlan['from_location'] === $dayPlan['to_location']) {
                $this->addError("formData.day_plans.{$index}.to_location", 'The to location must be different from the from location.');
                return;
            }
        }

        // Convert the month field into the correct format
        // $this->formData['month'] = \Carbon\Carbon::createFromFormat('Y-m', $this->formData['month'])->format('Y-m');
        if ($this->tourPlanId) {
            // Update existing monthly plan
            $tourPlan = MonthlyTourPlan::findOrFail($this->tourPlanId);
            $tourPlan->update([
                'salesperson_id' => $this->formData['salesperson_id'],
                'month' => $this->formData['month'],
            ]);

            // Clear existing day plans if you want to replace them
            $tourPlan->dayTourPlans()->delete();

            // Save the new day plans with key tasks
            foreach ($this->formData['day_plans'] as $dayPlanData) {
                $dayTourPlan = new DayTourPlan([
                    'date' => Carbon::createFromFormat('d/m/Y', $dayPlanData['date']),
                    'from_location' => $dayPlanData['from_location'],
                    'to_location' => $dayPlanData['to_location'],
                    'is_night_stay' => $dayPlanData['is_night_stay'],
                    'key_tasks' => $dayPlanData['key_tasks'],
                ]);

                // Associate the day plan with the tour plan
                $tourPlan->dayTourPlans()->save($dayTourPlan);
            }
        } else {
            // Create new monthly plan
            $tourPlan = MonthlyTourPlan::create([
                'salesperson_id' => $this->formData['salesperson_id'],
                'month' => $this->formData['month'],
            ]);

            // Create day plans
            foreach ($this->formData['day_plans'] as $dayPlanData) {
                $dayTourPlan = new DayTourPlan([
                    'date' => Carbon::createFromFormat('d/m/Y', $dayPlanData['date']),
                    'from_location' => $dayPlanData['from_location'],
                    'to_location' => $dayPlanData['to_location'],
                    'is_night_stay' => $dayPlanData['is_night_stay'],
                    'key_tasks' => $dayPlanData['key_tasks'],
                ]);

                // Associate the day plan with the tour plan
                $tourPlan->dayTourPlans()->save($dayTourPlan);
            }
        }

        // Close modal and reset form
        // $this->resetForm();

        // Notify user of success
        $this->notifyUser('Plan Updated', 'Tour Plan saved successfully.');

        // Redirect to the monthly plan list
        $this->redirectRoute('monthlyTourPlans.all');
    }

    public function resetForm()
    {
        $this->formData = [
            'salesperson_id' => auth()->user()->id,
            'month' => now()->format('F Y'),
            'day_plans' => [
                [
                    'date' => $this->getNextAvailableDate(),
                    'from_location' => '',
                    'to_location' => '',
                    'is_night_stay' => false,
                    'key_tasks' => [],
                ],
            ],
        ];
    }

    protected function getNextAvailableDate(): string
    {
        $currentDate = now(); // Start from the current date

        // Loop until we find a date that is not a Saturday or Sunday
        while ($currentDate->isWeekend()) { // Check if the current date is a weekend (Saturday or Sunday)
            $currentDate->addDay(); // Move to the next day
        }

        // Return the available date in the desired format (d/m/Y)
        return $currentDate->format('d/m/Y');
    }

    public function render()
    {
        // Conditionally set the title dynamically
        $title = $this->tourPlanId ? 'Edit Tour Plan' : 'Add New Plan';
        // Pass the title to the view if needed
        return view('livewire.crm.monthly-plan.create-plan', [
            'title' => $title
        ])->layoutData(['title' => $title]);
    }
}
