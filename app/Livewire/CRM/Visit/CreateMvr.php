<?php

namespace App\Livewire\CRM\Visit;

use App\Models\Visit;
use Livewire\Component;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;
use Livewire\WithFileUploads;
use App\Models\MonthlyTourPlan;
use App\Models\MonthlyVisitReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateMvr extends Component
{
    use NotifiesUsers, WithFileUploads;
    public ?array $visitFormData = [
        'day_tour_plan_id' => null,
        'visits' => [],
    ];

    public $selectedDayTourPlanId;

    public DayTourPlan $dayTourPlan;

    public bool $isEditing = false;

    // File upload property
    public $visitAttachments = [];
    public $competitorAttachments = [];

    public function mount(?DayTourPlan $dayTourPlan, $visitId = null)
    {
        if ($dayTourPlan->monthlyTourPlan->salesperson_id != auth()->user()->id) {
            $this->notify('Unauthorized access', 'You are not authorized to view this Day Tour Plan.', 'danger');
            $this->redirectRoute('monthlyTourPlans.all');
        }

        $this->dayTourPlan = $dayTourPlan;
        $this->selectedDayTourPlanId = $dayTourPlan->id;

        if ($dayTourPlan && $dayTourPlan->exists) {
            if ($visitId) {
                $this->isEditing = true;
                $visit = Visit::find($visitId);
                // Preload the visit data for editing
                $this->visitFormData['visits'] = [
                    [
                        'id' => $visit->id,
                        'customer_name' => $visit->customer_name,
                        'area' => $visit->area,
                        'contact_person' => $visit->contact_person,
                        'contact_no' => $visit->contact_no,
                        'outlet_type' => $visit->outlet_type,
                        'shop_category' => $visit->shop_category,
                        'visit_details' => $visit->visit_details,
                        'visit_findings_of_the_day' => $visit->findings_of_the_day,
                        'competitors' => $visit->competitors,
                        'attachments' => $visit->attachments,
                    ]
                ];
            } else {
                // If no visit ID, add a new visit
                $this->addNewVisit();
            }
        }
    }

    public function addNewVisit()
    {
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
            'attachments' => [],
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
            'attachments' => [],
        ];
    }

    public function removeCompetitor($visitIndex, $competitorIndex)
    {
        unset($this->visitFormData['visits'][$visitIndex]['competitors'][$competitorIndex]);
        $this->visitFormData['visits'][$visitIndex]['competitors'] = array_values($this->visitFormData['visits'][$visitIndex]['competitors']); // Re-index array
    }

    // public function submitVisit()
    // {
    //     // Validation rules for visits
    //     $rules = [
    //         'visitFormData.visits.*.customer_name' => 'required|string|max:255',
    //         'visitFormData.visits.*.area' => 'required|string|max:255',
    //         'visitFormData.visits.*.contact_person' => 'required|string|max:255',
    //         'visitFormData.visits.*.contact_no' => 'required|string|max:255',
    //         'visitFormData.visits.*.outlet_type' => 'required|string|max:255',
    //         'visitFormData.visits.*.shop_category' => 'required|string|max:255',
    //         'visitFormData.visits.*.visit_details' => 'required|string',
    //         'visitFormData.visits.*.visit_findings_of_the_day' => 'required|string',
    //         'visitFormData.visits.*.competitors.*.name' => 'nullable|string|max:255',
    //         'visitFormData.visits.*.competitors.*.product' => 'nullable|string|max:255',
    //         'visitFormData.visits.*.competitors.*.size' => 'nullable|string|max:255',
    //         'visitFormData.visits.*.competitors.*.details' => 'nullable|string',
    //         'visitAttachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',  // Max 10MB
    //         'competitorAttachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240', // Max 10MB
    //     ];

    //     // Validation messages for visits
    //     $messages = [
    //         'visitFormData.visits.*.customer_name.required' => 'Customer name is required.',
    //         'visitFormData.visits.*.area.required' => 'Area is required.',
    //         'visitFormData.visits.*.contact_person.required' => 'Contact person is required.',
    //         'visitFormData.visits.*.contact_no.required' => 'Contact number is required.',
    //         'visitFormData.visits.*.contact_no.max' => 'Contact number must not exceed 255 characters.',
    //         'visitFormData.visits.*.outlet_type.required' => 'Outlet type is required.',
    //         'visitFormData.visits.*.shop_category.required' => 'Shop category is required.',
    //         'visitFormData.visits.*.visit_details.required' => 'Visit details are required.',
    //         'visitFormData.visits.*.visit_findings_of_the_day.required' => 'Visit findings of the day is required.',
    //         'visitFormData.visits.*.competitors.*.name.required' => 'Competitor name is required.',
    //         'visitFormData.visits.*.competitors.*.product.required' => 'Product name is required.',
    //         'visitFormData.visits.*.competitors.*.size.max' => 'Size must not exceed 255 characters.',
    //         'visitFormData.visits.*.competitors.*.details.max' => 'Details must not exceed 255 characters.',
    //         'visitAttachments.*.*.mimes' => 'Invalid file type. Only JPG, JPEG, PNG, GIF, BMP, PDF, DOC, DOCX files are allowed.',
    //         'visitAttachments.*.*.max' => 'File size must not exceed 10MB.',
    //         'competitorAttachments.*.*.mimes' => 'Invalid file type. Only JPG, JPEG, PNG, GIF, BMP, PDF, DOC, DOCX files are allowed.',
    //         'competitorAttachments.*.*.max' => 'File size must not exceed 10MB.',
    //     ];

    //     // Validate visits
    //     $this->validate($rules, $messages);

    //     // Retrieve the DayTourPlan using the selectedDayTourPlanId
    //     $dayTourPlan = DayTourPlan::find($this->selectedDayTourPlanId);

    //     // Get the MonthlyTourPlan associated with this DayTourPlan
    //     $monthlyTourPlan = $dayTourPlan->monthlyTourPlan;

    //     if (!$monthlyTourPlan) {
    //         // Handle the case where the MonthlyTourPlan is not found
    //         $this->notifyUser('Error', 'Monthly Tour Plan not found for the selected Day Tour Plan.', 'danger');
    //         return;
    //     }

    //     // Extract the month from the MonthlyTourPlan
    //     $salespersonId = auth()->user()->id;

    //     DB::transaction(function () use ($salespersonId, $monthlyTourPlan) {

    //         // Find or create the MonthlyVisitReport
    //         $monthlyReport = MonthlyVisitReport::firstOrCreate(
    //             ['salesperson_id' => $salespersonId, 'month' => $monthlyTourPlan->month],
    //             ['monthly_tour_plan_id' => $monthlyTourPlan->id]
    //         );

    //         // Handle the logic to add the visit for the DayTourPlan
    //         foreach ($this->visitFormData['visits'] as $visitIndex => $visit) {

    //             $visitAttachments = isset($this->visitAttachments[$visitIndex])
    //                 ? $this->visitAttachments[$visitIndex]
    //                 : [];

    //             // Handle Visit Attachments
    //             $visitAttachmentPaths = [];
    //             foreach ($visitAttachments as $file) {
    //                 $visitAttachmentPaths[] = $file->store('visit-attachments', 'public');
    //             }

    //             // Prepare the competitors data
    //             $competitors = [];
    //             if (isset($visit['competitors'])) {
    //                 foreach ($visit['competitors'] as $competitorIndex => $competitor) {

    //                     $attachments = isset($this->competitorAttachments[$competitorIndex])
    //                         ? $this->competitorAttachments[$competitorIndex]
    //                         : [];
    //                     $attachmentPaths = [];
    //                     foreach ($attachments as $file) {
    //                         $attachmentPaths[] = $file->store('competitor-attachments', 'public');
    //                     }

    //                     // Only include competitors with names
    //                     if (!empty($competitor['name'])) {
    //                         $competitors[] = [
    //                             'name' => $competitor['name'],
    //                             'product' => $competitor['product'],
    //                             'size' => $competitor['size'],
    //                             'details' => $competitor['details'],
    //                             'attachments' => $attachmentPaths,
    //                         ];
    //                     }
    //                 }
    //             }

    //             // Create the Visit record
    //             Visit::create([
    //                 'day_tour_plan_id' => $this->selectedDayTourPlanId,
    //                 'customer_name' => $visit['customer_name'],
    //                 'area' => $visit['area'],
    //                 'contact_person' => $visit['contact_person'],
    //                 'contact_no' => $visit['contact_no'],
    //                 'outlet_type' => $visit['outlet_type'],
    //                 'shop_category' => $visit['shop_category'],
    //                 'visit_details' => $visit['visit_details'],
    //                 'findings_of_the_day' => $visit['visit_findings_of_the_day'],
    //                 'monthly_visit_report_id' => $monthlyReport->id,
    //                 'competitors' => $competitors,
    //                 'attachments' => $visitAttachmentPaths,
    //             ]);
    //         }
    //     });

    //     // Display success message and close the modal
    //     $this->notifyUser('Success', 'Visits added successfully.');

    //     // Redirect to the monthly plan list
    //     $this->redirectRoute('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $monthlyTourPlan->id]);
    // }

    public function submitVisit()
    {
        $rules = [
            'visitFormData.visits.*.customer_name' => 'required|string|max:255',
            'visitFormData.visits.*.area' => 'required|string|max:255',
            'visitFormData.visits.*.contact_person' => 'required|string|max:255',
            'visitFormData.visits.*.contact_no' => 'required|string|max:255',
            'visitFormData.visits.*.outlet_type' => 'required|string|max:255',
            'visitFormData.visits.*.shop_category' => 'required|string|max:255',
            'visitFormData.visits.*.visit_details' => 'required|string',
            'visitFormData.visits.*.visit_findings_of_the_day' => 'required|string',
            'visitAttachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',
            'competitorAttachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',
        ];
    
        $this->validate($rules);
    
        $monthlyTourPlan = $this->dayTourPlan->monthlyTourPlan;
    
        if (!$monthlyTourPlan) {
            $this->notifyUser('Error', 'Monthly Tour Plan not found for the selected Day Tour Plan.', 'danger');
            return;
        }
    
        $salespersonId = auth()->user()->id;
    
        DB::transaction(function () use ($salespersonId, $monthlyTourPlan) {
    
            $monthlyReport = MonthlyVisitReport::firstOrCreate(
                ['salesperson_id' => $salespersonId, 'month' => $monthlyTourPlan->month],
                ['monthly_tour_plan_id' => $monthlyTourPlan->id]
            );
    
            foreach ($this->visitFormData['visits'] as $visitIndex => $visit) {
                
                // --- Handle Visit Attachments (Replace if new uploaded) ---
                $visitAttachmentPaths = [];
                if (isset($this->visitAttachments[$visitIndex])) {
                    // Delete old attachments
                    if (isset($visit['attachments'])) {
                        foreach ($visit['attachments'] as $oldAttachment) {
                            Storage::disk('public')->delete($oldAttachment);
                        }
                    }
                    // Store new attachments
                    foreach ($this->visitAttachments[$visitIndex] as $file) {
                        if ($file->isValid()) {
                            $path = $file->store('visit-attachments', 'public');
                            Log::info('Visit Attachment Uploaded: ' . $path);
                            $visitAttachmentPaths[] = $path;
                        }
                    }
                } else {
                    // Retain old attachments if no new ones provided
                    $visitAttachmentPaths = $visit['attachments'] ?? [];
                }
    
                // --- Handle Competitor Attachments (Replace if new uploaded) ---
                $competitors = [];
                foreach ($visit['competitors'] as $competitorIndex => $competitor) {
                    $competitorAttachments = [];
                    if (isset($this->competitorAttachments[$visitIndex][$competitorIndex])) {
                        // Delete old attachments
                        if (isset($competitor['attachments'])) {
                            foreach ($competitor['attachments'] as $oldAttachment) {
                                Storage::disk('public')->delete($oldAttachment);
                            }
                        }
                        // Store new attachments
                        foreach ($this->competitorAttachments[$visitIndex][$competitorIndex] as $file) {
                            if ($file->isValid()) {
                                $path = $file->store('competitor-attachments', 'public');
                                Log::info('Competitor Attachment Uploaded: ' . $path);
                                $competitorAttachments[] = $path;
                            }
                        }
                    } else {
                        // Retain old attachments if no new ones provided
                        $competitorAttachments = $competitor['attachments'] ?? [];
                    }
    
                    $competitors[] = [
                        'name' => $competitor['name'],
                        'product' => $competitor['product'],
                        'size' => $competitor['size'],
                        'details' => $competitor['details'],
                        'attachments' => $competitorAttachments,
                    ];
                }
    
                // --- Create or Update Visit ---
                $visitRecord = Visit::updateOrCreate(
                    ['id' => $visit['id'] ?? null],
                    [
                        'monthly_visit_report_id' => $monthlyReport->id,
                        'day_tour_plan_id' => $this->dayTourPlan->id,
                        'customer_name' => $visit['customer_name'],
                        'area' => $visit['area'],
                        'contact_person' => $visit['contact_person'],
                        'contact_no' => $visit['contact_no'],
                        'outlet_type' => $visit['outlet_type'],
                        'shop_category' => $visit['shop_category'],
                        'visit_details' => $visit['visit_details'],
                        'findings_of_the_day' => $visit['visit_findings_of_the_day'],
                        'competitors' => $competitors,
                        'attachments' => $visitAttachmentPaths,
                    ]
                );
    
                Log::info('Final Visit Record:', ['attachments' => $visitRecord->attachments]);
            }
        });
    
        $this->notifyUser('Success', 'Visit data saved successfully.');
    
        ($this->isEditing)
            ? $this->redirectRoute('visit.reportDetails', ['plan' => $this->dayTourPlan])
            : $this->redirectRoute('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $monthlyTourPlan->id]);
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
        $outletTypes = [
            ['value' => 'Shoe', 'label' => 'Shoe'],
            ['value' => 'Adhesive', 'label' => 'Adhesive'],
            ['value' => 'HBM', 'label' => 'HBM'],
        ];
        $shopCategories = [
            ['value' => 'Dealer', 'label' => 'Dealer'],
            ['value' => 'Retailer', 'label' => 'Retailer'],
            ['value' => 'Wholesaler', 'label' => 'Wholesaler'],
            ['value' => 'Manufacturer', 'label' => 'Manufacturer'],
        ];
        return view('livewire.crm.visit.create-mvr', [
            'title' => $title,
            'outletTypes' => $outletTypes,
            'shopCategories' => $shopCategories,
        ])->layoutData(['title' => $title]);
    }
}
