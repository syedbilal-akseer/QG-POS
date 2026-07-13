<?php

namespace App\Livewire\CRM\MonthlyPlan;

use App\Models\Visit;
use Livewire\Component;

class VisitDetails extends Component
{
    public Visit $visit;

    public function mount(Visit $visit)
    {
        if ($visit->monthlyVisitReport->salesperson_id != auth()->user()->salesperson_id) {
            $this->notify('Unauthorized access', 'You are not authorized to view this visit.', 'danger');
            $this->redirectRoute('monthlyTourPlans.all', navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.crm.monthly-plan.visit-details', [
            'visit' => $this->visit,
            'title' => "Visit Details"
        ])->layoutData(['title' => "Visit Details"]);
    }
}

