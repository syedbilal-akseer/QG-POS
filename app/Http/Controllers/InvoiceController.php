<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader\StreamReader;
use Smalot\PdfParser\Parser;
use Maatwebsite\Excel\Facades\Excel; // Added Import
use App\Models\ActivityLog;

class InvoiceController extends Controller
{
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

    private function renderListing(Request $request, bool $viewOnly)
    {
        $diskFiles = [];

        // Date range + status filters are opt-in. No default constraint; the page
        // shows every upload until the user picks a From/To or uses the "Today"
        // quick-button in the filter bar.
        $filterFrom     = $request->input('from')     ?: null;
        $filterTo       = $request->input('to')       ?: null;
        $filterStatus   = $request->input('status')   ?: null;   // completed | processing | failed | pending
        $filterWhatsapp = $request->input('whatsapp') ?: null;   // sent | failed | pending
        $filterCustomer = $request->input('customer') ?: null;   // exact customer_code

        // Shortcut: when the "Unsent / Pending" quick filter is on, force the
        // WhatsApp filter to "pending". Lets the link in the header drop the
        // user into the unsent-only view without them touching the filter UI.
        if ($request->boolean('unsent_only')) {
            $filterWhatsapp = 'pending';
        }
        // Build the base filter once and clone it for each downstream query so
        // search/date filters apply consistently to stats, the date pagination,
        // and the actual invoice load.
        $baseFilter = function ($q) use ($filterFrom, $filterTo, $filterStatus, $filterWhatsapp, $filterCustomer, $request) {
              $admins = [
                    'mahmood@quadri-group.com',
                ];

                $user = auth()->user();

                if (in_array($user->email, $admins) || $user->isAdmin()) {
                    // no restriction
                } elseif ($user->isCmd()) {
                    // CMD-KHI / CMD-LHR see invoices uploaded by their assigned
                    // salespeople only; null (= "All") means no restriction.
                    $assignedSalespeopleIds = $user->getAssignedSalespeopleIds();
                    if (!empty($assignedSalespeopleIds)) {
                        $q->whereIn('uploaded_by', $assignedSalespeopleIds);
                    }
                } else {
                    // Other users can only see their own uploaded invoices
                    $q->where('uploaded_by', auth()->id());
                }


            if ($filterFrom) {
                $q->whereDate('uploaded_at', '>=', $filterFrom);
            }
            if ($filterTo) {
                $q->whereDate('uploaded_at', '<=', $filterTo);
            }
            if ($filterCustomer) {
                $q->where('customer_code', $filterCustomer);
            }
            if ($filterStatus) {
                if ($filterStatus === 'pending') {
                    $q->where(function ($inner) {
                        $inner->whereNull('processing_status')->orWhere('processing_status', '');
                    });
                } else {
                    $q->where('processing_status', $filterStatus);
                }
            }
            if ($filterWhatsapp) {
                if ($filterWhatsapp === 'pending') {
                    $q->where(function ($inner) {
                        $inner->whereNull('whatsapp_status')->orWhere('whatsapp_status', '');
                    });
                } else {
                    $q->where('whatsapp_status', $filterWhatsapp);
                }
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $q->where(function ($inner) use ($search) {
                    $inner->where('customer_code', 'like', "%{$search}%")
                          ->orWhere('customer_name', 'like', "%{$search}%")
                          ->orWhere('customer_phone', 'like', "%{$search}%")
                          ->orWhere('original_filename', 'like', "%{$search}%")
                          ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            }
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
            $invoicesPage = Invoice::query()
            ->select([
                'id',
                'customer_name',
                'customer_code',
                'customer_phone',
                'invoice_number',
                'total_amount',
                'processing_status',
                'whatsapp_status',
                'whatsapp_sent_at',
                'uploaded_at',
                'uploaded_by',
                'pdf_path',
                'original_filename',
            ])
            ->tap($baseFilter)
            ->with('uploader:id,name')
            ->latest('uploaded_at')
            ->paginate(10)
            ->withQueryString();

            // Distinct customers seen across the invoices table — populates the
            // Customer filter dropdown. Pulled here (not from the customers
            // table) so the list only contains customers who actually have
            // invoices uploaded.
            $customerOptions = Invoice::query()
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

        $invoices = Invoice::query()
            ->tap($baseFilter)
            ->with('uploader')
            ->when(!empty($visibleDates),
                fn ($q) => $q->whereIn(DB::raw('DATE(uploaded_at)'), $visibleDates),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->orderBy('uploaded_at', 'desc')
            ->get();

        $whatsappService = new \App\Services\WhatsAppService();
        $templatesResult = $whatsappService->getAvailableTemplates();
        $whatsappTemplates = $templatesResult['success'] ? $templatesResult['templates'] : [];

        $whatsappTemplates = array_filter($whatsappTemplates, function($t) {
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
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

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
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $file = $request->file('invoice_file');
            $originalFilename = $file->getClientOriginalName();
            $fileHash = hash_file('sha256', $file->getPathname());

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
            $customersToProcess     = [];
            $skippedDuplicates      = []; // [['customer_code' => …, 'invoice_numbers' => […]]]
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
                    if (!$this->invoiceNumberAlreadyExists($inv)) {
                        $allDuplicates = false;
                        break;
                    }
                }

                if ($allDuplicates) {
                    $skippedDuplicates[] = [
                        'customer_code'    => $customerData['customer_code'] ?? null,
                        'customer_name'    => $customerData['customer_name'] ?? null,
                        'invoice_numbers'  => $invoiceNumbers,
                    ];
                    continue;
                }

                $customersToProcess[] = $customerData;
            }

            // If literally everything in this PDF is a duplicate, bail out clearly.
            if (empty($customersToProcess) && !empty($skippedDuplicates)) {
                Log::info('PDF re-upload — all invoice numbers already exist', [
                    'filename'         => $originalFilename,
                    'file_hash'        => $fileHash,
                    'skipped_count'    => count($skippedDuplicates),
                    'sample_invoices'  => array_slice(array_column($skippedDuplicates, 'invoice_numbers'), 0, 3),
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
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name,
                    'action' => 'create',
                    'module' => 'Invoices',
                    'description' => "Uploaded PDF and extracted " . count($processedInvoices) . " customer invoices from {$originalFilename}",
                    'ip_address' => request()->ip(),
                ]);
            }

            $successMsg = 'PDF processed successfully! ' . count($processedInvoices)
                . ' customer invoice(s) separated: '
                . implode(', ', array_column($processedInvoices, 'customer_code'));

            if (!empty($skippedDuplicates)) {
                $successMsg .= '. Skipped ' . count($skippedDuplicates)
                    . ' already-extracted customer(s).';
            }

            return redirect()->route('invoices.index')->with('success', $successMsg);

        } catch (\Exception $e) {
            Log::error('Invoice processing failed: ' . $e->getMessage(), [
                'file' => $request->file('invoice_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
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
     * Extract customer information from PDF text
     */
    private function extractCustomersFromPDF($pdfPath)
    {
        try {
            // Try different PDF text extraction methods
            $pdfText = $this->extractPdfText($pdfPath);

            if (empty($pdfText)) {
                Log::error('PDF text extraction failed - no text extracted from PDF');
                return [];
            }

            Log::info('Raw extracted PDF text', ['pdf_text' => $pdfText]);

            // Parse the extracted text to find customers
            $customersData = $this->parseCustomersFromText($pdfText);

            if (empty($customersData)) {
                Log::error('PDF parsing failed - no customers found in extracted text');
                return [];
            }

            Log::info('Extracted customers from PDF', [
                'customers_found' => count($customersData),
                'customers' => array_map(function ($c) {
                    return [
                        'code' => $c['customer_code'],
                        'name' => $c['customer_name'],
                        'pages' => $c['pages'],
                        'invoice_count' => count($c['invoices']),
                        'first_invoice' => $c['invoices'][0]['invoice_number'] ?? 'N/A'
                    ];
                }, $customersData)
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
            $checkCmd = $isWindows ? 'where pdftotext' : 'which pdftotext 2>/dev/null';
            exec($checkCmd, $output, $returnCode);

            if ($returnCode === 0) {
                $tempTextFile = sys_get_temp_dir() . '/' . uniqid('pdf_text_') . '.txt';
                // Use -layout to preserve horizontal spacing which helps with NAME CODE detection
                $command = sprintf('pdftotext -layout %s %s', escapeshellarg($pdfPath), escapeshellarg($tempTextFile));
                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($tempTextFile)) {
                    $text = file_get_contents($tempTextFile);
                    unlink($tempTextFile);
                    return $text;
                }
            }

            // Method 2: Try using PHP PDF parser library
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $pages = $pdf->getPages();
            $text = '';

            foreach ($pages as $page) {
                // Get text from this specific page and add a form feed on its own line
                $text .= $page->getText() . "\n\f\n";
            }

            if (!empty(trim($text))) {
                Log::info('PDF text extracted successfully using PdfParser with isolated page breaks', [
                    'pages_found' => count($pages)
                ]);
                return $text;
            }

            return '';

        } catch (\Exception $e) {
            Log::error('PDF text extraction failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Parser state machine constants
     */
    private const STATE_SEARCHING = 'searching';
    private const STATE_COLLECTING_BLOCK = 'collecting_block';

    /**
     * Parse customer data from extracted PDF text with state machine
     */
    private function parseCustomersFromText($text)
    {
        $text = str_replace(["\xC2\xA0", "\xA0"], " ", $text); // Replace all non-breaking spaces in one go
        $customersData = [];
        $lines = explode("\n", $text);
        $currentCustomerIndex = null;
        $pageNumber = 1;
        $pageOwners = [];
        $pageHasPrimary = [];
        $pendingInvoicesByPage = [];
        $pendingAmountsByPage = [];
        $pendingDatesByPage = [];
        $state = self::STATE_SEARCHING; // Initialize state machine
        $currentCustomerBlockLines = []; // Collect lines between Salesperson and Bill To/Ship To/Invoice Type
        $currentCustomerBlockPage = null; // Track which page the customer block starts on
        $pageNumberRegex = '/Page\s+\d+\s+of\s+\d+/i'; // Precompile regex for performance

        ini_set('memory_limit', '512M');

        Log::info('Starting PDF parse with state machine and block collection', [
            'text_length' => strlen($text)
        ]);

        foreach ($lines as $lineNum => $line) {
            $originalLine = $line;
            $line = trim($line);

            Log::debug('Processing line', [
                'line_num' => $lineNum,
                'original_line' => $originalLine,
                'trimmed_line' => $line,
                'page_number' => $pageNumber,
                'current_state' => $state
            ]);

            // Check for page break first
            $hasPageBreak = strpos($originalLine, "\f") !== false;
            if ($hasPageBreak) {
                $pageNumber++;
                $line = trim(str_replace("\f", "", $line));
            }

            if (empty($line)) {
                continue;
            }

            // Skip page number lines
            if (preg_match($pageNumberRegex, $line)) {
                continue;
            }

            // Process invoice/date/amount detection for every line (unchanged behavior)
            $this->processInvoiceDetection($line, $pageNumber, $pendingInvoicesByPage);
            $this->processDateDetection($line, $pageNumber, $pendingDatesByPage);
            $this->processAmountDetection($line, $pageNumber, $pendingAmountsByPage);

            // Manage state machine and block collection
            $lineLower = strtolower($line);

            // If we just had a page break, reset state
            if ($hasPageBreak) {
                $state = self::STATE_SEARCHING;
                $currentCustomerBlockLines = [];
                $currentCustomerBlockPage = null;
            }

            if ($state === self::STATE_SEARCHING) {
                // Look for start of customer block (salesperson, bill to, or ship to
                if (
                    strpos($lineLower, 'salesperson') !== false ||
                    strpos($lineLower, 'bill to') !== false ||
                    strpos($lineLower, 'ship to') !== false
                ) {
                    Log::info('parseCustomersFromText: found start of customer block', [
                        'line' => $line,
                        'page' => $pageNumber
                    ]);
                    $state = self::STATE_COLLECTING_BLOCK;
                    $currentCustomerBlockPage = $pageNumber;
                    $currentCustomerBlockLines = [$line];
                }
            } else if ($state === self::STATE_COLLECTING_BLOCK) {
                // Check for end of customer block
                if (
                    strpos($lineLower, 'bill to') !== false ||
                    strpos($lineLower, 'ship to') !== false ||
                    strpos($lineLower, 'invoice type') !== false ||
                    strpos($lineLower, 'sno.') !== false ||
                    strpos($lineLower, 'date') !== false ||
                    strpos($lineLower, 'sub total') !== false ||
                    strpos($lineLower, 'shipping charges') !== false
                ) {
                    Log::info('parseCustomersFromText: found end of customer block', [
                        'end_marker' => $line,
                        'current_block_lines' => $currentCustomerBlockLines
                    ]);
                    // Parse the collected block
                    $this->parseCustomerBlock(
                        $customersData,
                        $currentCustomerBlockLines,
                        $currentCustomerBlockPage,
                        $lineNum,
                        $lines,
                        $currentCustomerIndex,
                        $pageOwners,
                        $pageHasPrimary
                    );
                    // Reset state and block
                    $state = self::STATE_SEARCHING;
                    $currentCustomerBlockLines = [];
                    $currentCustomerBlockPage = null;
                } else {
                    // Add line to current block
                    $currentCustomerBlockLines[] = $line;
                }
            }

            // Assign page to current customer if not already assigned
            if ($currentCustomerIndex !== null && !isset($pageOwners[$pageNumber])) {
                if (!in_array($pageNumber, $customersData[$currentCustomerIndex]['pages'])) {
                    $customersData[$currentCustomerIndex]['pages'][] = $pageNumber;
                }
                $pageOwners[$pageNumber] = $customersData[$currentCustomerIndex]['customer_code'];
            }
        }

        // ── Orphan page rescue ──
        // Any page that produced invoice numbers but never got a customer owner
        // (no `Bill To` line, no prior carry-over) would otherwise be silently
        // dropped — its invoice numbers disappear. Walk every page that has
        // pending invoices and, if it has no owner, fall back to the nearest
        // earlier page's owner (continuation-page heuristic). Only as a last
        // resort do we drop the page.
        $allPagesWithInvoices = array_keys($pendingInvoicesByPage);
        sort($allPagesWithInvoices);
        foreach ($allPagesWithInvoices as $pgNum) {
            if (isset($pageOwners[$pgNum])) continue;

            // Find the nearest prior page that DOES have an owner.
            $fallbackOwner = null;
            for ($p = $pgNum - 1; $p >= 1; $p--) {
                if (isset($pageOwners[$p])) {
                    $fallbackOwner = $pageOwners[$p];
                    break;
                }
            }

            if ($fallbackOwner) {
                $pageOwners[$pgNum] = $fallbackOwner;
                Log::info('Orphan page attributed to prior customer', [
                    'page'           => $pgNum,
                    'fallback_owner' => $fallbackOwner,
                    'invoice_count'  => count($pendingInvoicesByPage[$pgNum] ?? []),
                ]);
                // Also record the page on that customer's pages[] so the PDF
                // separator extracts it into their per-customer PDF.
                foreach ($customersData as &$c) {
                    if ($c['customer_code'] === $fallbackOwner
                        && !in_array($pgNum, $c['pages'])) {
                        $c['pages'][] = $pgNum;
                    }
                }
                unset($c);
            } else {
                Log::warning('Page has invoices but no detectable owner — dropped', [
                    'page'             => $pgNum,
                    'invoice_numbers'  => $pendingInvoicesByPage[$pgNum] ?? [],
                ]);
            }
        }

        // ── Cross-customer dedup ──
        // Track which invoice numbers have already been claimed across ALL
        // customers in this PDF. Stops the same invoice_number from being
        // attached to two different customer records (double-counting).
        $claimedInvoiceNumbers = [];

        foreach ($pageOwners as $pgNum => $ownerCode) {
            $invoices = $pendingInvoicesByPage[$pgNum] ?? [];
            $amounts  = $pendingAmountsByPage[$pgNum]  ?? [];
            $dates    = $pendingDatesByPage[$pgNum]    ?? [];
            if (empty($invoices)) continue;

            foreach ($customersData as &$customer) {
                if ($customer['customer_code'] !== $ownerCode) continue;

                foreach ($invoices as $index => $invNum) {
                    // Skip if THIS customer already has it (intra-customer dedup).
                    $existsForThisCustomer = false;
                    foreach ($customer['invoices'] as $existing) {
                        if ($existing['invoice_number'] === $invNum) {
                            $existsForThisCustomer = true;
                            break;
                        }
                    }
                    if ($existsForThisCustomer) continue;

                    // Skip + warn if ANOTHER customer already claimed it (cross-customer dedup).
                    if (isset($claimedInvoiceNumbers[$invNum])) {
                        Log::warning('Invoice number appears under multiple customers — keeping first', [
                            'invoice_number'   => $invNum,
                            'already_owned_by' => $claimedInvoiceNumbers[$invNum],
                            'rejected_owner'   => $ownerCode,
                            'page'             => $pgNum,
                        ]);
                        continue;
                    }

                    $amount = $amounts[$index] ?? (end($amounts) ?: null);
                    $date   = $dates[$index]   ?? (end($dates)   ?: null);
                    $customer['invoices'][] = [
                        'invoice_number' => $invNum,
                        'total_amount'   => $amount,
                        'invoice_date'   => $date,
                        'page'           => $pgNum,
                    ];
                    $claimedInvoiceNumbers[$invNum] = $ownerCode;
                }
            }
        }
        unset($customer);

        // Final tally — useful for diagnosing parse problems against a real PDF.
        Log::info('PDF parse complete', [
            'customers'          => count($customersData),
            'invoices_attached'  => count($claimedInvoiceNumbers),
            'pages_with_owner'   => count($pageOwners),
            'pages_with_pending' => count($pendingInvoicesByPage),
        ]);

        $customersData = array_filter($customersData, function($c) { return !empty($c['pages']); });
        return array_values($customersData);
    }

    /**
     * Process invoice number detection from a line
     */
    private function processInvoiceDetection($line, $pageNumber, &$pendingInvoicesByPage)
    {
        $invoicePatterns = [
            '/(?:Invoice|Inv\.?|Bill|Receipt|Order|No\.?|Number)\s*[:#\t\s]*(\d{4,10})/i',
            '/#\s*(\d{4,10})/',
        ];

        foreach ($invoicePatterns as $pattern) {
            if (preg_match_all($pattern, $line, $matches)) {
                foreach ($matches[1] as $invNum) {
                    if (!in_array($invNum, $pendingInvoicesByPage[$pageNumber] ?? [])) {
                        $pendingInvoicesByPage[$pageNumber][] = $invNum;
                    }
                }
            }
        }
    }

    /**
     * Process date detection from a line
     */
    private function processDateDetection($line, $pageNumber, &$pendingDatesByPage)
    {
        $datePatterns = [
            '/(?:Billing\s+Date|Invoice\s+Date|Invoice|Date)\s*[:\s]*(\d{1,2}[-\/\.\s](?:[a-z]{3}|\d{1,2})[-\/\.\s]\d{2,4})/i',
        ];

        foreach ($datePatterns as $datePattern) {
            if (preg_match($datePattern, $line, $dateMatches)) {
                $dateStr = $dateMatches[1];
                if (!in_array($dateStr, $pendingDatesByPage[$pageNumber] ?? [])) {
                    $pendingDatesByPage[$pageNumber][] = $dateStr;
                }
                break;
            }
        }
    }

    /**
     * Process amount detection from a line
     */
    private function processAmountDetection($line, $pageNumber, &$pendingAmountsByPage)
    {
        $amountPatterns = [
            '/Total\s+Receivable\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Total\s+Amount\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Grand\s+Total\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Net\s+Amount\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Net\s+Payable\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Payable\s+Amount\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Total\s*[:\-]?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Balance\s*:?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Sub\s*Total\s*[:\-]?\s*([\d,\-]+(?:\.\d{2,})?)/i',
            '/Amount\s*[:\-]?\s*([\d,\-]+(?:\.\d{2,})?)/i',
        ];

        foreach ($amountPatterns as $amountPattern) {
            if (preg_match($amountPattern, $line, $amountMatches)) {
                $val = str_replace(',', '', $amountMatches[1]);
                if (!in_array($val, $pendingAmountsByPage[$pageNumber] ?? [])) {
                    $pendingAmountsByPage[$pageNumber][] = $val;
                }
                break;
            }
        }
    }

    /**
     * Parse the collected customer block (between Salesperson and Bill To/Ship To/Invoice Type)
     * Extracts Customer Name, Customer Code, Address, City
     */
    private function parseCustomerBlock(&$customersData, $blockLines, $pageNumber, $lineNum, $lines, &$currentCustomerIndex, &$pageOwners, &$pageHasPrimary)
    {
        Log::info('parseCustomerBlock: processing collected lines', ['block_lines' => $blockLines, 'page_number' => $pageNumber]);

        $customerName = null;
        $customerCode = null;
        $address = [];
        $city = null;

        // Primary patterns to find customer name and code in the block
        // All patterns:
        // - Use (?i) for case-insensitive
        // - Allow customer names to contain: letters, spaces, &, -, ., ', (, )
        // - Require customer code to be exactly 4-6 digits (allow extra content after)
        // - Avoid financial fields by only matching lines that start with the label
        $primaryPatterns = [
            // Pattern 1: Bill To: Customer Name (with special chars) 12345
            '/^Bill\s+To:\s*([a-zA-Z0-9\s&\-.\'()]+?)\s+(\d{4,6})(?:\s+.+)?$/i',

            // Pattern 2: Customer: Customer Name (with special chars) 12345
            '/^Customer:\s*([a-zA-Z0-9\s&\-.\'()]+?)\s+(\d{4,6})(?:\s+.+)?$/i',

            // Pattern 3: Customer Code: 12345 Customer Name (with special chars)
            '/^Customer\s+Code:\s*(\d{4,6})\s+([a-zA-Z0-9\s&\-.\'()]+)(?:\s+.+)?$/i',

            // Pattern 4: Billed To: Customer Name (with special chars) 12345
            '/^Billed\s+To:\s*([a-zA-Z0-9\s&\-.\'()]+?)\s+(\d{4,6})(?:\s+.+)?$/i',

            // Pattern 5: Sold To: Customer Name (with special chars) 12345
            '/^Sold\s+To:\s*([a-zA-Z0-9\s&\-.\'()]+?)\s+(\d{4,6})(?:\s+.+)?$/i',

            // Pattern 6: Just Customer Name (with special chars) 12345 (no label)
            '/^([a-zA-Z0-9\s&\-.\'()]+?)\s+(\d{4,6})(?:\s+.+)?$/i',
        ];

        // First try to find name/code in any line of the block
        foreach ($blockLines as $blockLine) {
            $blockLineTrimmed = trim($blockLine);
            // If line has both "Bill To:" and "Ship To:", split and take the first part
            if (str_contains(strtolower($blockLineTrimmed), 'bill to') && str_contains(strtolower($blockLineTrimmed), 'ship to')) {
                $parts = preg_split('/\s{4,}/', $blockLineTrimmed); // Split on 4+ spaces
                if (count($parts) > 0) {
                    $blockLineTrimmed = trim($parts[0]);
                }
            }
            foreach ($primaryPatterns as $idx => $pattern) {
                // For pattern 6 (no label), skip if line has financial terms
                if ($idx === 5 && preg_match('/(?:Total|Receivable|Amount|Discount|Sub|Grand|Balance|Tax|Price|Rate|Qty|TR#|Ship|Order|Comments|Salesperson|Billing Date|Invoice Type)/i', $blockLineTrimmed)) {
                    continue;
                }
                if (preg_match($pattern, $blockLineTrimmed, $matches)) {
                    if ($idx === 2) { // Pattern 3 (Customer Code first)
                        $customerCode = trim($matches[1]);
                        $customerName = trim($matches[2]);
                    } else { // All other patterns (Name first)
                        $customerName = trim($matches[1]);
                        $customerCode = trim($matches[2]);
                    }
                    break 2; // Break both loops
                }
            }
        }

        // If we didn't find it with primary patterns, try looking for standalone name + 4-6 digit code
        if (!$customerCode || !$customerName) {
            foreach ($blockLines as $blockLine) {
                $blockLineTrimmed = trim($blockLine);
                // If line has both "Bill To:" and "Ship To:", split and take the first part
                if (str_contains(strtolower($blockLineTrimmed), 'bill to') && str_contains(strtolower($blockLineTrimmed), 'ship to')) {
                    $parts = preg_split('/\s{4,}/', $blockLineTrimmed); // Split on 4+ spaces
                    if (count($parts) > 0) {
                        $blockLineTrimmed = trim($parts[0]);
                    }
                }

                // Skip any line that contains financial terms to avoid false positives
                if (preg_match('/(?:Total|Receivable|Amount|Discount|Sub|Grand|Balance|Tax|Price|Rate|Qty|TR#|Ship|Order|Comments)/i', $blockLineTrimmed)) {
                    continue;
                }

                // Pattern 6: Standalone "Customer Name (with special chars) 12345" (allow extra content after)
                if (preg_match('/^([a-zA-Z0-9\s&\-.\'()]+?)\s+(\d{4,6})(?:\s+.+)?$/', $blockLineTrimmed, $matches)) {
                    $customerName = trim($matches[1]);
                    $customerCode = trim($matches[2]);
                    break;
                }
                // Pattern 7: Standalone "12345 Customer Name (with special chars)" (allow extra content after)
                elseif (preg_match('/^(\d{4,6})\s+([a-zA-Z0-9\s&\-.\'()]+)(?:\s+.+)?$/', $blockLineTrimmed, $matches)) {
                    $customerCode = trim($matches[1]);
                    $customerName = trim($matches[2]);
                    break;
                }
            }
        }

        // Now extract address and city from remaining lines
        $foundNameCode = false;
        foreach ($blockLines as $blockLine) {
            // Skip the line that has the name/code
            if (
                ($customerName && str_contains($blockLine, $customerName)) ||
                ($customerCode && str_contains($blockLine, $customerCode)) ||
                strtolower(trim($blockLine)) === 'salesperson'
            ) {
                $foundNameCode = true;
                continue;
            }

            // After name/code is found, collect lines as address
            if ($foundNameCode && !empty(trim($blockLine))) {
                // Skip any lines that look like invoice numbers or totals
                if (
                    preg_match('/(?:Invoice|Inv|Bill|Receipt|Order|No|Number|Total|Amount|Receivable)/i', $blockLine) ||
                    preg_match('/^\d{4,10}$/', $blockLine)
                ) {
                    continue;
                }
                $address[] = trim($blockLine);
            }
        }

        // Try to extract city from address lines (last line is often city)
        if (!empty($address)) {
            $city = end($address);
        }

        // If we found a valid customer code and name, process it!
        Log::debug('parseCustomerBlock: checking found values', [
            'customer_code_found' => $customerCode,
            'customer_name_found' => $customerName
        ]);

        if ($customerCode && $customerName && $this->isValidCustomerName($customerName, $customerCode)) {
            Log::info('parseCustomerBlock: found valid customer', [
                'customer_code' => $customerCode,
                'customer_name' => $customerName
            ]);

            $this->processCustomerMatch(
                $customersData,
                $customerCode,
                $customerName,
                $pageNumber,
                $lineNum,
                $lines,
                true,
                $currentCustomerIndex,
                $pageOwners,
                $pageHasPrimary
            );

            // Log what we extracted for debugging
            Log::info('Parsed customer block', [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'address' => $address,
                'city' => $city,
                'page' => $pageNumber
            ]);
        } else {
            Log::warning('parseCustomerBlock: did NOT found invalid or missing customer name/code', [
                'customer_code' => $customerCode,
                'customer_name' => $customerName
            ]);
        }
    }

    /**
     * Internal helper to process a customer match during PDF parsing
     */
    private function processCustomerMatch(&$customersData, $code, $name, $pageNumber, $lineNum, $lines, $isPrimary, &$currentCustomerIndex, &$pageOwners, &$pageHasPrimary)
    {
        if (!$this->isValidCustomerName($name, $code)) return false;

        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name, ' ,-');

        $idx = -1;
        foreach ($customersData as $i => $c) {
            if ($c['customer_code'] === $code) { $idx = $i; break; }
        }

        if ($idx === -1) {
            $customersData[] = [
                'customer_code' => $code,
                'customer_name' => $name,
                'customer_phone' => $this->extractPhoneNumber($lines, $lineNum, $name),
                'pages' => [],
                'invoices' => []
            ];
            $idx = count($customersData) - 1;
        }

        if ($isPrimary) {
            $currentCustomerIndex = $idx;
            $pageOwners[$pageNumber] = $code;
            $pageHasPrimary[$pageNumber] = true;
            Log::info('Page owner set (Primary)', ['page' => $pageNumber, 'code' => $code, 'name' => $name]);
        } elseif (!isset($pageOwners[$pageNumber])) {
            // Secondary only becomes owner if NO owner exists yet for this page
            $pageOwners[$pageNumber] = $code;
            Log::info('Page owner set (Secondary)', ['page' => $pageNumber, 'code' => $code, 'name' => $name]);
        } else {
            Log::info('Ignoring secondary customer (already owned)', ['page' => $pageNumber, 'code' => $code, 'name' => $name]);
        }

        if (!in_array($pageNumber, $customersData[$idx]['pages'])) {
            $customersData[$idx]['pages'][] = $pageNumber;
        }

        return true;
    }

    /**
     * Extract phone number from PDF text around customer information
     */
    private function extractPhoneNumber($lines, $currentLineIndex, $customerName)
    {
        // Define phone number patterns (Pakistani and international formats)
        $phonePatterns = [
            // Pakistani formats
            '/(?:ph|phone|tel|mobile|cell)[\s:]*(\+92[\s-]?[0-9\s-]{10,15})/i',
            '/(?:ph|phone|tel|mobile|cell)[\s:]*(\+92[0-9]{10,12})/i',
            '/(?:ph|phone|tel|mobile|cell)[\s:]*(92[0-9]{10,12})/i',
            '/(?:ph|phone|tel|mobile|cell)[\s:]*(03[0-9]{9})/i',
            '/(?:ph|phone|tel|mobile|cell)[\s:]*(021[0-9-\s]{7,15})/i',

            // General international formats
            '/(?:ph|phone|tel|mobile|cell)[\s:]*(\+[0-9\s-]{8,20})/i',
            '/(?:ph|phone|tel|mobile|cell)[\s:]*([0-9\s-]{8,20})/i',

            // Standalone number patterns (be more selective)
            '/(\+92[0-9\s-]{10,15})/',
            '/(03[0-9]{9})/',
            '/(021[0-9\s-]{7,15})/',
            '/(\+[0-9]{1,4}[0-9\s-]{8,15})/',
        ];

        // Search in lines around the current customer line (±5 lines)
        $searchStart = max(0, $currentLineIndex - 5);
        $searchEnd = min(count($lines), $currentLineIndex + 5);

        for ($i = $searchStart; $i < $searchEnd; $i++) {
            $line = trim($lines[$i]);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            foreach ($phonePatterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $phoneNumber = trim($matches[1]);

                    // Clean up the phone number
                    $phoneNumber = $this->cleanPhoneNumber($phoneNumber);

                    if (!empty($phoneNumber)) {
                        Log::info('Phone number extracted', [
                            'customer_name' => $customerName,
                            'phone_number' => $phoneNumber,
                            'source_line' => $line,
                            'pattern_used' => $pattern
                        ]);

                        return $phoneNumber;
                    }
                }
            }
        }

        return null; // No phone number found
    }

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
            $pages = $customerData['pages'];

            // Create customer folder path
            $customerFolderPath = 'invoices/customers/' . $customerCode;

            // Ensure customer folder exists
            if (!Storage::disk('local')->exists($customerFolderPath)) {
                Storage::disk('local')->makeDirectory($customerFolderPath);
            }

            // Generate unique filename for customer PDF
            $customerPdfName = $customerCode . '_' . date('Y-m-d_H-i-s') . '_' . Str::random(6) . '.pdf';
            $customerPdfPath = $customerFolderPath . '/' . $customerPdfName;
            $fullCustomerPath = storage_path('app/' . $customerPdfPath);

            // Try different PDF separation methods
            $separated = $this->extractPdfPages($originalPdfPath, $fullCustomerPath, $pages);

            if (!$separated) {
                Log::error('PDF separation failed critically', [
                    'customer_code' => $customerCode,
                    'pages' => $pages
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
            $resolvedRawPhone = $this->resolveCustomerPhone($customerCode)
                ?? ($customerData['customer_phone'] ?? null);
            $resolvedPhone = $this->normalizePakistaniPhone($resolvedRawPhone);

            // Collect all invoice numbers, sum total amounts, and get invoice date range
            $sortedInvoices = collect($customerData['invoices'])->sortBy('page');
            $invoiceNumbers = $sortedInvoices->pluck('invoice_number')->unique()->filter()->values();
            $totalSum = $sortedInvoices->sum('total_amount');

            $startDateStr = $sortedInvoices->pluck('invoice_date')->filter()->first();
            $endDateStr = $sortedInvoices->pluck('invoice_date')->filter()->last();

            $invoiceDate = null;
            $startDate = null;
            $endDate = null;

            if ($startDateStr) {
                try {
                    $startDate = \Carbon\Carbon::parse($startDateStr);
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
                'customer_code',
                $customerData['customer_code']
            )->first();

            $phone = $customer?->contact_number ?? null;
            // Create database record
            $invoice = Invoice::create([
                'original_filename' => $originalFilename,
                'source_file_hash' => $fileHash,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'invoice_number' => $invoiceNumbers->isNotEmpty() ? $invoiceNumbers->implode(', ') : null,
                'invoice_date' => $invoiceDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_amount' => $totalSum ?: null,
                'pdf_path' => $customerPdfPath,
                'extracted_pages' => $pages,
                'page_range' => $pageRange,
                'processing_status' => 'completed',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
                'notes' => $notes
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
                'pages' => $pages,
                'pdf_path' => $customerPdfPath
            ]);

            return [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'invoice_id' => $invoice->id
            ];

        } catch (\Exception $e) {
            Log::error('Customer invoice separation failed', [
                'customer_code' => $customerData['customer_code'],
                'error' => $e->getMessage()
            ]);

            // Create failed record — still try to resolve the phone from the
            // customers table so the operator can act on the failure without
            // hunting down the contact.
            $failedPhone = $this->normalizePakistaniPhone(
                $this->resolveCustomerPhone($customerData['customer_code'])
                    ?? ($customerData['customer_phone'] ?? null)
            );
            Invoice::create([
                'original_filename' => $originalFilename,
                'source_file_hash' => $fileHash,
                'customer_code' => $customerData['customer_code'],
                'customer_name' => $customerData['customer_name'],
                'customer_phone' => $failedPhone,
                'pdf_path' => '',
                'extracted_pages' => $customerData['pages'],
                'processing_status' => 'failed',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
                'notes' => $notes . ' | Error: ' . $e->getMessage()
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
                'input' => $inputPath,
                'output' => $outputPath,
                'pages' => $pages
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
            $checkCmd = $isWindows ? 'where pdftk' : 'which pdftk 2>/dev/null';
            exec($checkCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                return false; // pdftk not available
            }

            // Build page range (e.g., "1-2 5-6")
            $pageRanges = [];
            $sortedPages = $pages;
            sort($sortedPages);

            $start = $sortedPages[0];
            $end = $sortedPages[0];

            for ($i = 1; $i < count($sortedPages); $i++) {
                if ($sortedPages[$i] == $end + 1) {
                    $end = $sortedPages[$i];
                } else {
                    $pageRanges[] = $start == $end ? $start : "$start-$end";
                    $start = $end = $sortedPages[$i];
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
                'command' => $command,
                'output' => $output,
                'return_code' => $returnCode
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
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'total_pages' => $pageCount,
                'pages_to_extract' => $pages
            ]);

            // Extract only the specified pages
            foreach ($pages as $pageNum) {
                if ($pageNum <= $pageCount && $pageNum > 0) {
                    $tplId = $fpdi->importPage($pageNum);
                    $size = $fpdi->getTemplateSize($tplId);

                    // Safeguard against missing size data
                    if ($size === false || !isset($size['w']) || !isset($size['h'])) {
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
                'success' => $success,
                'output_file_size' => $success ? filesize($outputPath) : 0
            ]);

            return $success;

        } catch (\Exception $e) {
            Log::error('PHP PDF extraction error: ' . $e->getMessage(), [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'pages' => $pages,
                'trace' => $e->getTraceAsString()
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
            $checkCmd = $isWindows ? 'where qpdf' : 'which qpdf 2>/dev/null';
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
        if (empty($pages))
            return '';

        sort($pages);
        $ranges = [];
        $start = $pages[0];
        $end = $pages[0];

        for ($i = 1; $i < count($pages); $i++) {
            if ($pages[$i] == $end + 1) {
                $end = $pages[$i];
            } else {
                $ranges[] = $start == $end ? $start : "$start-$end";
                $start = $end = $pages[$i];
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

        if (!Storage::disk('local')->exists($invoice->pdf_path)) {
            return back()->withErrors(['error' => 'Invoice file not found.']);
        }

        // Sanitize customer name for filename (remove invalid characters)
        $sanitizedCustomerName = $this->sanitizeFilename($invoice->customer_name);
        $filename = $invoice->customer_code . '_' . $sanitizedCustomerName . '.pdf';

        return Storage::disk('local')->download($invoice->pdf_path, $filename);
    }

    /**
     * Preview raw file from disk (Disk Explorer)
     */
    public function previewDiskFile(Request $request)
    {
        $path = $request->query('path');

        // Security check: ensure path is within invoices/customers
        if (!$path || !Str::startsWith($path, 'invoices/customers/') || !Storage::disk('local')->exists($path)) {
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
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'action' => 'delete',
            'module' => 'Invoices',
            'description' => "Deleted invoice for " . $invoice->customer_name,
            'ip_address' => request()->ip(),
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
            'phone' => 'required|string|max:20'
        ]);

        try {
            $invoice = Invoice::findOrFail($id);

            // Clean and validate phone number
            $phone = $this->cleanPhoneNumber($request->phone);

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ], 400);
            }

            // Update the invoice
            $invoice->update(['customer_phone' => $phone]);

            Log::info('Customer phone number updated via WhatsApp', [
                'invoice_id' => $invoice->id,
                'customer_code' => $invoice->customer_code,
                'customer_name' => $invoice->customer_name,
                'phone' => $phone,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone number updated successfully',
                'phone' => $phone
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update customer phone number', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'phone' => $request->phone
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update phone number'
            ], 500);
        }
    }

    /**
     * Send invoice via WhatsApp (Queued)
     */
    public function sendWhatsApp(Request $request, $id)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'template' => 'nullable|string|max:50'
        ]);

        try {
            $invoice = Invoice::findOrFail($id);

            // Check if invoice is ready to send
            if ($invoice->processing_status !== 'completed' || !$invoice->pdf_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is not ready for sending'
                ], 400);
            }

            // Phone number source priority:
            //   1. Explicit phone in request (manual override)
            //   2. customers.contact_number matched by invoice.customer_code
            //   3. invoice.customer_phone (legacy fallback)
            $phone = $request->phone
                ?? $this->resolveCustomerPhone($invoice->customer_code)
                ?? $invoice->customer_phone;

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is required (no contact_number on customer record).'
                ], 400);
            }

            // Clean number
            $phone = (new \App\Services\WhatsAppService())->formatPhoneNumber($phone);

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ], 400);
            }

            // Persist resolved/overridden phone on the invoice for history
            if ($phone !== $invoice->customer_phone) {
                $invoice->update(['customer_phone' => $phone]);
            }

            // Always use Urdu template
            $template = 'invoice_urdu';
            $url = route('invoices.download', $invoice->id);
            \App\Jobs\SendWhatsAppInvoiceJob::dispatch($invoice, $phone, $url, $template);

            $invoice->update([
                'whatsapp_status' => 'pending',
                'whatsapp_error' => null
            ]);

            Log::info('WhatsApp invoice job dispatched', [
                'invoice_id' => $invoice->id,
                'customer_code' => $invoice->customer_code,
                'phone' => $phone
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'action' => 'update',
                'module' => 'Invoices',
                'description' => "Queued WhatsApp invoice for {$invoice->customer_name} ({$invoice->customer_code})",
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice queued for sending via WhatsApp!',
                'data' => [
                    'status' => 'pending'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('WhatsApp send invoice error', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while queuing the invoice'
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

            if (!empty($scopedIds)) {
                $query->whereIn('id', $scopedIds);
            }

            $unsentInvoices = $query->get();

            if ($unsentInvoices->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No unsent invoices found'
                ]);
            }

            $whatsappService = new \App\Services\WhatsAppService();
            $skipped = 0;

            foreach ($unsentInvoices as $invoice) {
                // Customer phone first, then legacy invoice.customer_phone
                $rawPhone = $this->resolveCustomerPhone($invoice->customer_code)
                    ?? $invoice->customer_phone;

                $phone = $whatsappService->formatPhoneNumber($rawPhone);
                if (!$phone) {
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
                    'whatsapp_error' => null
                ]);
            }

            Log::info('Bulk WhatsApp invoice jobs dispatched', [
                'count' => $unsentInvoices->count()
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'action' => 'update',
                'module' => 'Invoices',
                'description' => "Bulk queued WhatsApp invoices for " . $unsentInvoices->count() . " customers",
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk sending initiated! ' . $unsentInvoices->count() . ' invoices queued.',
                'count' => $unsentInvoices->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk WhatsApp send error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate bulk send: ' . $e->getMessage()
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
                'success' => true,
                'stats' => $stats,
                'recent_failures' => $recentFailures
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch status'
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
                'success' => true,
                'invoices' => $unsentInvoices
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unsent invoices'
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
            'market'
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
        if (!preg_match('/[A-Za-z]/', $customerName)) {
            Log::warning('isValidCustomerName: rejected (no letters)', ['name' => $customerName]);
            return false;
        }

        // Check if customer code is exactly 4-6 digits as specified by user
        if (!preg_match('/^\d{4,6}$/', $customerCode)) {
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
        if (!$customerCode) {
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
