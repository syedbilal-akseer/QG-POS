<?php

namespace App\Livewire\CRM\MonthlyPlan;

use App\Models\Visit;
use Livewire\Component;
use App\Models\DayTourPlan;
use App\Models\MonthlyTourPlan;
use App\Models\MonthlyVisitReport;
use App\Traits\NotifiesUsers;
use Illuminate\Support\Facades\DB;

class CreateMvr extends Component
{
    use NotifiesUsers;
    public ?array $visitFormData = [
        'day_tour_plan_id' => null,
        'visits' => [],
    ];

    public $selectedDayTourPlanId;

    public DayTourPlan $dayTourPlan;

    public function mount(?DayTourPlan $dayTourPlan)
    {
        if($dayTourPlan->monthlyTourPlan->salesperson_id != auth()->user()->salesperson_id){
            $this->notify('Unauthorized access', 'You are not authorized to view this Day Tour Plan.', 'danger');
            $this->redirectRoute('monthlyTourPlans.all', navigate: true);
        }

        if ($dayTourPlan && $dayTourPlan->exists) {
            $this->addNewVisit($dayTourPlan->id);
        }
    }

    public function addNewVisit($dayTourPlanId)
    {
        $this->selectedDayTourPlanId = $dayTourPlanId;
        $this->resetVisitForm();
        $this->addVisit();
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
            'visit_findings_of_the_day' => '',
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
            'visitFormData.visits.*.visit_details' => 'required|string',
            'visitFormData.visits.*.visit_findings_of_the_day' => 'required|string',
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
            'visitFormData.visits.*.visit_findings_of_the_day.required' => 'Visit findings of the day is required.',
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
                    'findings_of_the_day' => $visit['visit_findings_of_the_day'],
                    'monthly_visit_report_id' => $monthlyReport->id,
                    'competitors' => $competitors,
                ]);
            }
        });

        // Display success message and close the modal
        $this->notifyUser('Success', 'Visits added successfully.');

        // Redirect to the monthly plan list
        $this->redirectRoute('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $monthlyTourPlan->id], navigate: true);
    }

    private function resetVisitForm()
    {
        $this->visitFormData = [
            'day_tour_plan_id' => null,
            'visits' => [],
        ];
    }

    public function render()
    {
        // Conditionally set the title dynamically
        $title = $this->dayTourPlan->day;
        return view('livewire.crm.monthly-plan.create-mvr', [
            'title' => $title
        ])->layoutData(['title' => $title]);
    }
}
