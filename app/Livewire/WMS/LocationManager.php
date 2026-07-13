<?php

namespace App\Livewire\WMS;

use Livewire\Component;

class LocationManager extends Component
{
    public $locations = [];
    public $type = 'zone';
    public $location_code = '';
    public $description = '';
    public $parent_id = null;

    public function mount()
    {
        $this->loadLocations();
    }

    public function loadLocations()
    {
        $this->locations = \App\Models\WMS\Location::all();
    }

    public function saveLocation()
    {
        $this->validate([
            'location_code' => 'required|unique:wms_locations,location_code',
            'type' => 'required'
        ]);

        \App\Models\WMS\Location::create([
            'location_code' => $this->location_code,
            'type' => $this->type,
            'description' => $this->description,
            'parent_id' => ($this->type !== 'zone') ? $this->parent_id : null,
            'qr_code' => $this->location_code
        ]);

        $this->reset(['location_code', 'description']);
        $this->loadLocations();
        session()->flash('message', 'Location successfully mapped & QR generated.');
    }

    public function deleteLocation($id)
    {
        \App\Models\WMS\Location::find($id)?->delete();
        $this->loadLocations();
    }

    public function render()
    {
        return view('livewire.w-m-s.location-manager');
    }
}
