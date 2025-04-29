<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\Contracts\View\View as ViewContract;

class GuestLayout extends Component
{
    /**
     * Get the view / contents that represent the component.
     */
    public function render(): ViewContract
    {
        return view('layouts.guest');
    }
}
