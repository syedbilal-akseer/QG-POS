<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;

class InvoiceController extends Controller
{

    public function testParser($pdf)
    {
        return $this->extractCustomersFromPDF($pdf);
    }
    /**
     * Display invoice management page
     */
    public function index(Request $request)
    {
        return $this->renderListing($request, false);
    }

    /**
     * Read-only listing for view-* roles. Same query/layout as index(); the blade
     * suppresses every write action (send, edit phone, delete, upload, bulk send)
     * when $viewOnly is true. Routed at /admin/invoices/view.
     */
    public function viewIndex(Request $request)
    {
        return $this->renderListing($request, true);
    }

    /**
     * CMD-KHI / CMD-LHR restrict to customers whose Oracle "salesperson"
     * matches one of their assigned salespeople. customers.salesperson stores
     * the salesperson's name / oracle_user_name (a string), not a user id, so
     * assigned ids are resolved through the users table first.
     *
     * Returns null when no restriction applies (not CMD, or CMD with "All"
     * access), or an array of allowed customer_number values (possibly empty,
     * meaning the assigned names matched no customers).
     */
    private function resolveCmdCustomerCodes(User $user): ?array
    {
        if (! $user->isCmd()) {
            return null;
        }

        $assignedSalespeopleIds = $user->getAssignedSalespeopleIds();
        if (empty($assignedSalespeopleIds)) {
            return null; // "All" access
        }

        $salespersonNames = User::whereIn('id', $assignedSalespeopleIds)
            ->get(['name', 'oracle_user_name'])
            ->flatMap(fn($u) => [$u->name, $u->oracle_user_name])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ! empty($salespersonNames)
            ? Customer::whereIn('salesperson', $salespersonNames)->pluck('customer_number')->all()
            : [];
    }

    /** Who's allowed to see which invoices — admins see all, CMD roles see their assigned customers, everyone else sees only their own uploads. */
    private function applyAccessScope($q, User $user, array $admins, ?array $cmdCustomerCodes): void
    {
        if (in_array($user->email, $admins) || $user->isAdmin()) {
            return;
        }
        if ($cmdCustomerCodes !== null) {
            $q->whereIn('invoices.customer_code', $cmdCustomerCodes);
            return;
        }
        if (! $user->isCmd()) {
            $q->where('invoices.uploaded_by', $user->id);
        }
    }

    /** Date/status/whatsapp/customer/search filters shared by the listing queries and the lazy row-loading endpoint. */
    private function applyRequestFilters($q, Request $request): void
    {
        $filterFrom     = $request->input('from') ?: null;
        $filterTo       = $request->input('to') ?: null;
        $filterStatus   = $request->input('status') ?: null;
        $filterWhatsapp = $request->input('whatsapp') ?: null;
        $filterCustomer = $request->input('customer') ?: null;

        if ($request->boolean('unsent_only')) {
            $filterWhatsapp = 'pending';
        }

        if ($filterFrom) {
            $q->whereDate('invoices.uploaded_at', '>=', $filterFrom);
        }
        if ($filterTo) {
            $q->whereDate('invoices.uploaded_at', '<=', $filterTo);
        }
        if ($filterCustomer) {
            $q->where('invoices.customer_code', $filterCustomer);
        }
        if ($filterStatus) {
            if ($filterStatus === 'pending') {
                $q->where(function ($inner) {
                    $inner->whereNull('invoices.processing_status')->orWhere('invoices.processing_status', '');
                });
            } else {
                $q->where('invoices.processing_status', $filterStatus);
            }
        }
        if ($filterWhatsapp) {
            if ($filterWhatsapp === 'pending') {
                $q->where(function ($inner) {
                    $inner->whereNull('invoices.whatsapp_status')->orWhere('invoices.whatsapp_status', '');
                });
            } else {
                $q->where('invoices.whatsapp_status', $filterWhatsapp);
            }
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $q->where(function ($inner) use ($search) {
                $inner->where('invoices.customer_code', 'like', "%{$search}%")
                    ->orWhere('invoices.customer_name', 'like', "%{$search}%")
                    ->orWhere('invoices.customer_phone', 'like', "%{$search}%")
                    ->orWhere('invoices.original_filename', 'like', "%{$search}%")
                    ->orWhere('invoices.invoice_number', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Detail rows for one (date, uploaded_by) accordion group on the send
     * page — fetched lazily via AJAX only when that group is expanded, so the
     * initial page load doesn't have to render every invoice row up front.
     */
    public function rowsForGroup(Request $request)
    {
        $request->validate([
            'date'        => 'required|date',
            'uploaded_by' => 'required|integer',
        ]);

        $user             = auth()->user();
        $admins           = ['mahmood@quadri-group.com'];
        $cmdCustomerCodes = $this->resolveCmdCustomerCodes($user);

        $invoices = Invoice::query()
            ->whereDate('invoices.uploaded_at', $request->date)
            ->where('invoices.uploaded_by', $request->integer('uploaded_by'))
            ->tap(fn($q) => $this->applyAccessScope($q, $user, $admins, $cmdCustomerCodes))
            ->tap(fn($q) => $this->applyRequestFilters($q, $request))
            ->with('uploader:id,name')
            ->orderBy('invoices.uploaded_at', 'desc')
            ->get();

        $this->attachLivePhones($invoices);

        return view('admin.invoices.partials.invoice-detail-rows', compact('invoices'));
    }

    /**
     * Live customer master info for the "click customer to view details" popup
     * on the invoices view page — deliberately NOT sourced from the invoice's
     * own (possibly stale) customer_name/customer_phone columns.
     */
    public function customerInfo(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $customer = Customer::where('customer_number', $request->code)
            ->orWhere('customer_id', $request->code)
            ->first([
                'customer_id', 'ou_name', 'ou_id', 'customer_name', 'customer_number',
                'customer_site_id', 'salesperson', 'city', 'area', 'address1',
                'contact_number', 'email_address', 'nic', 'ntn',
                'price_list_id', 'price_list_name', 'creation_date',
            ]);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        return response()->json($customer);
    }

    /**
     * Batch-resolve each invoice's customer's CURRENT contact_number from the
     * customers table (one query for the whole collection) and stash it as
     * ->live_phone. Used to decide whether "Send WhatsApp" should be offered
     * at all — an invoice's own customer_phone column can be blank or stale,
     * so gating only on that would hide/allow the action incorrectly.
     */
    private function attachLivePhones($invoices): void
    {
        $codes = $invoices->pluck('customer_code')->filter()->unique()->values()->all();
        if (empty($codes)) {
            return;
        }

        $phonesByCode = Customer::whereIn('customer_number', $codes)
            ->orWhereIn('customer_id', $codes)
            ->get(['customer_number', 'customer_id', 'contact_number'])
            ->reduce(function (array $carry, Customer $customer) {
                if ($customer->contact_number) {
                    if ($customer->customer_number) {
                        $carry[$customer->customer_number] = $customer->contact_number;
                    }
                    if ($customer->customer_id) {
                        $carry[$customer->customer_id] = $customer->contact_number;
                    }
                }
                return $carry;
            }, []);

        foreach ($invoices as $invoice) {
            $invoice->live_phone = $phonesByCode[$invoice->customer_code] ?? null;
        }
    }

    private function renderListing(Request $request, bool $viewOnly)
    {
        $diskFiles = [];

        // Date range + status filters are opt-in. No default constraint; the page
        // shows every upload until the user picks a From/To or uses the "Today"
        // quick-button in the filter bar.
        $filterFrom     = $request->input('from') ?: null;
        $filterTo       = $request->input('to') ?: null;
        $filterStatus   = $request->input('status') ?: null;   // completed | processing | failed | pending
        $filterWhatsapp = $request->input('whatsapp') ?: null; // sent | failed | pending
        $filterCustomer = $request->input('customer') ?: null; // exact customer_code

        // Resolved once (not per-query) since it's identical across every
        // tap($baseFilter) call below — avoids re-running the user/customer
        // lookups 3-4 times per page load.
        $user             = auth()->user();
        $admins           = ['mahmood@quadri-group.com'];
        $cmdCustomerCodes = $this->resolveCmdCustomerCodes($user);

        // Build the base filter once and clone it for each downstream query so
        // search/date filters apply consistently to stats, the date pagination,
        // and the actual invoice load.
        $baseFilter = function ($q) use ($request, $user, $admins, $cmdCustomerCodes) {
            $this->applyAccessScope($q, $user, $admins, $cmdCustomerCodes);
            $this->applyRequestFilters($q, $request);
        };

        // Filesystem files matching the search term (kept from the prior behavior).
        if ($request->filled('search') && strlen($request->search) >= 4) {
            $customerPath = 'invoices/customers/' . $request->search;
            if (Storage::disk('local')->exists($customerPath)) {
                foreach (Storage::disk('local')->files($customerPath) as $file) {
                    $diskFiles[] = [
                        'name' => basename($file),
                        'path' => $file,
                        'size' => Storage::disk('local')->size($file),
                        'time' => Storage::disk('local')->lastModified($file),
                    ];
                }
            }
        }

        // Aggregate stats across ALL filtered invoices — independent of pagination,
        // so the summary cards always reflect the full filtered set.
        $stats = Invoice::query()
            ->tap($baseFilter)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN processing_status = 'completed'  THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN processing_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN processing_status = 'failed'     THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN processing_status IS NULL OR processing_status = '' OR processing_status = 'pending'
                     THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN processing_status = 'completed'
                          AND pdf_path IS NOT NULL
                          AND (whatsapp_status IS NULL OR whatsapp_status = '' OR whatsapp_status = 'failed')
                     THEN 1 ELSE 0 END) as unsent
            ")
            ->first();

        // Whether the unsent-only quick filter is currently active — used by
        // the blade to render either the "View Unsent" call-to-action or the
        // "Clear" link in the active-banner.
        $unsentOnly = $request->boolean('unsent_only');

        if ($viewOnly) {
            // Read-only page = flat paginated table (orders-style). No date
            // grouping; the user filters / sorts the whole list.
            //
            // Salesperson pulled via a correlated scalar subquery rather than
            // a join — invoices.customer_code may match either
            // customers.customer_number OR customers.customer_id (invoices
            // store either form depending on upload source), and a join on
            // both columns risks matching two different customer rows and
            // duplicating the invoice row. A LIMIT 1 scalar subquery can't
            // fan out no matter how many customers rows match.
            $invoicesPage = Invoice::query()
                ->select([
                    'invoices.id',
                    'invoices.customer_name',
                    'invoices.customer_code',
                    'invoices.customer_phone',
                    'invoices.invoice_number',
                    'invoices.total_amount',
                    'invoices.processing_status',
                    'invoices.whatsapp_status',
                    'invoices.whatsapp_sent_at',
                    'invoices.uploaded_at',
                    'invoices.uploaded_by',
                    'invoices.pdf_path',
                    'invoices.original_filename',
                ])
                ->addSelect(['salesperson' => Customer::select('salesperson')
                        ->whereColumn('customers.customer_number', 'invoices.customer_code')
                        ->orWhereColumn('customers.customer_id', 'invoices.customer_code')
                        ->limit(1),
                ])
                ->tap($baseFilter)
                ->with('uploader:id,name')
                ->latest('invoices.uploaded_at')
                ->paginate(10)
                ->withQueryString();

            // Distinct customers seen across the invoices table — populates the
            // Customer filter dropdown. Pulled here (not from the customers
            // table) so the list only contains customers who actually have
            // invoices uploaded.
            $customerOptions = Invoice::query()
                ->selectRaw('customer_code, MAX(customer_name) as customer_name')
                ->whereNotNull('customer_code')
                ->groupBy('customer_code')
                ->get();

            return view('admin.invoices.view', compact(
                'invoicesPage', 'stats', 'diskFiles',
                'filterFrom', 'filterTo',
                'filterStatus', 'filterWhatsapp', 'filterCustomer',
                'customerOptions'
            ));
        }

        // Send page — paginate by upload DATE so an expanded accordion always
        // shows every invoice for that day.
        $datesPage = Invoice::query()
            ->tap($baseFilter)
            ->whereNotNull('uploaded_at')
            ->selectRaw('DATE(uploaded_at) as upload_date, COUNT(*) as invoice_count')
            ->groupBy('upload_date')
            ->orderByDesc('upload_date')
            ->paginate(30)
            ->withQueryString();

        $visibleDates = $datesPage->pluck('upload_date')->all();

        // Light columns only — this collection just drives the per-date/
        // per-uploader accordion HEADERS (counts, unsent ids for the "Send"
        // button). The actual detail rows (customer info, action menus, …)
        // are fetched lazily via rowsForGroup() only when a group is expanded,
        // so a page with hundreds of invoices across its visible dates
        // doesn't render all of them into the DOM up front.
        $invoices = Invoice::query()
            ->tap($baseFilter)
            ->select(['id', 'uploaded_by', 'uploaded_at', 'processing_status', 'whatsapp_status', 'pdf_path', 'customer_code', 'customer_phone'])
            ->with('uploader:id,name')
            ->when(! empty($visibleDates),
                fn($q) => $q->whereIn(DB::raw('DATE(uploaded_at)'), $visibleDates),
                fn($q) => $q->whereRaw('1 = 0')
            )
            ->orderBy('uploaded_at', 'desc')
            ->get();

        // Needed so the per-day "Send N" count/button only counts invoices
        // whose customer currently HAS a contact number — see attachLivePhones().
        $this->attachLivePhones($invoices);

        $whatsappService   = new \App\Services\WhatsAppService();
        $templatesResult   = $whatsappService->getAvailableTemplates();
        $whatsappTemplates = $templatesResult['success'] ? $templatesResult['templates'] : [];

        $whatsappTemplates = array_filter($whatsappTemplates, function ($t) {
            return $t['status'] === 'APPROVED' && in_array($t['name'], ['invoice_ready', 'invoice_urdu']);
        });
        // dd($invoices->take(10), auth()->id());
        return view('admin.invoices.index', compact(
            'invoices', 'datesPage', 'stats',
            'whatsappTemplates', 'diskFiles',
            'filterFrom', 'filterTo',
            'filterStatus', 'filterWhatsapp',
            'viewOnly', 'unsentOnly'
        ));
    }

    /**
     * Export invoices to Excel.
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $filename = 'invoices_export_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new \App\Exports\InvoicesExport($startDate, $endDate), $filename);
    }

    /**
     * Show upload form
     */
    public function upload()
    {
        return view('admin.invoices.upload');
    }

    /**
     * Process uploaded PDF and separate by customer
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_file' => 'required|file|mimes:pdf|max:51200', // 50MB max
            'notes'        => 'nullable|string|max:1000',
        ]);

        try {
            $file             = $request->file('invoice_file');
            $originalFilename = $file->getClientOriginalName();
            $fileHash         = hash_file('sha256', $file->getPathname());

            // Store the original PDF
            $filename = 'invoices/originals/' . Str::uuid() . '.pdf';
            Storage::disk('local')->put($filename, file_get_contents($file));

            Log::info('Processing PDF invoice', [
                'filename'  => $originalFilename,
                'size'      => $file->getSize(),
                'file_hash' => $fileHash,
            ]);

            // Extract text and identify customers
            $customersData = $this->extractCustomersFromPDF($file->getPathname());

            if (empty($customersData)) {
                return back()->withErrors(['error' => 'No customers found in the PDF. Please check the file format.']);
            }

            // ── Duplicate detection by invoice_number ──
            // For each extracted customer, look at the invoice numbers parsed
            // out of the PDF. If EVERY one of those invoice_numbers already
            // exists in a non-failed Invoice row, that customer is a duplicate
            // and gets skipped. If even one is new, we still process the
            // customer (so partial uploads still work).
            $customersToProcess = [];
            $skippedDuplicates  = []; // [['customer_code' => …, 'invoice_numbers' => […]]]
            foreach ($customersData as $customerData) {
                $invoiceNumbers = collect($customerData['invoices'] ?? [])
                    ->pluck('invoice_number')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                // No invoice numbers extracted → can't dedupe; process as normal.
                if (empty($invoiceNumbers)) {
                    $customersToProcess[] = $customerData;
                    continue;
                }

                $allDuplicates = true;
                foreach ($invoiceNumbers as $inv) {
                    if (! $this->invoiceNumberAlreadyExists($inv)) {
                        $allDuplicates = false;
                        break;
                    }
                }

                if ($allDuplicates) {
                    $skippedDuplicates[] = [
                        'customer_code'   => $customerData['customer_code'] ?? null,
                        'customer_name'   => $customerData['customer_name'] ?? null,
                        'invoice_numbers' => $invoiceNumbers,
                    ];
                    continue;
                }

                $customersToProcess[] = $customerData;
            }

            // If literally everything in this PDF is a duplicate, bail out clearly.
            if (empty($customersToProcess) && ! empty($skippedDuplicates)) {
                Log::info('PDF re-upload — all invoice numbers already exist', [
                    'filename'        => $originalFilename,
                    'file_hash'       => $fileHash,
                    'skipped_count'   => count($skippedDuplicates),
                    'sample_invoices' => array_slice(array_column($skippedDuplicates, 'invoice_numbers'), 0, 3),
                ]);

                $sampleNumbers = collect($skippedDuplicates)
                    ->pluck('invoice_numbers')
                    ->flatten()
                    ->take(5)
                    ->implode(', ');

                return redirect()->route('invoices.index')->with(
                    'warning',
                    'This PDF has already been extracted — all ' . count($skippedDuplicates)
                    . ' customer(s) and their invoice numbers (e.g. ' . $sampleNumbers
                    . '…) are already in the system. Nothing was imported.'
                );
            }

            $processedInvoices = [];

            // Process each customer's invoices
            foreach ($customersToProcess as $customerData) {
                $customerInvoice = $this->separateCustomerInvoices(
                    $file->getPathname(),
                    $customerData,
                    $originalFilename,
                    $request->notes,
                    $fileHash
                );

                if ($customerInvoice) {
                    $processedInvoices[] = $customerInvoice;
                }
            }

            if (count($processedInvoices) > 0) {
                ActivityLog::create([
                    'user_id'     => auth()->id(),
                    'user_name'   => auth()->user()->name,
                    'action'      => 'create',
                    'module'      => 'Invoices',
                    'description' => "Uploaded PDF and extracted " . count($processedInvoices) . " customer invoices from {$originalFilename}",
                    'ip_address' => request()->ip(),
                ]);
            }

            $successMsg = 'PDF processed successfully! ' . count($processedInvoices)
            . ' customer invoice(s) separated: '
            . implode(', ', array_column($processedInvoices, 'customer_code'));

            if (! empty($skippedDuplicates)) {
                $successMsg .= '. Skipped ' . count($skippedDuplicates)
                    . ' already-extracted customer(s).';
            }

            return redirect()->route('invoices.index')->with('success', $successMsg);

        } catch (\Exception $e) {
            Log::error('Invoice processing failed: ' . $e->getMessage(), [
                'file'  => $request->file('invoice_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to process PDF: ' . $e->getMessage()]);
        }
    }

    /**
     * True if `$inv` matches any existing Invoice's invoice_number field.
     * Handles the fact that invoices.invoice_number stores a comma-separated
     * list (one Invoice row can cover multiple Oracle invoices for the same
     * customer/page-range) — comma-boundary patterns avoid false positives
     * from substring matches like "1234" matching inside "12345".
     *
     * Failed Invoice rows are ignored so a failed upload doesn't block a re-try.
     */
    private function invoiceNumberAlreadyExists(string $inv): bool
    {
        $inv = trim($inv);
        if ($inv === '') {
            return false;
        }

        return Invoice::query()
            ->where('processing_status', '!=', 'failed')
            ->where(function ($q) use ($inv) {
                $q->where('invoice_number', $inv)
                    ->orWhere('invoice_number', 'LIKE', $inv . ',%')
                    ->orWhere('invoice_number', 'LIKE', '%,' . $inv . ',%')
                    ->orWhere('invoice_number', 'LIKE', '%, ' . $inv . ',%')
                    ->orWhere('invoice_number', 'LIKE', '%, ' . $inv)
                    ->orWhere('invoice_number', 'LIKE', '%,' . $inv);
            })
            ->exists();
    }

    /**
     * Extract customer information from PDF text.
     *
     * Public (not private) so console commands can reuse the exact same
     * parsing logic to re-verify already-stored invoices against their own
     * split PDF — e.g. app/Console/Commands/VerifyInvoiceCustomers.php.
     */
    public function extractCustomersFromPDF($pdfPath)
    {
        try {
            // Try different PDF text extraction methods
            $pdfPages = $this->extractPdfText($pdfPath);
            // if (empty($pdfText)) {
            //     Log::error('PDF text extraction failed - no text extracted from PDF');
            //     return [];
            // }

            Log::info('Raw extracted PDF text', ['pdf_text' => $pdfPages]);

            // Parse the extracted text to find customers
            $customersData = $this->parseCustomersFromPages($pdfPages);
            // if (empty($customersData)) {
            //     Log::error('PDF parsing failed - no customers found in extracted text');
            //     return [];
            // }

            Log::info('Extracted customers from PDF', [
                'customers_found' => count($customersData),
                'customers'       => array_map(function ($c) {
                    return [
                        'code'          => $c['customer_code'],
                        'name'          => $c['customer_name'],
                        'pages'         => $c['pages'],
                        'invoice_count' => count($c['invoices']),
                        'first_invoice' => $c['invoices'][0]['invoice_number'] ?? 'N/A',
                    ];
                }, $customersData),
            ]);

            return $customersData;

        } catch (\Exception $e) {
            Log::error('PDF processing failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract text from PDF using available tools
     */
    private function extractPdfText($pdfPath)
    {
        try {
            // Method 1: Try pdftotext
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            // 2>NUL / 2>/dev/null — "where"/"which" write their "not found"
            // message to stderr, which exec() doesn't capture into $output
            // and so otherwise leaks straight through to the terminal.
            $checkCmd  = $isWindows ? 'where pdftotext 2>NUL' : 'which pdftotext 2>/dev/null';
            exec($checkCmd, $output, $returnCode);

            if ($returnCode === 0) {
                $tempTextFile = sys_get_temp_dir() . '/' . uniqid('pdf_text_') . '.txt';
                // Use -layout to preserve horizontal spacing which helps with NAME CODE detection
                $command = sprintf('pdftotext -table %s %s', escapeshellarg($pdfPath), escapeshellarg($tempTextFile));
                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($tempTextFile)) {
                    $text = file_get_contents($tempTextFile);
                    unlink($tempTextFile);

// Split into individual pages
                    $pages = preg_split('/\f/', $text);

                    $pageData = [];

                    foreach ($pages as $index => $pageText) {
                        $pageData[] = [
                            'page' => $index + 1,
                            'text' => trim($pageText),
                        ];
                    }

                    return $pageData;
                }
            }

            // Method 2: Try using PHP PDF parser library
            // Must return the same [['page' => n, 'text' => ...], ...] shape
            // as Method 1 above — parseCustomersFromPages() always expects an
            // array of per-page entries, never a raw string.
            $parser = new Parser();
            $pdf    = $parser->parseFile($pdfPath);
            $pages  = $pdf->getPages();

            $pageData = [];
            foreach ($pages as $index => $page) {
                $pageData[] = [
                    'page' => $index + 1,
                    'text' => trim($page->getText()),
                ];
            }

            if (! empty($pageData)) {
                Log::info('PDF text extracted successfully using PdfParser', [
                    'pages_found' => count($pageData),
                ]);
                return $pageData;
            }

            return [];

        } catch (\Exception $e) {
            Log::error('PDF text extraction failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parser state machine constants
     */
    private const STATE_SEARCHING        = 'searching';
    private const STATE_COLLECTING_BLOCK = 'collecting_block';

    private function parseCustomersFromPages(array $pages): array
    {
        $customers = [];

        foreach ($pages as $pageData) {

            $pageNo = $pageData['page'];
            $text   = trim($pageData['text']);

            if (empty($text)) {
                continue;
            }

            // Parse customer details from this page
            $customer = $this->parseCustomerBlockFromPage($text);

            if (! $customer) {
                Log::warning('Customer not found', [
                    'page' => $pageNo,
                ]);
                continue;
            }

            $customerCode = $customer['customer_code'];
            $customerName = $customer['customer_name'];

            // Parse invoice information
            $invoiceNumber = $this->extractInvoiceNumber($text);
            $invoiceDate   = $this->extractBillingDate($text);
            $totalAmount   = $this->extractCurrentReceivable($text);

            if (! isset($customers[$customerCode])) {

                $customers[$customerCode] = [
                    'customer_code'  => $customerCode,
                    'customer_name'  => $customerName,
                    'customer_phone' => null,
                    'pages'          => [],
                    'invoices'       => [],
                ];
            }

            // Add page only once
            if (! in_array($pageNo, $customers[$customerCode]['pages'])) {
                $customers[$customerCode]['pages'][] = $pageNo;
            }

            // Add invoice
            $customers[$customerCode]['invoices'][] = [
                'invoice_number' => $invoiceNumber,
                'invoice_date'   => $invoiceDate,
                'total_amount'   => $totalAmount,
                'page'           => $pageNo,
            ];
        }

        return array_values($customers);
    }
    private function extractInvoiceNumber(string $text): ?string
    {
        if (preg_match('/Invoice\s+(\d{4,10})/i', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }
    private function extractBillingDate(string $text): ?string
    {
        if (preg_match('/Billing\s+Date\s+(\d{2}-[A-Z]{3}-\d{2})/i', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }
    private function extractCurrentReceivable(string $text): ?string
    {
        if (preg_match('/Current\s+Receivable\s*(-?[\d,]+(?:\.\d{2})?)/i', $text, $m)) {
            return str_replace(',', '', $m[1]);
        }

        return null;
    }
    private function parseCustomerBlockFromPage(string $text): ?array
    {
        if (preg_match('/Bill To:.*?\n\s*(.*?)\s+(\d{4,6})\b/si', $text, $matches)) {

            return [
                'customer_name' => trim($matches[1]),
                'customer_code' => trim($matches[2]),
            ];
        }

        return null;
    }
    /**
     * Parse customer data from extracted PDF text with state machine
     */

    /**
     * Internal helper to process a customer match during PDF parsing
     */

    /**
     * Extract phone number from PDF text around customer information
     */

    /**
     * Clean and format phone number
     */
    private function cleanPhoneNumber($phoneNumber)
    {
        // Remove extra spaces and common separators
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Validate minimum length
        if (strlen($cleaned) < 8) {
            return null;
        }

        // Format Pakistani numbers
        if (preg_match('/^92([0-9]{10})$/', $cleaned, $matches)) {
            return '+92' . $matches[1];
        } elseif (preg_match('/^03([0-9]{9})$/', $cleaned)) {
            return '+92' . $cleaned;
        } elseif (preg_match('/^021([0-9]{7,8})$/', $cleaned)) {
            return '+92' . $cleaned;
        } elseif (preg_match('/^\+92([0-9]{10,12})$/', $cleaned)) {
            return $cleaned;
        } elseif (preg_match('/^\+[0-9]{8,20}$/', $cleaned)) {
            return $cleaned;
        }

        // Return as-is if it looks like a valid international number
        if (strlen($cleaned) >= 8 && strlen($cleaned) <= 20) {
            return $phoneNumber; // Return original format with spaces/dashes
        }

        return null;
    }

    /**
     * Separate customer invoices into individual PDF files
     */
    private function separateCustomerInvoices($originalPdfPath, $customerData, $originalFilename, $notes, $fileHash = null)
    {
        try {
            $customerCode = $customerData['customer_code'];
            $customerName = $customerData['customer_name'];
            $pages        = $customerData['pages'];

            // Create customer folder path
            $customerFolderPath = 'invoices/customers/' . $customerCode;

            // Ensure customer folder exists
            if (! Storage::disk('local')->exists($customerFolderPath)) {
                Storage::disk('local')->makeDirectory($customerFolderPath);
            }

            // Generate unique filename for customer PDF
            $customerPdfName  = $customerCode . '_' . date('Y-m-d_H-i-s') . '_' . Str::random(6) . '.pdf';
            $customerPdfPath  = $customerFolderPath . '/' . $customerPdfName;
            $fullCustomerPath = storage_path('app/' . $customerPdfPath);

            // Try different PDF separation methods
            $separated = $this->extractPdfPages($originalPdfPath, $fullCustomerPath, $pages);

            if (! $separated) {
                Log::error('PDF separation failed critically', [
                    'customer_code' => $customerCode,
                    'pages'         => $pages,
                ]);
                throw new \Exception("Could not extract pages " . implode(',', $pages) . " for customer " . $customerCode);
            }

            // Calculate page range
            $pageRange = $this->formatPageRange($pages);

            // Resolve the customer's phone number.
            //   1. Prefer customers.contact_number from the local DB — that's
            //      the authoritative number the company actually messages.
            //   2. Fall back to the phone we scraped out of the PDF text only
            //      when the DB has nothing (PDF text often picks up the
            //      shipping agent's number by mistake).
            //   3. Normalize to canonical "+92XXXXXXXXXX" form so messaging /
            //      reporting code downstream doesn't have to think about
            //      "+92" vs "92" vs "0300" vs "0300-1234567".
            $resolvedRawPhone = $this->resolveCustomerPhone($customerCode) ?? ($customerData['customer_phone'] ?? null);
            $resolvedPhone    = $this->normalizePakistaniPhone($resolvedRawPhone);

            // Collect all invoice numbers, sum total amounts, and get invoice date range
            $sortedInvoices = collect($customerData['invoices'])->sortBy('page');
            $invoiceNumbers = $sortedInvoices->pluck('invoice_number')->unique()->filter()->values();
            $totalSum       = $sortedInvoices->sum('total_amount');

            $startDateStr = $sortedInvoices->pluck('invoice_date')->filter()->first();
            $endDateStr   = $sortedInvoices->pluck('invoice_date')->filter()->last();

            $invoiceDate = null;
            $startDate   = null;
            $endDate     = null;

            if ($startDateStr) {
                try {
                    $startDate   = \Carbon\Carbon::parse($startDateStr);
                    $invoiceDate = $startDate; // Keep invoice_date as the start date for backward compatibility
                } catch (\Exception $e) {
                    Log::warning('Failed to parse start date', ['date' => $startDateStr]);
                }
            }

            if ($endDateStr) {
                try {
                    $endDate = \Carbon\Carbon::parse($endDateStr);
                } catch (\Exception $e) {
                    Log::warning('Failed to parse end date', ['date' => $endDateStr]);
                }
            }

            $customer = Customer::where(
                'customer_number',
                $customerData['customer_code']
            )->first();

            $phone = $customer?->contact_number ?? null;
            // Create database record
            $invoice = Invoice::create([
                'original_filename' => $originalFilename,
                'source_file_hash'  => $fileHash,
                'customer_code'     => $customerCode,
                'customer_name'     => $customerName,
                'customer_phone'    => $phone,
                'invoice_number'    => $invoiceNumbers->isNotEmpty() ? $invoiceNumbers->implode(', ') : null,
                'invoice_date'      => $invoiceDate,
                'start_date'        => $startDate,
                'end_date'          => $endDate,
                'total_amount'      => $totalSum ?: null,
                'pdf_path'          => $customerPdfPath,
                'extracted_pages'   => $pages,
                'page_range'        => $pageRange,
                'processing_status' => 'completed',
                'uploaded_by'       => auth()->id(),
                'uploaded_at'       => now(),
                'notes'             => $notes,
            ]);

            // Auto-attach any builties that were uploaded ahead of this
            // invoice and linked to the same customer_code. Wrapped in a
            // try/catch so a failure here never blocks the invoice creation
            // itself — auto-attach is a convenience, not a critical path.
            try {
                $attached = app(\App\Http\Controllers\BuiltyController::class)
                    ->autoAttachToInvoice($invoice->fresh());
                if ($attached > 0) {
                    Log::info('Auto-attached pending builties to new invoice', [
                        'invoice_id'   => $invoice->id,
                        'customer'     => $customerCode,
                        'builty_count' => $attached,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Auto-attach builties failed (invoice still created)', [
                    'invoice_id' => $invoice->id,
                    'customer'   => $customerCode,
                    'error'      => $e->getMessage(),
                ]);
            }

            Log::info('Customer invoice separated successfully', [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'pages'         => $pages,
                'pdf_path'      => $customerPdfPath,
            ]);

            return [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'invoice_id'    => $invoice->id,
            ];

        } catch (\Exception $e) {
            Log::error('Customer invoice separation failed', [
                'customer_code' => $customerData['customer_code'],
                'error'         => $e->getMessage(),
            ]);

            // Create failed record — still try to resolve the phone from the
            // customers table so the operator can act on the failure without
            // hunting down the contact.
            $failedPhone = $this->normalizePakistaniPhone(
                $this->resolveCustomerPhone($customerData['customer_code']) ?? ($customerData['customer_phone'] ?? null)
            );
            Invoice::create([
                'original_filename' => $originalFilename,
                'source_file_hash'  => $fileHash,
                'customer_code'     => $customerData['customer_code'],
                'customer_name'     => $customerData['customer_name'],
                'customer_phone'    => $failedPhone,
                'pdf_path'          => '',
                'extracted_pages'   => $customerData['pages'],
                'processing_status' => 'failed',
                'uploaded_by'       => auth()->id(),
                'uploaded_at'       => now(),
                'notes'             => $notes . ' | Error: ' . $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract specific pages from PDF using available tools
     */
    private function extractPdfPages($inputPath, $outputPath, $pages)
    {
        try {
            // Method 1: Try using PHP PDF libraries (most reliable now)
            if ($this->tryPhpPdfExtraction($inputPath, $outputPath, $pages)) {
                Log::info('PDF pages extracted using PHP FPDI library', ['pages' => $pages]);
                return true;
            }

            // Method 2: Try pdftk (if available)
            if ($this->tryPdftk($inputPath, $outputPath, $pages)) {
                Log::info('PDF pages extracted using pdftk', ['pages' => $pages]);
                return true;
            }

            // Method 3: Try qpdf (if available)
            if ($this->tryQpdf($inputPath, $outputPath, $pages)) {
                Log::info('PDF pages extracted using qpdf', ['pages' => $pages]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('PDF page extraction failed: ' . $e->getMessage(), [
                'input'  => $inputPath,
                'output' => $outputPath,
                'pages'  => $pages,
            ]);
            return false;
        }
    }

    /**
     * Try extracting PDF pages using pdftk
     */
    private function tryPdftk($inputPath, $outputPath, $pages)
    {
        try {
            // Check if pdftk is available
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $checkCmd  = $isWindows ? 'where pdftk' : 'which pdftk 2>/dev/null';
            exec($checkCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                return false; // pdftk not available
            }

            // Build page range (e.g., "1-2 5-6")
            $pageRanges  = [];
            $sortedPages = $pages;
            sort($sortedPages);

            $start = $sortedPages[0];
            $end   = $sortedPages[0];

            for ($i = 1; $i < count($sortedPages); $i++) {
                if ($sortedPages[$i] == $end + 1) {
                    $end = $sortedPages[$i];
                } else {
                    $pageRanges[] = $start == $end ? $start : "$start-$end";
                    $start        = $end        = $sortedPages[$i];
                }
            }
            $pageRanges[] = $start == $end ? $start : "$start-$end";

            $pageRange = implode(' ', $pageRanges);

            // Execute pdftk command
            $command = sprintf(
                'pdftk %s cat %s output %s 2>&1',
                escapeshellarg($inputPath),
                $pageRange,
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                return true;
            }

            Log::warning('pdftk command failed', [
                'command'     => $command,
                'output'      => $output,
                'return_code' => $returnCode,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('pdftk extraction error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Try extracting PDF pages using PHP libraries
     */
    private function tryPhpPdfExtraction($inputPath, $outputPath, $pages)
    {
        try {
            $fpdi = new Fpdi();

            // Set margins to 0 to prevent shifting content which causes cropping
            $fpdi->SetMargins(0, 0, 0);
            $fpdi->SetAutoPageBreak(false);

            $pageCount = $fpdi->setSourceFile($inputPath);

            Log::info('FPDI PDF processing', [
                'input_path'       => $inputPath,
                'output_path'      => $outputPath,
                'total_pages'      => $pageCount,
                'pages_to_extract' => $pages,
            ]);

            // Extract only the specified pages
            foreach ($pages as $pageNum) {
                if ($pageNum <= $pageCount && $pageNum > 0) {
                    $tplId = $fpdi->importPage($pageNum);
                    $size  = $fpdi->getTemplateSize($tplId);

                    // Safeguard against missing size data
                    if ($size === false || ! isset($size['w']) || ! isset($size['h'])) {
                        Log::warning('Could not get page size for FPDI, using A4 default', ['page' => $pageNum]);
                        $size = ['w' => 210, 'h' => 297, 'orientation' => 'P'];
                    }

                    // Add page with same orientation and size as original
                    $orientation = $size['orientation'] ?? ($size['w'] > $size['h'] ? 'L' : 'P');
                    $fpdi->AddPage($orientation, [$size['w'], $size['h']]);

                    // Use template with explicit (0,0) position and full size to avoid cropping from right/bottom
                    $fpdi->useTemplate($tplId, 0, 0, $size['w'], $size['h'], true);

                    Log::info('Extracted page using FPDI', ['page' => $pageNum]);
                } else {
                    Log::warning('Page number out of range for FPDI', ['page' => $pageNum, 'total' => $pageCount]);
                }
            }

            // Save the extracted PDF
            $pdfContent = $fpdi->Output('', 'S');
            file_put_contents($outputPath, $pdfContent);

            $success = file_exists($outputPath) && filesize($outputPath) > 0;

            Log::info('FPDI extraction result', [
                'success'          => $success,
                'output_file_size' => $success ? filesize($outputPath) : 0,
            ]);

            return $success;

        } catch (\Exception $e) {
            Log::error('PHP PDF extraction error: ' . $e->getMessage(), [
                'input_path'  => $inputPath,
                'output_path' => $outputPath,
                'pages'       => $pages,
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Try extracting PDF pages using qpdf
     */
    private function tryQpdf($inputPath, $outputPath, $pages)
    {
        try {
            // Check if qpdf is available
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $checkCmd  = $isWindows ? 'where qpdf' : 'which qpdf 2>/dev/null';
            exec($checkCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                return false; // qpdf not available
            }

            // Build page list (e.g., "1,2,5,6")
            $pageList = implode(',', $pages);

            // Execute qpdf command
            $command = sprintf(
                'qpdf %s --pages . %s -- %s 2>&1',
                escapeshellarg($inputPath),
                $pageList,
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('qpdf extraction error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format page numbers into readable range
     */
    private function formatPageRange($pages)
    {
        if (empty($pages)) {
            return '';
        }

        sort($pages);
        $ranges = [];
        $start  = $pages[0];
        $end    = $pages[0];

        for ($i = 1; $i < count($pages); $i++) {
            if ($pages[$i] == $end + 1) {
                $end = $pages[$i];
            } else {
                $ranges[] = $start == $end ? $start : "$start-$end";
                $start    = $end    = $pages[$i];
            }
        }

        $ranges[] = $start == $end ? $start : "$start-$end";

        return implode(', ', $ranges);
    }

    /**
     * Download customer invoice PDF
     */
    public function download($id)
    {
        $invoice = Invoice::findOrFail($id);

        if (! Storage::disk('local')->exists($invoice->pdf_path)) {
            return back()->withErrors(['error' => 'Invoice file not found.']);
        }

        // Sanitize customer name for filename (remove invalid characters)
        $sanitizedCustomerName = $this->sanitizeFilename($invoice->customer_name);
        $filename              = $invoice->customer_code . '_' . $sanitizedCustomerName . '.pdf';

        return Storage::disk('local')->download($invoice->pdf_path, $filename);
    }

    /**
     * Preview raw file from disk (Disk Explorer)
     */
    public function previewDiskFile(Request $request)
    {
        $path = $request->query('path');

        // Security check: ensure path is within invoices/customers
        if (! $path || ! Str::startsWith($path, 'invoices/customers/') || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found or access denied.');
        }

        return Storage::disk('local')->response($path);
    }

    /**
     * Show individual invoice details
     */
    public function show($id)
    {
        $invoice = Invoice::with('uploader')->findOrFail($id);
        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Sanitize filename by removing invalid characters
     */
    private function sanitizeFilename($filename)
    {
        // Remove invalid filename characters: / \ : * ? " < > |
        $sanitized = preg_replace('/[\/\\:*?"<>|]/', '', $filename);

        // Replace multiple spaces with single space and then with underscores
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        $sanitized = str_replace(' ', '_', $sanitized);

        // Remove leading/trailing underscores and limit length
        $sanitized = trim($sanitized, '_');
        $sanitized = substr($sanitized, 0, 100); // Limit to 100 characters

        // Ensure we have a valid filename
        return empty($sanitized) ? 'invoice' : $sanitized;
    }

    /**
     * Show customer invoices
     */
    public function showCustomer($customerCode)
    {
        $invoices = Invoice::byCustomer($customerCode)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.invoices.customer', compact('invoices', 'customerCode'));
    }

    /**
     * Delete invoice
     */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);

        // Delete PDF file if exists
        if (Storage::disk('local')->exists($invoice->pdf_path)) {
            Storage::disk('local')->delete($invoice->pdf_path);
        }

        ActivityLog::create([
            'user_id'     => auth()->id(),
            'user_name'   => auth()->user()->name,
            'action'      => 'delete',
            'module'      => 'Invoices',
            'description' => "Deleted invoice for " . $invoice->customer_name,
            'ip_address'  => request()->ip(),
        ]);

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Update customer phone number for invoice
     */
    public function updatePhone(Request $request, $id)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        try {
            $invoice = Invoice::findOrFail($id);

            // Clean and validate phone number
            $phone = $this->cleanPhoneNumber($request->phone);

            if (! $phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format',
                ], 400);
            }

            // Update the invoice
            $invoice->update(['customer_phone' => $phone]);

            Log::info('Customer phone number updated via WhatsApp', [
                'invoice_id'    => $invoice->id,
                'customer_code' => $invoice->customer_code,
                'customer_name' => $invoice->customer_name,
                'phone'         => $phone,
                'updated_by'    => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone number updated successfully',
                'phone'   => $phone,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update customer phone number', [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
                'phone'      => $request->phone,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update phone number',
            ], 500);
        }
    }

    /**
     * Send invoice via WhatsApp (Queued)
     */
    public function sendWhatsApp(Request $request, $id)
    {
        $request->validate([
            'phone'    => 'nullable|string|max:20',
            'template' => 'nullable|string|max:50',
        ]);

        try {
            $invoice = Invoice::findOrFail($id);

            // Check if invoice is ready to send
            if ($invoice->processing_status !== 'completed' || ! $invoice->pdf_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is not ready for sending',
                ], 400);
            }

            // Phone number source priority:
            //   1. Explicit phone in request (manual override)
            //   2. customers.contact_number matched by invoice.customer_code
            //   3. invoice.customer_phone (legacy fallback)
            $phone = $request->phone ?? $this->resolveCustomerPhone($invoice->customer_code) ?? $invoice->customer_phone;

            if (! $phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is required (no contact_number on customer record).',
                ], 400);
            }

            // Clean number
            $phone = (new \App\Services\WhatsAppService())->formatPhoneNumber($phone);

            if (! $phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format',
                ], 400);
            }

            // Persist resolved/overridden phone on the invoice for history
            if ($phone !== $invoice->customer_phone) {
                $invoice->update(['customer_phone' => $phone]);
            }

            // Always use Urdu template
            $template = 'invoice_urdu';
            $url      = route('invoices.download', $invoice->id);
            \App\Jobs\SendWhatsAppInvoiceJob::dispatch($invoice, $phone, $url, $template);

            $invoice->update([
                'whatsapp_status' => 'pending',
                'whatsapp_error'  => null,
            ]);

            Log::info('WhatsApp invoice job dispatched', [
                'invoice_id'    => $invoice->id,
                'customer_code' => $invoice->customer_code,
                'phone'         => $phone,
            ]);

            ActivityLog::create([
                'user_id'     => auth()->id(),
                'user_name'   => auth()->user()->name,
                'action'      => 'update',
                'module'      => 'Invoices',
                'description' => "Queued WhatsApp invoice for {$invoice->customer_name} ({$invoice->customer_code})",
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice queued for sending via WhatsApp!',
                'data'    => [
                    'status' => 'pending',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('WhatsApp send invoice error', [
                'invoice_id' => $id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while queuing the invoice',
            ], 500);
        }
    }

    /**
     * Bulk send WhatsApp invoices
     */
    public function bulkSendWhatsApp(Request $request)
    {
        try {
            // Always use Urdu template
            $template = 'invoice_urdu';

            // Optional: scope to specific invoice IDs (used by the per-day
            // accordion "Send" button on the index page). When the caller
            // omits invoice_ids, falls back to the original "send everything
            // unsent" behavior.
            $scopedIds = $request->input('invoice_ids');
            if (is_array($scopedIds)) {
                $scopedIds = array_values(array_filter(array_map('intval', $scopedIds)));
            }

            $query = Invoice::where('processing_status', 'completed')
                ->whereNotNull('pdf_path')
                ->where(function ($query) {
                    $query->whereNull('whatsapp_status')
                        ->orWhere('whatsapp_status', '!=', 'sent');
                });

            if (! empty($scopedIds)) {
                $query->whereIn('id', $scopedIds);
            }

            $unsentInvoices = $query->get();

            if ($unsentInvoices->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No unsent invoices found',
                ]);
            }

            $whatsappService = new \App\Services\WhatsAppService();
            $skipped         = 0;

            foreach ($unsentInvoices as $invoice) {
                // Customer phone first, then legacy invoice.customer_phone
                $rawPhone = $this->resolveCustomerPhone($invoice->customer_code) ?? $invoice->customer_phone;

                $phone = $whatsappService->formatPhoneNumber($rawPhone);
                if (! $phone) {
                    $skipped++;
                    continue;
                }

                // Persist the resolved phone for traceability
                if ($phone !== $invoice->customer_phone) {
                    $invoice->update(['customer_phone' => $phone]);
                }

                $url = route('invoices.download', $invoice->id);
                \App\Jobs\SendWhatsAppInvoiceJob::dispatch($invoice, $phone, $url, $template);

                $invoice->update([
                    'whatsapp_status' => 'pending',
                    'whatsapp_error'  => null,
                ]);
            }

            Log::info('Bulk WhatsApp invoice jobs dispatched', [
                'count' => $unsentInvoices->count(),
            ]);

            ActivityLog::create([
                'user_id'     => auth()->id(),
                'user_name'   => auth()->user()->name,
                'action'      => 'update',
                'module'      => 'Invoices',
                'description' => "Bulk queued WhatsApp invoices for " . $unsentInvoices->count() . " customers",
                'ip_address'  => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk sending initiated! ' . $unsentInvoices->count() . ' invoices queued.',
                'count'   => $unsentInvoices->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk WhatsApp send error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate bulk send: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time status of WhatsApp sending queue
     */
    public function getQueueStatus()
    {
        try {
            $stats = Invoice::selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN whatsapp_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN whatsapp_status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN whatsapp_status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN whatsapp_status = 'failed' THEN 1 ELSE 0 END) as failed
                ")
                ->where('processing_status', 'completed')
                ->whereNotNull('pdf_path')
                ->first();

            // Also get failed ones with their customer names/error
            $recentFailures = Invoice::where('whatsapp_status', 'failed')
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->select('id', 'customer_name', 'whatsapp_error')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success'         => true,
                'stats'           => $stats,
                'recent_failures' => $recentFailures,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch status',
            ], 500);
        }
    }

    /**
     * Get IDs of invoices that have not been sent via WhatsApp
     */
    public function getUnsentInvoices()
    {
        try {
            $unsentInvoices = Invoice::where('processing_status', 'completed')
                ->whereNotNull('pdf_path')
                ->whereNotNull('customer_phone')
                ->where(function ($query) {
                    $query->whereNull('whatsapp_status')
                        ->orWhere('whatsapp_status', '!=', 'sent');
                })
                ->select('id', 'customer_name', 'customer_phone')
                ->get();

            return response()->json([
                'success'  => true,
                'invoices' => $unsentInvoices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unsent invoices',
            ], 500);
        }
    }

    /**
     * Validate if a customer name is legitimate
     */
    private function isValidCustomerName($customerName, $customerCode)
    {
        Log::debug('isValidCustomerName: checking customer', ['name' => $customerName, 'code' => $customerCode]);

        // List of invalid customer names as specified by the user
        $invalidNames = [
            'receivable',
            'current',
            'sub total',
            'grand total',
            'total',
            'discount',
            'amount',
            'balance',
            'invoice',
            'billing',
            'tax',
            'price',
            'rate',
            'qty',
            'tr#',
            'ship agent',
            'order',
            'comments',
            'inv',
            'bill',
            'payment',
            'subtotal',
            'charges',
            'date',
            'number',
            'no',
            'code',
            'id',
            'reference',
            'ref',
            'goods',
            'transport',
            'logistics',
            'cargo',
            'courier',
            'shipment',
            'shipping',
            'estate',
            'town',
            'kot lakhpat',
            'quaid-e-azam',
            'block',
            'phase',
            'sector',
            'street',
            'road',
            'floor',
            'plot',
            'market',
        ];

        $nameLower = strtolower(trim($customerName));

        // Check if name is or contains any invalid term
        foreach ($invalidNames as $invalid) {
            // Check as whole word or part of the string
            if (preg_match('/\b' . preg_quote($invalid, '/') . '\b/i', $nameLower) || strpos($nameLower, $invalid) !== false) {
                Log::warning('isValidCustomerName: rejected (contains invalid term)', ['name' => $customerName, 'invalid_term' => $invalid]);
                return false;
            }
        }

        // Check if name is too short (less than 3 characters)
        if (strlen($customerName) < 3) {
            Log::warning('isValidCustomerName: rejected (too short)', ['name' => $customerName, 'length' => strlen($customerName)]);
            return false;
        }

        // Check if name is a block/phase address like "M, Quaid-e-Azam..."
        if (preg_match('/^[A-Za-z]\s*,/', $customerName)) {
            Log::warning('isValidCustomerName: rejected (matching plot/block format)', ['name' => $customerName]);
            return false;
        }

        // Check if name is just numbers or special characters
        if (! preg_match('/[A-Za-z]/', $customerName)) {
            Log::warning('isValidCustomerName: rejected (no letters)', ['name' => $customerName]);
            return false;
        }

        // Check if customer code is exactly 4-6 digits as specified by user
        if (! preg_match('/^\d{4,6}$/', $customerCode)) {
            Log::warning('isValidCustomerName: rejected (invalid code format)', ['code' => $customerCode]);
            return false;
        }

        Log::debug('isValidCustomerName: accepted', ['name' => $customerName, 'code' => $customerCode]);
        return true;
    }

    /**
     * Look up the customer's contact_number from the customers table.
     * Matches the invoice's customer_code against either Customer.customer_number
     * or Customer.customer_id (since invoices may store either form).
     *
     * Returns null when no customer is found or contact_number is empty.
     */
    private function resolveCustomerPhone(?string $customerCode): ?string
    {
        if (! $customerCode) {
            return null;
        }

        $phone = Customer::where('customer_number', $customerCode)
            ->orWhere('customer_id', $customerCode)
            ->value('contact_number');

        return $phone ?: null;
    }

    /**
     * Normalize Pakistani phone numbers to canonical "+92XXXXXXXXXX" form.
     *
     * Accepts every common shape the customers.contact_number column has
     * been seen in:
     *   "+923001234567", "+92 300 1234567",
     *   "923001234567",
     *   "03001234567",   "0300-1234567",   "0300 1234567",
     *   "021 1234567",   "042-12345678",
     *   "3001234567"   (bare local mobile without leading 0)
     *
     * Returns null if the input doesn't look like a phone number at all.
     *
     * Note: this fixes a long-standing bug in cleanPhoneNumber() which left
     * the leading 0 in place when prefixing 92, producing "+9203001234567"
     * (13 digits — invalid). Here we strip it: "03001234567" → "+923001234567".
     */
    private function normalizePakistaniPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '' || strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }

        // Already has 92 country code (handles "+923001234567" and "923001234567").
        if (str_starts_with($digits, '92')) {
            return '+' . $digits;
        }

        // Local form with a leading 0 — strip it and prefix 92.
        //   "03001234567"  → "+923001234567"
        //   "0211234567"   → "+92211234567"
        //   "04212345678"  → "+924212345678"
        if (str_starts_with($digits, '0')) {
            return '+92' . substr($digits, 1);
        }

        // Bare 10-digit mobile (typed without the leading 0).
        if (preg_match('/^3\d{9}$/', $digits)) {
            return '+92' . $digits;
        }

        // Otherwise treat as an international number from another country.
        return '+' . $digits;
    }
}
