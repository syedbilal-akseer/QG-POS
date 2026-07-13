<?php

namespace App\Livewire\CRM;

use App\Models\Visit;
use Livewire\Component;
use App\Models\Warehouse;
use Filament\Tables\Table;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;
use App\Models\MonthlyTourPlan;
use App\Models\MonthlyVisitReport;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class ListMonthlyTourPlans extends Component implements HasForms, HasTable
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

    public $warehouseLocations = [];
    public $tourPlan;
    public $dayTourPlans = [];
    public $selectedDayTourPlanId;

    public function mount()
    {
        $this->warehouseLocations = Warehouse::all();
    }

    // Define the table directly
    public function table(Table $table): Table
    {
        return $table
            ->query(MonthlyTourPlan::where('salesperson_id', auth()->user()->salesperson_id))
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
                        $this->viewDetails($record);
                    })
                    ->color('violet'),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->button()
                    ->action(function (MonthlyTourPlan $record) {
                        $this->edit($record);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function viewDetails(MonthlyTourPlan $tourPlan)
    {
        $this->tourPlan = $tourPlan; // Correctly assign the tourPlan property
        $this->dayTourPlans = []; // Clear previous plans

        // Load related day plans and their key tasks
        foreach ($tourPlan->dayTourPlans as $dayPlan) {
            $this->dayTourPlans[] = [
                'id' => $dayPlan->id,
                'date' => $dayPlan->date,
                'day' => $dayPlan->day,
                'from_location' => $dayPlan->fromWarehouse->organization_code,
                'to_location' => $dayPlan->toWarehouse->organization_code,
                'is_night_stay' => $dayPlan->is_night_stay,
                'key_tasks' => is_string($dayPlan->key_tasks)
                    ? json_decode($dayPlan->key_tasks, true) // Decode JSON string to array
                    : (array) $dayPlan->key_tasks, // Ensure it's an array
            ];
        }

        // Open the modal
        $this->dispatch('open-modal', 'day_tour_plan_modal');
    }

    public function addNewVisit($dayTourPlanId)
    {
        $this->selectedDayTourPlanId = $dayTourPlanId;
        $this->resetVisitForm();
        $this->addVisit();
        $this->dispatch('open-modal', 'visit_add_modal');
    }

    public function addVisit()
    {
        $this->visitFormData['visits'][] = [
            'customer_name' => '',
            'area' => '',
            'contact_person' => '',
            'contact_no' => '',
            'outlet_type' => '',
            'shop_category' => '',
            'visit_details' => '',
            'competitors' => [],
        ];
    }

    public function removeVisit($index)
    {
        unset($this->visitFormData['visits'][$index]);
        $this->visitFormData['visits'] = array_values($this->visitFormData['visits']); // Re-index array
    }

    public function addCompetitor($visitIndex)
    {
        $this->visitFormData['visits'][$visitIndex]['competitors'][] = [
            'name' => '',
            'product' => '',
            'size' => '',
            'details' => '',
        ];
    }

    public function removeCompetitor($visitIndex, $competitorIndex)
    {
        unset($this->visitFormData['visits'][$visitIndex]['competitors'][$competitorIndex]);
        $this->visitFormData['visits'][$visitIndex]['competitors'] = array_values($this->visitFormData['visits'][$visitIndex]['competitors']); // Re-index array
    }

    public function submitVisit()
    {
        // Validation rules for visits
        $rules = [
            'visitFormData.visits.*.customer_name' => 'required|string|max:255',
            'visitFormData.visits.*.area' => 'required|string|max:255',
            'visitFormData.visits.*.contact_person' => 'required|string|max:255',
            'visitFormData.visits.*.contact_no' => 'required|string|max:255',
            'visitFormData.visits.*.outlet_type' => 'required|string|max:255',
            'visitFormData.visits.*.shop_category' => 'required|string|max:255',
            'visitFormData.visits.*.visit_details' => 'required|string|max:1000',
            'visitFormData.visits.*.competitors.*.name' => 'nullable|string|max:255',
            'visitFormData.visits.*.competitors.*.product' => 'nullable|string|max:255',
            'visitFormData.visits.*.competitors.*.size' => 'nullable|string|max:255',
            'visitFormData.visits.*.competitors.*.details' => 'nullable|string',
        ];

        // Validation messages for visits
        $messages = [
            'visitFormData.visits.*.customer_name.required' => 'Customer name is required.',
            'visitFormData.visits.*.area.required' => 'Area is required.',
            'visitFormData.visits.*.contact_person.required' => 'Contact person is required.',
            'visitFormData.visits.*.contact_no.required' => 'Contact number is required.',
            'visitFormData.visits.*.contact_no.max' => 'Contact number must not exceed 255 characters.',
            'visitFormData.visits.*.outlet_type.required' => 'Outlet type is required.',
            'visitFormData.visits.*.shop_category.required' => 'Shop category is required.',
            'visitFormData.visits.*.visit_details.required' => 'Visit details are required.',
            'visitFormData.visits.*.competitors.*.name.required' => 'Competitor name is required.',
            'visitFormData.visits.*.competitors.*.product.required' => 'Product name is required.',
            'visitFormData.visits.*.competitors.*.size.max' => 'Size must not exceed 255 characters.',
            'visitFormData.visits.*.competitors.*.details.max' => 'Details must not exceed 255 characters.',
        ];

        // Validate visits
        $this->validate($rules, $messages);

        // Retrieve the DayTourPlan using the selectedDayTourPlanId
        $dayTourPlan = DayTourPlan::find($this->selectedDayTourPlanId);

        // Get the MonthlyTourPlan associated with this DayTourPlan
        $monthlyTourPlan = $dayTourPlan->monthlyTourPlan;

        if (!$monthlyTourPlan) {
            // Handle the case where the MonthlyTourPlan is not found
            $this->notifyUser('Error', 'Monthly Tour Plan not found for the selected Day Tour Plan.', 'danger');
            return;
        }

        // Extract the month from the MonthlyTourPlan
        $salespersonId = auth()->user()->salesperson_id;

        DB::transaction(function () use ($salespersonId, $monthlyTourPlan) {

            // Find or create the MonthlyVisitReport
            $monthlyReport = MonthlyVisitReport::firstOrCreate(
                ['salesperson_id' => $salespersonId, 'month' => $monthlyTourPlan->month],
                ['monthly_tour_plan_id' => $monthlyTourPlan->id]
            );

            // Handle the logic to add the visit for the DayTourPlan
            foreach ($this->visitFormData['visits'] as $visit) {
                // Prepare the competitors data
                $competitors = [];
                if (isset($visit['competitors'])) {
                    foreach ($visit['competitors'] as $competitor) {
                        // Only include competitors with names
                        if (!empty($competitor['name'])) {
                            $competitors[] = [
                                'name' => $competitor['name'],
                                'product' => $competitor['product'],
                                'size' => $competitor['size'],
                                'details' => $competitor['details'],
                            ];
                        }
                    }
                }

                // Create the Visit record
                Visit::create([
                    'day_tour_plan_id' => $this->selectedDayTourPlanId,
                    'customer_name' => $visit['customer_name'],
                    'area' => $visit['area'],
                    'contact_person' => $visit['contact_person'],
                    'contact_no' => $visit['contact_no'],
                    'outlet_type' => $visit['outlet_type'],
                    'shop_category' => $visit['shop_category'],
                    'visit_details' => $visit['visit_details'],
                    'monthly_visit_report_id' => $monthlyReport->id,
                    'competitors' => $competitors, // Store competitors as a JSON array
                ]);
            }
        });

        // Display success message and close the modal
        $this->notifyUser('Success', 'Visits added successfully.');
        $this->resetVisitForm();
        $this->dispatch('close-modal', 'visit_add_modal');
    }


    private function resetVisitForm()
    {
        $this->visitFormData = [
            'day_tour_plan_id' => null,
            'visits' => [],
        ];
    }

    public function addNewPlan()
    {
        $this->tourPlanId = null;
        $this->resetForm();
        $this->formData['salesperson_id'] = auth()->user()->salesperson_id;
        $this->dispatch('open-modal', 'tour_plan_modal');
    }

    public function edit(MonthlyTourPlan $tourPlan)
    {
        // Set the ID of the tour plan being edited
        $this->tourPlanId = $tourPlan->id;

        // Load the basic tour plan data
        $this->formData = [
            'salesperson_id' => $tourPlan->salesperson_id,
            'month' => $tourPlan->month,
            'day_plans' => [], // Initialize day_plans as an empty array
        ];

        // Load related day plans and their key tasks
        foreach ($tourPlan->dayTourPlans as $dayPlan) {
            $this->formData['day_plans'][] = [
                'date' => $dayPlan->date, // Format the date
                'from_location' => $dayPlan->from_location,
                'to_location' => $dayPlan->to_location,
                'is_night_stay' => $dayPlan->is_night_stay,
                'key_tasks' => is_string($dayPlan->key_tasks)
                    ? json_decode($dayPlan->key_tasks, true)
                    : (array) $dayPlan->key_tasks,
            ];
        }

        // Dispatch an event to open the modal
        $this->dispatch('open-modal', 'tour_plan_modal');
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
            'formData.salesperson_id' => 'required|exists:users,salesperson_id',
            'formData.month' => 'required|date_format:F Y',
            'formData.day_plans' => 'required|array',
            'formData.day_plans.*.date' => 'required|date_format:d/m/Y',
            'formData.day_plans.*.from_location' => 'required|string|max:255|different:formData.day_plans.*.to_location',
            'formData.day_plans.*.to_location' => 'required|string|max:255|different:formData.day_plans.*.from_location',
            'formData.day_plans.*.is_night_stay' => 'boolean',
            'formData.day_plans.*.key_tasks' => 'nullable|array',
            'formData.day_plans.*.key_tasks.*' => 'nullable|string',
        ], [
            'formData.salesperson_id.required' => 'The salesperson field is required.',
            'formData.salesperson_id.exists' => 'The selected salesperson is invalid.',
            'formData.month.required' => 'The month field is required.',
            'formData.month.date_format' => 'The month must be in the format Y-m.',
            'formData.day_plans.required' => 'You must provide day plans.',
            'formData.day_plans.array' => 'The day plans must be an array.',
            'formData.day_plans.*.date.required' => 'The date field is required for each day plan.',
            'formData.day_plans.*.date.date_format' => 'The date must be in the format d/m/Y.',
            'formData.day_plans.*.from_location.required' => 'The from location field is required for each day plan.',
            'formData.day_plans.*.from_location.different' => 'The from location must be different from the to location.',
            'formData.day_plans.*.to_location.required' => 'The to location field is required for each day plan.',
            'formData.day_plans.*.to_location.different' => 'The to location must be different from the from location.',
            'formData.day_plans.*.is_night_stay.boolean' => 'The night stay field must be true or false.',
            'formData.day_plans.*.key_tasks.array' => 'The key tasks must be an array.',
            'formData.day_plans.*.key_tasks.*.nullable' => 'Each key task must be a string.',
        ]);


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
                    'date' => \Carbon\Carbon::createFromFormat('d/m/Y', $dayPlanData['date']),
                    'from_location' => $dayPlanData['from_location'],
                    'to_location' => $dayPlanData['to_location'],
                    'is_night_stay' => $dayPlanData['is_night_stay'],
                    'key_tasks' => json_encode($dayPlanData['key_tasks']), // Convert key_tasks to JSON
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
            foreach ($this->formData['day_plans'] as $dayPlan) {
                $dayPlan['date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $dayPlan['date'])->format('Y-m-d');
                $tourPlan->dayTourPlans()->create($dayPlan);
            }
        }

        // Close modal and reset form
        $this->dispatch('close-modal', 'tour_plan_modal');
        $this->resetForm();

        // Notify user of success
        $this->notifyUser('Plan Updated', 'Tour Plan saved successfully.');
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

    /**
     * Get the next available date that is not Saturday or Sunday.
     *
     * @return string
     */
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
        return view('livewire.crm.list-monthly-tour-plans');
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
