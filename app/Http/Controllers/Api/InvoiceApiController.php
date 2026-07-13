<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InvoiceApiController extends Controller
{
    /**
     * Get all invoices visible to the authenticated user.
     *
     * For a salesperson (role = 'user') this returns invoices that belong to
     * customers assigned to them (matched by customer_code / customer_name).
     * Admins see every invoice.
     *
     * GET /api/invoices
     * Params: page, per_page, status (processing_status), start_date, end_date
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'        => 'nullable|string',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'date_preset'   => 'nullable|in:today,yesterday,last_week,this_month,last_month,this_year,custom',
            'customer_name' => 'nullable|string|max:255',
            'customer_id'   => 'nullable|string|max:64',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $user  = Auth::user();
        $query = Invoice::with('uploader:id,name');

        $this->applyCommonFilters($query, $request);

        $perPage  = $request->input('per_page', 15);
        $invoices = $query->orderBy('invoice_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Invoices retrieved successfully.',
            'data'    => $this->transformInvoices($invoices->items()),
            'pagination' => [
                'total'         => $invoices->total(),
                'per_page'      => $invoices->perPage(),
                'current_page'  => $invoices->currentPage(),
                'total_pages'   => $invoices->lastPage(),
                'next_page_url' => $invoices->nextPageUrl(),
                'prev_page_url' => $invoices->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Get a single invoice by ID.
     *
     * GET /api/invoices/{id}
     */
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with('uploader:id,name')->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Invoice not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Invoice retrieved successfully.',
            'data'    => $this->transformInvoice($invoice),
        ], 200);
    }

    /**
     * Download invoice PDF.
     *
     * GET /api/invoices/{id}/download
     *
     * Once a builty has been uploaded, `pdf_path` already points to the
     * merged (invoice + builty) PDF — we simply stream that file. The
     * unmerged invoice is preserved under `original_pdf_path` for re-merge.
     */
    public function download(int $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Invoice not found.',
            ], 404);
        }

        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Invoice file not found.',
            ], 404);
        }

        $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $invoice->customer_name);
        $filename  = ($invoice->customer_code ?: 'invoice') . '_' . $sanitized . '.pdf';

        return Storage::disk('local')->download($invoice->pdf_path, $filename);
    }

    /**
     * Upload a builty PDF and merge it into the invoice's stored PDF.
     *
     * POST /api/invoices/upload-builty
     * Body (multipart):
     *   invoice_number — required string. Matches against the comma-separated
     *                    list stored in invoices.invoice_number, so "1123"
     *                    finds an Invoice row whose invoice_number is
     *                    "1123" OR "1123, 1124" OR "1124, 1123" etc.
     *   builty_file    — required PDF, max 25 MB.
     *
     * After this call:
     *   - `invoices.pdf_path` points to the merged (invoice + builty) PDF.
     *   - `invoices.original_pdf_path` preserves the un-merged invoice PDF
     *     so subsequent re-uploads merge from scratch (no stacking).
     *   - `invoices.builty_path` stores the latest builty file separately
     *     for audit.
     */
    public function uploadBuilty(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_number' => 'required|string|max:64',
            'builty_file'    => 'required|file|mimes:pdf|max:25600',
        ]);

        $invoiceNumber = trim($request->invoice_number);

        // Find the invoice row whose invoice_number contains this number.
        // invoices.invoice_number stores a comma-separated list of Oracle
        // invoice numbers — match the requested number on any list position.
        $invoice = Invoice::query()
            ->where('processing_status', '!=', 'failed')
            ->where(function ($q) use ($invoiceNumber) {
                $q->where('invoice_number', $invoiceNumber)
                  ->orWhere('invoice_number', 'LIKE', $invoiceNumber . ',%')
                  ->orWhere('invoice_number', 'LIKE', '%, ' . $invoiceNumber . ',%')
                  ->orWhere('invoice_number', 'LIKE', '%, ' . $invoiceNumber)
                  ->orWhere('invoice_number', 'LIKE', '%,' . $invoiceNumber . ',%')
                  ->orWhere('invoice_number', 'LIKE', '%,' . $invoiceNumber);
            })
            ->first();

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => "No invoice found with invoice number {$invoiceNumber}.",
            ], 404);
        }

        // Decide which file to read invoice pages from. On the first builty
        // upload, `original_pdf_path` is empty and `pdf_path` still holds the
        // un-merged invoice. After the first merge, `pdf_path` becomes the
        // merged file and `original_pdf_path` snapshots the un-merged copy
        // — so re-uploads always re-merge cleanly from the original.
        $sourceInvoicePath = $invoice->original_pdf_path ?: $invoice->pdf_path;

        if (!$sourceInvoicePath || !Storage::disk('local')->exists($sourceInvoicePath)) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => "Invoice {$invoiceNumber} has no PDF on file to merge with.",
            ], 404);
        }

        $tempMergedFile = null;
        try {
            $builtyFile = $request->file('builty_file');

            // 1) Persist the builty under invoices/builties/ — keeps an audit
            //    copy of every uploaded builty, useful when re-uploading.
            $builtyFilename = sprintf(
                '%d_%s_%s.pdf',
                $invoice->id,
                preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoiceNumber),
                now()->format('Y-m-d_His')
            );
            $builtyStoragePath = 'invoices/builties/' . $builtyFilename;
            Storage::disk('local')->put(
                $builtyStoragePath,
                file_get_contents($builtyFile->getPathname())
            );

            // 2) Build the merged PDF (original invoice pages → then builty pages).
            $pdf = new \setasign\Fpdi\Fpdi();

            $invoicePages = $pdf->setSourceFile(Storage::disk('local')->path($sourceInvoicePath));
            for ($i = 1; $i <= $invoicePages; $i++) {
                $tpl  = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }

            $builtyPages = $pdf->setSourceFile(Storage::disk('local')->path($builtyStoragePath));
            for ($i = 1; $i <= $builtyPages; $i++) {
                $tpl  = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }

            $tempMergedFile = tempnam(sys_get_temp_dir(), 'inv_builty_') . '.pdf';
            $pdf->Output($tempMergedFile, 'F');

            // 3) Persist merged PDF alongside the original (same customer folder)
            //    with a `_with_builty` suffix. We never overwrite the original.
            $originalDir = dirname($sourceInvoicePath);
            $originalBase = pathinfo($sourceInvoicePath, PATHINFO_FILENAME);
            $mergedStoragePath = $originalDir . '/' . $originalBase . '_with_builty.pdf';
            Storage::disk('local')->put($mergedStoragePath, file_get_contents($tempMergedFile));
            @unlink($tempMergedFile);
            $tempMergedFile = null;

            // 4) Clean up the previous builty file (audit copy) and the
            //    previous merged file (the one currently at pdf_path) so we
            //    don't accumulate stale "*_with_builty.pdf" files. The
            //    un-merged original at sourceInvoicePath is preserved.
            $previousBuilty = $invoice->builty_path;
            if ($previousBuilty
                && $previousBuilty !== $builtyStoragePath
                && Storage::disk('local')->exists($previousBuilty)) {
                try { Storage::disk('local')->delete($previousBuilty); }
                catch (\Throwable $e) { \Log::warning('Could not delete previous builty', ['path' => $previousBuilty, 'error' => $e->getMessage()]); }
            }
            if ($invoice->original_pdf_path
                && $invoice->pdf_path
                && $invoice->pdf_path !== $invoice->original_pdf_path
                && $invoice->pdf_path !== $mergedStoragePath
                && Storage::disk('local')->exists($invoice->pdf_path)) {
                try { Storage::disk('local')->delete($invoice->pdf_path); }
                catch (\Throwable $e) { \Log::warning('Could not delete previous merged file', ['path' => $invoice->pdf_path, 'error' => $e->getMessage()]); }
            }

            // 5) Update the Invoice row.
            $updates = [
                'pdf_path'           => $mergedStoragePath,
                'builty_path'        => $builtyStoragePath,
                'builty_uploaded_at' => now(),
                'builty_uploaded_by' => auth()->id(),
            ];
            // On the FIRST builty upload, snapshot the un-merged path so
            // future re-uploads still merge from the same clean source.
            if (empty($invoice->original_pdf_path)) {
                $updates['original_pdf_path'] = $sourceInvoicePath;
            }
            $invoice->update($updates);

            \Log::info('Builty merged into invoice PDF', [
                'invoice_id'         => $invoice->id,
                'invoice_number'     => $invoice->invoice_number,
                'matched_number'     => $invoiceNumber,
                'source_invoice_pdf' => $sourceInvoicePath,
                'builty_path'        => $builtyStoragePath,
                'merged_pdf_path'    => $mergedStoragePath,
                'invoice_pages'      => $invoicePages,
                'builty_pages'       => $builtyPages,
                'total_pages'        => $invoicePages + $builtyPages,
                'replaced_previous'  => $previousBuilty !== null,
                'uploaded_by'        => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Builty merged into invoice PDF successfully.',
                'data' => [
                    'invoice_id'             => $invoice->id,
                    'invoice_number_field'   => $invoice->invoice_number,
                    'matched_invoice_number' => $invoiceNumber,
                    'customer_code'          => $invoice->customer_code,
                    'customer_name'          => $invoice->customer_name,

                    // Direct, browser-openable URL to the updated merged PDF.
                    // Served by the /invoices/{path} route in routes/web.php
                    // (with path-traversal protection).
                    'pdf_url'                => url($invoice->pdf_path),

                    // Authenticated API endpoint that streams the same merged
                    // PDF as an attachment download.
                    'download_url'           => route('api.invoices.download', ['id' => $invoice->id]),

                    // Just the builty (audit copy), in case the caller wants
                    // to inspect it on its own.
                    'builty_url'             => $invoice->builty_path
                        ? url($invoice->builty_path)
                        : null,

                    'pages' => [
                        'invoice' => $invoicePages,
                        'builty'  => $builtyPages,
                        'total'   => $invoicePages + $builtyPages,
                    ],
                    'builty_uploaded_at'        => optional($invoice->builty_uploaded_at)->toIso8601String(),
                    'replaced_previous_builty'  => $previousBuilty !== null,
                ],
            ], 200);

        } catch (\Exception $e) {
            if ($tempMergedFile && file_exists($tempMergedFile)) {
                @unlink($tempMergedFile);
            }
            \Log::error('Builty upload/merge failed', [
                'invoice_number' => $invoiceNumber,
                'invoice_id'     => $invoice->id ?? null,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to merge builty into invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoices for a specific customer code.
     * Admin can omit customer_code to retrieve every invoice.
     *
     * POST /api/invoices/by-customer
     * Params: customer_code (required for non-admin), per_page (optional)
     */
    public function byCustomer(Request $request): JsonResponse
    {
        $user    = Auth::user();
        $isAdmin = $user && (
            (method_exists($user, 'isAdmin') && $user->isAdmin())
            || ($user->role ?? null) === 'admin'
        );

        // Admin can call this without a customer_code (returns everything).
        // Anyone else still needs to specify the customer.
        $request->validate([
            'customer_code' => $isAdmin ? 'nullable|string' : 'required|string',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'date_preset'   => 'nullable|in:today,yesterday,last_week,this_month,last_month,this_year,custom',
            'customer_name' => 'nullable|string|max:255',
            'customer_id'   => 'nullable|string|max:64',
            'status'        => 'nullable|string',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $query = Invoice::with('uploader:id,name')
            ->orderBy('invoice_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->filled('customer_code')) {
            $query->where('customer_code', $request->customer_code);
        }

        $this->applyCommonFilters($query, $request);

        $perPage  = $request->input('per_page', 15);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => $request->filled('customer_code')
                ? 'Customer invoices retrieved successfully.'
                : 'All invoices retrieved successfully.',
            'data'    => $this->transformInvoices($invoices->items()),
            'pagination' => [
                'total'        => $invoices->total(),
                'per_page'     => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'total_pages'  => $invoices->lastPage(),
                'next_page_url'   => $invoices->nextPageUrl(),
                'prev_page_url'   => $invoices->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Search invoices by customer_name, customer_code, or invoice_number.
     *
     * POST /api/invoices/search
     * Params: search (required)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search'        => 'required|string|min:1',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'date_preset'   => 'nullable|in:today,yesterday,last_week,this_month,last_month,this_year,custom',
            'customer_name' => 'nullable|string|max:255',
            'customer_id'   => 'nullable|string|max:64',
            'status'        => 'nullable|string',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $term  = $request->search;
        $query = Invoice::with('uploader:id,name');

        // Multi-field text search
        $query->where(function ($q) use ($term) {
            $q->where('customer_name',  'like', "%{$term}%")
              ->orWhere('customer_code',   'like', "%{$term}%")
              ->orWhere('customer_phone',  'like', "%{$term}%")
              ->orWhere('invoice_number',  'like', "%{$term}%")
              ->orWhere('notes',           'like', "%{$term}%");
        });

        $this->applyCommonFilters($query, $request);

        $perPage  = $request->input('per_page', 15);
        $invoices = $query->orderBy('invoice_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Invoice search results retrieved.',
            'data'    => $this->transformInvoices($invoices->items()),
            'pagination' => [
                'total'        => $invoices->total(),
                'per_page'     => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'total_pages'  => $invoices->lastPage(),
                'next_page_url' => $invoices->nextPageUrl(),
                'prev_page_url' => $invoices->previousPageUrl(),
            ],
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Apply the common filter set to an invoice query.
     *
     * Supported params:
     *   - date_preset:  today | yesterday | last_week | this_month |
     *                   last_month | this_year | custom
     *   - start_date / end_date:  Used when date_preset = 'custom' or no preset
     *   - customer_name:  LIKE search on invoices.customer_name
     *   - customer_id  :  exact match on invoices.customer_code
     *   - status       :  invoices.processing_status
     */
    private function applyCommonFilters($query, Request $request): void
    {
        // Resolve the date range from the preset (if provided), else fall back
        // to explicit start_date / end_date.
        [$from, $to] = $this->resolveDateRange($request);

        if ($from) $query->whereDate('invoice_date', '>=', $from->toDateString());
        if ($to)   $query->whereDate('invoice_date', '<=', $to->toDateString());

        if ($request->filled('customer_name')) {
            $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_code', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('processing_status', $request->status);
        }
    }

    /**
     * Returns [Carbon|null $from, Carbon|null $to] based on either
     * the date_preset or the explicit start_date/end_date params.
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = $request->input('date_preset');
        $now    = \Carbon\Carbon::now();

        switch ($preset) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'yesterday':
                $y = $now->copy()->subDay();
                return [$y->copy()->startOfDay(), $y->copy()->endOfDay()];
            case 'last_week':
                // Last 7 days inclusive of today
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()];
            case 'this_month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'last_month':
                $lm = $now->copy()->subMonthNoOverflow();
                return [$lm->copy()->startOfMonth(), $lm->copy()->endOfMonth()];
            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            case 'custom':
            default:
                $from = $request->filled('start_date') ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : null;
                $to   = $request->filled('end_date')   ? \Carbon\Carbon::parse($request->end_date)->endOfDay()     : null;
                return [$from, $to];
        }
    }

    /**
     * Return empty pagination structure.
     */
    private function emptyPagination(): array
    {
        return [
            'total'        => 0,
            'per_page'     => 15,
            'current_page' => 1,
            'total_pages'  => 0,
        ];
    }

    /**
     * Transform an invoice for API response:
     *   - `pdf_path`     → direct public URL to the (merged-if-builty-uploaded) PDF
     *   - `builty_path`  → direct public URL to the standalone builty file, if any
     *   - `download_url` → API endpoint that serves the same PDF as an attachment
     *   - adds `customer_number` from the customers table
     * Key names are preserved so existing API consumers don't break.
     */
    private function transformInvoice($invoice): array
    {
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : ($invoice->id ?? null);
        $data = $invoice instanceof Invoice ? $invoice->toArray() : (array) $invoice;

        // Direct PDF URL: served by /invoices/{path} route (routes/web.php).
        // After a builty merge, pdf_path on the row already points to the
        // merged file — so this URL automatically delivers the up-to-date
        // invoice+builty PDF without any extra logic here.
        if (!empty($data['pdf_path'])) {
            $data['pdf_path'] = url($data['pdf_path']);
        }

        // Standalone builty URL — useful when the caller wants only the
        // builty document instead of the merged invoice.
        if (!empty($data['builty_path'])) {
            $data['builty_path'] = url($data['builty_path']);
        }

        // API download endpoint (Sanctum auth, served as attachment).
        if ($invoiceId) {
            $data['download_url'] = route('api.invoices.download', ['id' => $invoiceId]);
        }

        // Pull customer_number from the customers table by matching customer_code
        // against either Customer.customer_number or Customer.customer_id.
        $code = $data['customer_code'] ?? null;
        if ($code) {
            $customerNumber = Customer::where('customer_number', $code)
                ->orWhere('customer_id', $code)
                ->value('customer_number');
            $data['customer_number'] = $customerNumber;
        } else {
            $data['customer_number'] = null;
        }

        return $data;
    }

    /**
     * Apply transformInvoice to a collection / array of invoices.
     */
    private function transformInvoices($invoices): array
    {
        return collect($invoices)->map(fn ($i) => $this->transformInvoice($i))->all();
    }
}
