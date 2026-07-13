<?php

namespace App\Livewire\WMS;

use Livewire\Component;

use App\Models\WMS\Transaction;
use App\Models\WMS\Lpn;

class TraceabilityReport extends Component
{
    public $search = '';
    public $results = [];

    public function searchTrace()
    {
        if (empty($this->search)) return;

        $this->results = Transaction::with(['lpn', 'fromLocation', 'toLocation', 'user'])
            ->where('item_code', 'like', '%' . $this->search . '%')
            ->orWhereHas('lpn', function($q) {
                $q->where('lpn_number', 'like', '%' . $this->search . '%')
                  ->orWhere('system_sub_lot', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.w-m-s.traceability-report');
    }
}
