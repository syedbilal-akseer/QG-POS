<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerFormEvent;
use Illuminate\Http\Request;

class CustomerFormEventController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerFormEvent::query()->withCount('forms');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $events = $query->orderByDesc('start_date')->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.customer-form-events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.customer-form-events.form', ['event' => new CustomerFormEvent()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAndPrep($request);
        $event = CustomerFormEvent::create($data + ['created_by' => auth()->id()]);

        return redirect()->route('admin.customer-form-events.index')
            ->with('success', "Event '{$event->name}' created.");
    }

    public function edit(CustomerFormEvent $customerFormEvent)
    {
        return view('admin.customer-form-events.form', ['event' => $customerFormEvent]);
    }

    public function update(Request $request, CustomerFormEvent $customerFormEvent)
    {
        $customerFormEvent->update($this->validateAndPrep($request));

        return redirect()->route('admin.customer-form-events.index')
            ->with('success', "Event '{$customerFormEvent->name}' updated.");
    }

    public function destroy(CustomerFormEvent $customerFormEvent)
    {
        $customerFormEvent->delete();
        return redirect()->route('admin.customer-form-events.index')
            ->with('success', 'Event deleted.');
    }

    private function validateAndPrep(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'is_active'   => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
