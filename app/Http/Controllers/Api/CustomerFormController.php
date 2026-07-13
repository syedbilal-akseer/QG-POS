<?php

namespace App\Http\Controllers\Api;

use App\Exports\CustomerFormsExport;
use App\Http\Controllers\Controller;
use App\Models\CustomerForm;
use App\Models\CustomerFormEvent;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class CustomerFormController extends Controller
{
    /**
     * List all forms for the authenticated user (scoped by form_type if provided).
     *
     * GET/POST /api/customer-forms
     * Params: form_type (optional, 'HBM'|'sales'), page (optional)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'form_type' => ['nullable', Rule::in(['HBM', 'sales'])],
        ]);

        $user  = Auth::user();
        $query = CustomerForm::query()->with('user:id,name');

        // Admins see all; salesperson (role=user) sees only their own
        if ($user->role === 'user') {
            $query->forSalesperson($user->id);
        }

        if ($request->filled('form_type')) {
            $query->ofType($request->form_type);
        }

        $forms = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Customer forms retrieved successfully.',
            'data'    => $forms->items(),
            'pagination' => [
                'total'        => $forms->total(),
                'per_page'     => $forms->perPage(),
                'current_page' => $forms->currentPage(),
                'total_pages'  => $forms->lastPage(),
                'next_page_url'   => $forms->nextPageUrl(),
                'prev_page_url'   => $forms->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Create a new customer form entry.
     *
     * POST /api/customer-forms/store
     * Body: all form fields + form_type ('HBM'|'sales')
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id'                => 'nullable|exists:customer_form_events,id',
            'form_type'               => ['required', Rule::in(['HBM', 'sales'])],
            'name'                    => 'required|string|max:255',
            'designation'             => 'nullable|string|max:255',
            'company_name'            => 'nullable|string|max:255',
            'address'                 => 'nullable|string|max:500',
            'city'                    => 'nullable|string|max:100',
            'mobile'                  => 'nullable|string|max:30',
            'phone'                   => 'nullable|string|max:30',
            'email'                   => 'nullable|email|max:255',
            'skype'                   => 'nullable|string|max:100',
            'is_shoe_material_dealer' => 'nullable|boolean',
            'is_shoe_manufacturer'    => 'nullable|boolean',
            'is_merchandise'          => 'nullable|boolean',
            'is_cottage'              => 'nullable|boolean',
            'is_ladies'               => 'nullable|boolean',
            'is_gents'                => 'nullable|boolean',
            'capacity'                => ['nullable', Rule::in(['1-200', '201-500', '501-1000', '1001-2000', '2000+'])],
            'inquiry'                 => 'nullable|string',
            'sample_given'            => 'nullable|string',
            'sample_required'         => 'nullable|string',
            'submitted_by'            => 'nullable|string|max:255',
            'customer_code'           => 'nullable|string|max:50',
            'customer_name_linked'    => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = Auth::id();

        $form = CustomerForm::create($validated);

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Customer form created successfully.',
            'data'    => $form,
        ], 201);
    }

    /**
     * Show a single form entry.
     *
     * GET /api/customer-forms/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $form = CustomerForm::with('user:id,name')->find($id);

        if (!$form) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Form not found.',
            ], 404);
        }

        // Salesperson can only view their own
        if ($user->role === 'user' && $form->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Access denied.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Form retrieved successfully.',
            'data'    => $form,
        ], 200);
    }

    /**
     * Update an existing form entry.
     *
     * PUT /api/customer-forms/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $form = CustomerForm::find($id);

        if (!$form) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Form not found.',
            ], 404);
        }

        // Salesperson can only edit their own
        if ($user->role === 'user' && $form->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Access denied.',
            ], 403);
        }

        $validated = $request->validate([
            'event_id'                => 'nullable|exists:customer_form_events,id',
            'form_type'               => ['nullable', Rule::in(['HBM', 'sales'])],
            'name'                    => 'nullable|string|max:255',
            'designation'             => 'nullable|string|max:255',
            'company_name'            => 'nullable|string|max:255',
            'address'                 => 'nullable|string|max:500',
            'city'                    => 'nullable|string|max:100',
            'mobile'                  => 'nullable|string|max:30',
            'phone'                   => 'nullable|string|max:30',
            'email'                   => 'nullable|email|max:255',
            'skype'                   => 'nullable|string|max:100',
            'is_shoe_material_dealer' => 'nullable|boolean',
            'is_shoe_manufacturer'    => 'nullable|boolean',
            'is_merchandise'          => 'nullable|boolean',
            'is_cottage'              => 'nullable|boolean',
            'is_ladies'               => 'nullable|boolean',
            'is_gents'                => 'nullable|boolean',
            'capacity'                => ['nullable', Rule::in(['1-200', '201-500', '501-1000', '1001-2000', '2000+'])],
            'inquiry'                 => 'nullable|string',
            'sample_given'            => 'nullable|string',
            'sample_required'         => 'nullable|string',
            'submitted_by'            => 'nullable|string|max:255',
            'customer_code'           => 'nullable|string|max:50',
            'customer_name_linked'    => 'nullable|string|max:255',
        ]);

        $form->update($validated);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Form updated successfully.',
            'data'    => $form->fresh(),
        ], 200);
    }

    /**
     * Delete a form entry (soft delete).
     *
     * DELETE /api/customer-forms/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $form = CustomerForm::find($id);

        if (!$form) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Form not found.',
            ], 404);
        }

        if ($user->role === 'user' && $form->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'Access denied.',
            ], 403);
        }

        $form->delete();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Form deleted successfully.',
        ], 200);
    }

    /**
     * Search forms by name, company, mobile, city, or customer_code.
     *
     * POST /api/customer-forms/search
     * Params: search (string), form_type (optional)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search'    => 'required|string|min:1',
            'form_type' => ['nullable', Rule::in(['HBM', 'sales'])],
        ]);

        $user  = Auth::user();
        $term  = $request->search;
        $query = CustomerForm::query();

        if ($user->role === 'user') {
            $query->forSalesperson($user->id);
        }

        if ($request->filled('form_type')) {
            $query->ofType($request->form_type);
        }

        $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('company_name', 'like', "%{$term}%")
              ->orWhere('mobile', 'like', "%{$term}%")
              ->orWhere('city', 'like', "%{$term}%")
              ->orWhere('customer_code', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });

        $results = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Search results retrieved.',
            'data'    => $results->items(),
            'pagination' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'total_pages'  => $results->lastPage(),
            ],
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════════════
    // EVENTS — list events, list forms per event, export, share
    // ════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/customer-form-events
     * Returns active events the salesperson should pick from on the mobile app.
     */
    public function listEvents(Request $request): JsonResponse
    {
        $events = CustomerFormEvent::query()
            ->where('is_active', true)
            ->withCount('forms')
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Active events retrieved.',
            'data'    => $events->map(fn ($e) => [
                'id'           => $e->id,
                'name'         => $e->name,
                'description'  => $e->description,
                'start_date'   => $e->start_date?->toDateString(),
                'end_date'     => $e->end_date?->toDateString(),
                'forms_count'  => (int) $e->forms_count,
            ])->all(),
        ], 200);
    }

    /**
     * GET /api/customer-form-events/{eventId}/forms
     * List all forms inside an event (scoped to current user when not admin).
     */
    public function listEventForms(Request $request, int $eventId): JsonResponse
    {
        $event = CustomerFormEvent::find($eventId);
        if (!$event) {
            return response()->json([
                'success' => false, 'status' => 404,
                'message' => 'Event not found.',
            ], 404);
        }

        $user  = Auth::user();
        $query = CustomerForm::query()->with('user:id,name')->where('event_id', $event->id);

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        }

        $forms = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Forms retrieved for event.',
            'data' => [
                'event' => [
                    'id'   => $event->id,
                    'name' => $event->name,
                ],
                'forms' => $forms->items(),
                'pagination' => [
                    'total'        => $forms->total(),
                    'per_page'     => $forms->perPage(),
                    'current_page' => $forms->currentPage(),
                    'total_pages'  => $forms->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/customer-form-events/{eventId}/export
     * Downloads all forms for an event as XLSX.
     */
    public function exportEvent(Request $request, int $eventId)
    {
        $event = CustomerFormEvent::find($eventId);
        if (!$event) abort(404, 'Event not found.');

        $user  = Auth::user();
        $query = CustomerForm::with(['user:id,name', 'event:id,name'])
            ->where('event_id', $event->id);
        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        }

        $filename = 'forms_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $event->name) . '_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new CustomerFormsExport($query->get()), $filename);
    }

    /**
     * POST /api/customer-form-events/{eventId}/share-whatsapp
     * Body: { "phone": "923338123171" }
     *
     * Generates the Excel, uploads to WhatsApp media, and sends it as an
     * attachment to the given phone via the existing WhatsApp service.
     */
    public function shareEventViaWhatsApp(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:8|max:20',
        ]);

        $event = CustomerFormEvent::find($eventId);
        if (!$event) {
            return response()->json([
                'success' => false, 'status' => 404, 'message' => 'Event not found.',
            ], 404);
        }

        $user  = Auth::user();
        $query = CustomerForm::with(['user:id,name', 'event:id,name'])
            ->where('event_id', $event->id);
        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        }
        $forms = $query->get();

        if ($forms->isEmpty()) {
            return response()->json([
                'success' => false, 'status' => 400,
                'message' => 'No forms to share for this event.',
            ], 400);
        }

        // Build the file in storage, then send via WhatsApp.
        $filename = 'forms_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $event->name) . '_' . now()->format('Ymd_His') . '.xlsx';
        $relativePath = 'customer-form-exports/' . $filename;
        Excel::store(new CustomerFormsExport($forms), $relativePath, 'local');
        $absolutePath = storage_path('app/' . $relativePath);

        try {
            $whatsapp = new WhatsAppService();
            $phone    = $whatsapp->formatPhoneNumber($request->input('phone'));
            if (!$phone) {
                return response()->json([
                    'success' => false, 'status' => 400,
                    'message' => 'Invalid phone number.',
                ], 400);
            }

            $caption = "Customer forms for event: {$event->name}\nTotal forms: {$forms->count()}";
            $result  = $whatsapp->sendDocument($phone, $absolutePath, $filename, $caption);

            return response()->json([
                'success' => $result['success'] ?? false,
                'status'  => ($result['success'] ?? false) ? 200 : 500,
                'message' => ($result['success'] ?? false)
                    ? 'Forms shared via WhatsApp.'
                    : ($result['error'] ?? 'Failed to share via WhatsApp.'),
                'data'    => [
                    'event_id'    => $event->id,
                    'forms_count' => $forms->count(),
                    'phone'       => $phone,
                ],
            ], ($result['success'] ?? false) ? 200 : 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false, 'status' => 500,
                'message' => 'WhatsApp send failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer-forms/{id}/share-whatsapp
     * Body: { "phone": "923338123171" }
     *
     * Shares a SINGLE filled form as a formatted WhatsApp text message.
     */
    public function shareFormViaWhatsApp(Request $request, int $id): JsonResponse
    {
        $request->validate(['phone' => 'required|string|min:8|max:20']);

        $form = CustomerForm::with('event:id,name')->find($id);
        if (!$form) {
            return response()->json([
                'success' => false, 'status' => 404, 'message' => 'Form not found.',
            ], 404);
        }

        $whatsapp = new WhatsAppService();
        $phone    = $whatsapp->formatPhoneNumber($request->input('phone'));
        if (!$phone) {
            return response()->json([
                'success' => false, 'status' => 400, 'message' => 'Invalid phone number.',
            ], 400);
        }

        $msg = $this->formatFormAsMessage($form);

        try {
            $result = $whatsapp->sendTextMessage($phone, $msg);
            return response()->json([
                'success' => $result['success'] ?? false,
                'status'  => ($result['success'] ?? false) ? 200 : 500,
                'message' => ($result['success'] ?? false)
                    ? 'Form shared via WhatsApp.'
                    : ($result['error'] ?? 'WhatsApp send failed.'),
            ], ($result['success'] ?? false) ? 200 : 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false, 'status' => 500,
                'message' => 'WhatsApp send failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format a CustomerForm into a human-readable WhatsApp text message.
     */
    private function formatFormAsMessage(CustomerForm $f): string
    {
        $yesno = fn ($v) => $v ? '✓' : '✗';
        $lines = [
            "*Customer Form — {$f->name}*",
            $f->event ? "Event: {$f->event->name}" : null,
            $f->form_type ? "Type: {$f->form_type}" : null,
            '',
            $f->company_name ? "🏢 *Company:* {$f->company_name}" : null,
            $f->designation  ? "💼 *Designation:* {$f->designation}" : null,
            $f->mobile       ? "📱 *Mobile:* {$f->mobile}" : null,
            $f->phone        ? "☎️ *Phone:* {$f->phone}" : null,
            $f->email        ? "✉️ *Email:* {$f->email}" : null,
            $f->skype        ? "💬 *Skype:* {$f->skype}" : null,
            ($f->address || $f->city) ? "📍 *Address:* " . trim(($f->address ?? '') . ($f->city ? ', ' . $f->city : '')) : null,
            '',
            "*Categories*",
            "  Shoe Material Dealer: {$yesno($f->is_shoe_material_dealer)}",
            "  Shoe Manufacturer:    {$yesno($f->is_shoe_manufacturer)}",
            "  Merchandise:          {$yesno($f->is_merchandise)}",
            "  Cottage:              {$yesno($f->is_cottage)}",
            "  Ladies:               {$yesno($f->is_ladies)}",
            "  Gents:                {$yesno($f->is_gents)}",
            $f->capacity ? "  Capacity: {$f->capacity}" : null,
            '',
            $f->inquiry         ? "*Inquiry:*\n{$f->inquiry}" : null,
            $f->sample_given    ? "*Sample Given:* {$f->sample_given}" : null,
            $f->sample_required ? "*Sample Required:* {$f->sample_required}" : null,
            $f->submitted_by    ? "Submitted By: {$f->submitted_by}" : null,
        ];

        return implode("\n", array_filter($lines));
    }
}
