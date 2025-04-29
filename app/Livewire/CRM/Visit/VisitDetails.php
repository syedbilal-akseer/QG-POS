<?php

namespace App\Livewire\CRM\Visit;

use App\Models\Visit;
use Livewire\Component;

class VisitDetails extends Component
{
    public Visit $visit;

    public function mount(Visit $visit)
    {
        if ($visit->monthlyVisitReport->salesperson_id != auth()->user()->id) {
            $this->notify('Unauthorized access', 'You are not authorized to view this visit.', 'danger');
            $this->redirectRoute('visits.all');
        }
    }

    public function render()
    {
        return view('livewire.crm.visit.visit-details', [
            'visit' => $this->visit,
            'title' => "Visit Details"
        ])->layoutData(['title' => "Visit Details"]);
    }
}

