<?php

namespace App\Livewire\CRM\MonthlyPlan;

use Livewire\Component;
use App\Models\DayTourPlan;
use App\Traits\NotifiesUsers;

class DayTourDetails extends Component
{
    use NotifiesUsers;
    public DayTourPlan $dayTourPlan;

    public function mount(DayTourPlan $dayTourPlan)
    {
        $user = auth()->user();

        // Check if the user is the creator of the plan (salesperson)
        if ($dayTourPlan->monthlyTourPlan->salesperson_id == $user->id) {
            $this->dayTourPlan = $dayTourPlan;
            return;
        }

        // Check if the user is the Line Manager of the salesperson
        if ($user->isManager() && $dayTourPlan->monthlyTourPlan->getLineManager()?->id == $user->id) {
            $this->dayTourPlan = $dayTourPlan;
            return;
        }

        // Check if the user is the HOD of the salesperson's department
        if ($user->isHOD() || $user->isAdmin() && $dayTourPlan->monthlyTourPlan->getHod()?->id == $user->id) {
            $this->dayTourPlan = $dayTourPlan;
            return;
        }

        // Deny access if the user doesn't match any of the conditions
        $this->notify('Unauthorized access', 'You are not authorized to view this Day Tour Plan.', 'danger');
        $this->redirectRoute('monthlyTourPlans.all');
    }


    public function addExpense($visit)
    {
        $this->redirectRoute('visit.addExpense', ['visit' => $visit]);
    }

    public function render()
    {
        return view('livewire.crm.monthly-plan.day-tour-details', [
            'dayTourPlan' => $this->dayTourPlan,
            'title' => "Day Tour Plan Details"
        ])->layoutData(['title' => "Day Tour Plan Details"]);
    }
}
