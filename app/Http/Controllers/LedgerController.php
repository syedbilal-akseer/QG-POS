<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppLedgerJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\LedgerImport;
use App\Models\SalespersonTarget;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Traits\ExtractsPdfPages;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LedgerController extends Controller
{
    use ExtractsPdfPages;

    /**
     * "Ledgers" — flat list of every imported customer ledger, with
     * multiselect + bulk WhatsApp send.
     */
    public function index(Request $request)
    {
        $filterFrom = $request->input('from') ?: null;
        $filterTo = $request->input('to') ?: null;
        $filterStatus = $request->input('status') ?: null;
        $filterWhatsapp = $request->input('whatsapp') ?: null;
        $filterCustomer = $request->input('customer') ?: null;
        $filterSalesperson = $request->input('salesperson') ?: null;
        $filterImport = $request->input('import') ?: null;

        if ($request->boolean('unsent_only')) {
            $filterWhatsapp = 'pending';
        }

        $activeImport = $filterImport ? LedgerImport::with('uploader')->find($filterImport) : null;

        $baseFilter = function ($q) use ($filterFrom, $filterTo, $filterStatus, $filterWhatsapp, $filterCustomer, $filterSalesperson, $filterImport, $request) {
            if ($filterImport) {
                $q->where('ledger_import_id', $filterImport);
            }
            if ($filterFrom) {
                $q->whereDate('period_from', '>=', $filterFrom);
            }
            if ($filterTo) {
                $q->whereDate('period_to', '<=', $filterTo);
            }
            if ($filterCustomer) {
                $q->where('customer_code', $filterCustomer);
            }
            if ($filterSalesperson === 'unmatched') {
                $q->whereNull('salesperson_id');
            } elseif ($filterSalesperson) {
                $q->where('salesperson_id', $filterSalesperson);
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
                        ->orWhere('salesperson_name', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%");
                });
            }
        };

        $stats = CustomerLedger::query()
            ->tap($baseFilter)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN processing_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN processing_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN processing_status = 'completed'
                          AND pdf_path IS NOT NULL AND pdf_path != ''
                          AND (whatsapp_status IS NULL OR whatsapp_status = '' OR whatsapp_status = 'failed')
                     THEN 1 ELSE 0 END) as unsent
            ")
            ->first();

        $ledgersPage = CustomerLedger::query()
            ->tap($baseFilter)
            ->with(['uploader', 'salesperson'])
            ->orderByDesc('uploaded_at')
            ->paginate(50)
            ->withQueryString();

        $customerOptions = CustomerLedger::query()
            ->whereNotNull('customer_code')
            ->select('customer_code', DB::raw('MAX(customer_name) as customer_name'))
            ->groupBy('customer_code')
            ->orderBy('customer_name')
            ->get();

        $salespersonOptions = User::query()
            ->whereIn('id', CustomerLedger::query()->whereNotNull('salesperson_id')->distinct()->pluck('salesperson_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.ledgers.index', compact(
            'ledgersPage',
            'stats',
            'filterFrom',
            'filterTo',
            'filterStatus',
            'filterWhatsapp',
            'filterCustomer',
            'filterSalesperson',
            'activeImport',
            'customerOptions',
            'salespersonOptions'
        ));
    }

    /**
     * "Ledger Import" — upload form + recent import batches (found /
     * imported / duplicate / failed counts per upload).
     */
    public function uploadForm()
    {
        $recentImports = LedgerImport::with('uploader')
            ->orderByDesc('uploaded_at')
            ->limit(15)
            ->get();

        return view('admin.ledgers.upload', compact('recentImports'));
    }

    /**
     * Parse the uploaded bulk ledger PDF and split it per customer. Runs
     * synchronously (mirrors InvoiceController::store()) — no auto WhatsApp
     * send happens here.
     */
    public function store(Request $request)
    {
        $request->validate([
            'ledger_file' => 'required|file|mimes:pdf|max:102400', // 100MB max
            'notes' => 'nullable|string|max:1000',
        ]);

        set_time_limit(0);

        $file = $request->file('ledger_file');
        $originalFilename = $file->getClientOriginalName();
        $fileHash = null;

        try {
            $fileHash = hash_file('sha256', $file->getPathname());

            $storedName = 'ledgers/originals/'.Str::uuid().'.pdf';
            Storage::disk('local')->put($storedName, file_get_contents($file));

            Log::info('Processing customer ledger PDF', [
                'filename' => $originalFilename,
                'size' => $file->getSize(),
                'file_hash' => $fileHash,
            ]);

            $text = $this->extractPdfTextFromFile($file->getPathname());
            if (empty($text)) {
                $this->recordLedgerImport($originalFilename, $fileHash, null, null, 0, 0, 0, 0, 'failed', 'Could not extract text from the PDF.', $request->notes);

                return back()->withErrors(['error' => 'Could not extract text from the PDF. Please check the file.']);
            }

            $parsed = $this->parseLedgerPdf($text);
            $customers = $parsed['customers'];
            $periodFrom = $this->parseLedgerDate($parsed['period_from']);
            $periodTo = $this->parseLedgerDate($parsed['period_to']);

            if (empty($customers)) {
                $this->recordLedgerImport($originalFilename, $fileHash, $periodFrom, $periodTo, 0, 0, 0, 0, 'failed', 'No customers found in the PDF.', $request->notes);

                return back()->withErrors(['error' => 'No customers found in the PDF. Please check the file format.']);
            }

            // Duplicate detection — same (customer_code, period_from, period_to)
            // already imported and not failed = skip that customer.
            $toProcess = [];
            $skipped = [];
            foreach ($customers as $c) {
                $exists = $periodFrom && $periodTo && CustomerLedger::where('customer_code', $c['customer_code'])
                    ->whereDate('period_from', $periodFrom)
                    ->whereDate('period_to', $periodTo)
                    ->where('processing_status', '!=', 'failed')
                    ->exists();

                if ($exists) {
                    $skipped[] = $c;

                    continue;
                }
                $toProcess[] = $c;
            }

            $ledgerImport = $this->recordLedgerImport(
                $originalFilename,
                $fileHash,
                $periodFrom,
                $periodTo,
                count($customers),
                0,
                count($skipped),
                0,
                'completed',
                null,
                $request->notes
            );

            if (empty($toProcess) && ! empty($skipped)) {
                Log::info('Ledger PDF re-upload — all customers already imported for this period', [
                    'filename' => $originalFilename,
                    'period' => [$periodFrom, $periodTo],
                    'skipped_count' => count($skipped),
                ]);

                return redirect()->route('ledgers.index', [
                    'from' => optional($periodFrom)->toDateString(),
                    'to' => optional($periodTo)->toDateString(),
                ])->with(
                    'warning',
                    'This ledger PDF has already been imported — all '.count($skipped)
                        .' customer(s) for this period are already in the system. Showing the existing ledgers for this period below.'
                );
            }

            $created = [];
            $failedSplits = 0;
            foreach ($toProcess as $c) {
                $row = $this->createLedgerRow(
                    $file->getPathname(),
                    $c,
                    $originalFilename,
                    $fileHash,
                    $periodFrom,
                    $periodTo,
                    $request->notes,
                    $ledgerImport->id
                );
                if ($row) {
                    $created[] = $row;
                    if ($row->processing_status === 'failed') {
                        $failedSplits++;
                    }
                }
            }

            $ledgerImport->update([
                'imported_count' => count($created) - $failedSplits,
                'failed_count' => $failedSplits,
            ]);

            if (count($created) > 0) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name,
                    'action' => 'create',
                    'module' => 'Ledgers',
                    'description' => 'Uploaded ledger PDF and extracted '.count($created)." customer ledger(s) from {$originalFilename}",
                    'ip_address' => request()->ip(),
                ]);
            }

            $successMsg = 'Ledger PDF processed! Found '.count($customers).' customer(s): '
                .(count($created) - $failedSplits).' imported';
            if (! empty($skipped)) {
                $successMsg .= ', '.count($skipped).' duplicate (already imported for this period)';
            }
            if ($failedSplits > 0) {
                $successMsg .= ', '.$failedSplits.' failed to split';
            }
            $successMsg .= '.';

            return redirect()->route('ledgers.index', ['import' => $ledgerImport->id])->with('success', $successMsg);
        } catch (\Exception $e) {
            Log::error('Ledger processing failed: '.$e->getMessage(), [
                'file' => $originalFilename,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->recordLedgerImport($originalFilename, $fileHash, null, null, 0, 0, 0, 0, 'failed', $e->getMessage(), $request->notes);

            return back()->withErrors(['error' => 'Failed to process ledger PDF: '.$e->getMessage()]);
        }
    }

    /**
     * Create (or top up) the LedgerImport row that tracks this upload's
     * outcome — how many customers were found, imported, already-duplicate,
     * or failed to split. Powers the "recent imports" status list on the
     * Ledger Import page.
     */
    private function recordLedgerImport(
        string $originalFilename,
        ?string $fileHash,
        ?Carbon $periodFrom,
        ?Carbon $periodTo,
        int $customersFound,
        int $importedCount,
        int $duplicateCount,
        int $failedCount,
        string $status,
        ?string $error,
        ?string $notes
    ): LedgerImport {
        return LedgerImport::create([
            'original_filename' => $originalFilename,
            'source_file_hash' => $fileHash,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'customers_found' => $customersFound,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'status' => $status,
            'error' => $error ? $this->sanitizeUtf8($error) : $error,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Split one customer's pages out into their own PDF and create the
     * CustomerLedger row. Returns null only if we couldn't create any row
     * at all (shouldn't normally happen — split failures still get a
     * 'failed' row so they're visible in the list).
     */
    private function createLedgerRow(
        string $originalPdfPath,
        array $customer,
        string $originalFilename,
        string $fileHash,
        ?Carbon $periodFrom,
        ?Carbon $periodTo,
        ?string $notes,
        ?int $ledgerImportId = null
    ): ?CustomerLedger {
        $customerCode = $customer['customer_code'];
        $customerName = $customer['customer_name'];
        $pages = $customer['pages'];

        // Prefer the authoritative customers.salesperson field (synced from
        // Oracle) over the PDF's scraped "Salesperson :" line — it's not
        // subject to PDF text-extraction quirks. Fall back to the PDF value
        // only for customers with no local record (e.g. internal accounts
        // like "QG Tech Lhr (HBM)" that aren't in the customers table).
        $salespersonRaw = $this->resolveCustomerSalesperson($customerCode) ?? ($customer['salesperson_raw'] ?? null);

        [$salespersonId, $salespersonName] = $this->resolveSalesperson($salespersonRaw);

        $resolvedPhone = $this->normalizePakistaniPhone(
            $this->resolveCustomerPhone($customerCode)
        );

        $folder = 'ledgers/customers/'.$customerCode;
        if (! Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $pdfName = $customerCode.'_'.date('Y-m-d_H-i-s').'_'.Str::random(6).'.pdf';
        $pdfPath = $folder.'/'.$pdfName;
        $fullPath = storage_path('app/'.$pdfPath);

        $split = $this->splitPdfPages($originalPdfPath, $fullPath, $pages);

        if (! $split) {
            Log::error('Ledger PDF split failed', ['customer_code' => $customerCode, 'pages' => $pages]);

            return CustomerLedger::create([
                'ledger_import_id' => $ledgerImportId,
                'original_filename' => $originalFilename,
                'source_file_hash' => $fileHash,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'customer_phone' => $resolvedPhone,
                'salesperson_raw' => $salespersonRaw,
                'salesperson_id' => $salespersonId,
                'salesperson_name' => $salespersonName,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'pdf_path' => '',
                'extracted_pages' => $pages,
                'processing_status' => 'failed',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
                'notes' => trim(($notes ?? '').' | Error: could not extract pages '.implode(',', $pages)),
            ]);
        }

        return CustomerLedger::create([
            'ledger_import_id' => $ledgerImportId,
            'original_filename' => $originalFilename,
            'source_file_hash' => $fileHash,
            'customer_code' => $customerCode,
            'customer_name' => $customerName,
            'customer_phone' => $resolvedPhone,
            'salesperson_raw' => $customer['salesperson_raw'] ?? null,
            'salesperson_id' => $salespersonId,
            'salesperson_name' => $salespersonName,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'total_debit' => $customer['total_debit'] ?? null,
            'total_credit' => $customer['total_credit'] ?? null,
            'pdf_path' => $pdfPath,
            'extracted_pages' => $pages,
            'page_range' => $this->formatPageRange($pages),
            'processing_status' => 'completed',
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Parse the raw ledger text into per-customer groups. Every page of a
     * customer's block repeats a literal "Customer : {code} {name}" line
     * (confirmed across the sample report), so we can group by consecutive
     * pages sharing the same code instead of Invoice's heavier state
     * machine. We deliberately do NOT reconstruct individual transaction
     * rows — this report's table cells extract in column-major order (all
     * dates together, then all amounts, etc.), so per-row reconstruction
     * from plain text isn't reliable. The full transaction detail stays
     * intact inside each customer's split PDF.
     */
    private function parseLedgerPdf(string $text): array
    {
        // PDF fonts sometimes encode the non-breaking space between a
        // salesperson's names as a stray/malformed byte sequence (not
        // valid UTF-8), which MySQL's strict UTF-8 columns reject outright.
        // Scrub it before anything else touches the string.
        $text = $this->sanitizeUtf8($text);
        $text = str_replace(["\xC2\xA0", "\xA0", 'Â'], ' ', $text);
        $pages = preg_split('/\f/', $text);

        $periodFrom = $periodTo = null;
        if (preg_match_all('/\d{1,2}-[A-Za-z]{3}-\d{2,4}/', substr($pages[0] ?? '', 0, 800), $dateMatches) && count($dateMatches[0]) >= 2) {
            $periodFrom = $dateMatches[0][0];
            $periodTo = $dateMatches[0][1];
        }

        $customers = [];
        $currentCode = null;
        $currentIndex = -1;

        foreach ($pages as $pageIndex => $pageText) {
            $pageNum = $pageIndex + 1;
            if (trim($pageText) === '') {
                continue;
            }

            if (! preg_match('/^Customer\s*:\s*(\d{3,7})\s+(.+)$/mi', $pageText, $cm)) {
                continue;
            }

            $code = trim($cm[1]);
            $name = $this->cleanExtractedLabel($cm[2]);

            $salespersonRaw = null;
            if (preg_match('/^Salesperson\s*:\s*(.*)$/mi', $pageText, $sm)) {
                $salespersonRaw = $this->cleanExtractedLabel($sm[1]) ?: null;
            }

            if ($code !== $currentCode) {
                $customers[] = [
                    'customer_code' => $code,
                    'customer_name' => $name,
                    'salesperson_raw' => $salespersonRaw,
                    'pages' => [$pageNum],
                    'page_text' => $pageText,
                ];
                $currentIndex++;
                $currentCode = $code;
            } else {
                $customers[$currentIndex]['pages'][] = $pageNum;
                $customers[$currentIndex]['page_text'] .= "\n".$pageText;
                if ($salespersonRaw) {
                    $customers[$currentIndex]['salesperson_raw'] = $salespersonRaw;
                }
            }
        }

        foreach ($customers as &$c) {
            $c['total_debit'] = null;
            $c['total_credit'] = null;
            if (preg_match('/Total\s+Of\s+.+?\n\s*([\d,]+(?:\.\d+)?)\s+([\d,]+(?:\.\d+)?)/is', $c['page_text'], $tm)) {
                $c['total_debit'] = (float) str_replace(',', '', $tm[1]);
                $c['total_credit'] = (float) str_replace(',', '', $tm[2]);
            }
            unset($c['page_text']);
        }
        unset($c);

        return [
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'customers' => $customers,
        ];
    }

    /**
     * Drop byte sequences that aren't valid UTF-8 (PDF fonts occasionally
     * produce malformed multi-byte sequences for glyphs like a non-breaking
     * space) so the string is always safe to store in a strict-UTF-8 MySQL
     * column.
     */
    private function sanitizeUtf8(string $text): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        return $clean !== false ? $clean : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * Clean a captured "Customer : {name}" / "Salesperson : {name}" value.
     * On pages with little content, PdfParser sometimes doesn't insert a
     * line break between two adjacent labels, so a greedy end-of-line
     * capture can swallow the next label's text too (e.g. customer_name
     * ending up as "Ahmed Traders HBM Salesperson : Aqib"). Strip anything
     * from a subsequent known label onward, defensively.
     */
    private function cleanExtractedLabel(string $raw): string
    {
        $value = preg_replace('/\s+/', ' ', $raw);
        $value = preg_replace('/\s*(Customer|Address|Salesperson|Total\s+Of|Date\s+Inv\s+No)\s*:.*/i', '', $value);

        return trim($value);
    }

    private function parseLedgerDate(?string $raw): ?Carbon
    {
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-M-y', $raw)->startOfDay();
        } catch (\Exception $e) {
            try {
                return Carbon::parse($raw)->startOfDay();
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Normalize "Aqib, Mr. Muhammad" / "HaiderÂ Ali," / "Fahad Khan" style
     * scraped salesperson text and match it against the users table, reusing
     * SalespersonTarget::resolveUserId() which already handles the
     * "Last, First" <-> "First Last" swap against users.name /
     * users.oracle_user_name (see app/Models/SalespersonTarget.php).
     *
     * @return array{0: ?int, 1: ?string} [salesperson_id, display_name]
     */
    private function resolveSalesperson(?string $raw): array
    {
        if (! $raw) {
            return [null, null];
        }

        $clean = preg_replace('/\s+/', ' ', $raw);
        $clean = trim($clean, " ,\t\n\r\0\x0B");
        if ($clean === '') {
            return [null, null];
        }

        // Strip common titles so "Aqib, Mr. Muhammad" swaps to "Muhammad Aqib".
        // Note: no trailing \b — "Mr." has a period right after it, and \b
        // can't match between two non-word characters (". "), so a trailing
        // \b would silently fail to consume the period and leave it behind
        // (e.g. "Aqib, . Muhammad"). "Mrs?" (not "Mr" / "Mrs" as separate
        // alternatives) avoids alternation stopping at "Mr" inside "Mrs.".
        $clean = preg_replace('/\b(Mrs?|Miss|Ms)\.?/i', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean), ' ,');
        if ($clean === '') {
            return [null, null];
        }

        $swapped = null;
        $firstPart = null;
        if (str_contains($clean, ',')) {
            $parts = array_map('trim', explode(',', $clean, 2));
            if (count($parts) === 2 && $parts[1] !== '') {
                $swapped = $parts[1].' '.$parts[0];
            }
            $firstPart = $parts[0] !== '' ? $parts[0] : null;
        }

        $userId = SalespersonTarget::resolveUserId($clean, $swapped);

        // Last resort: some ledger entries only carry a surname (e.g.
        // "Zeeshan, Mr. Muhammad") while the matching user record was only
        // ever given their first name (e.g. "Zeeshan"). Try that alone.
        if (! $userId && $firstPart) {
            $userId = User::where('name', $firstPart)
                ->orWhere('oracle_user_name', $firstPart)
                ->value('id');
        }

        if ($userId) {
            $user = User::find($userId);

            return [$userId, $user->name ?? ($swapped ?: $clean)];
        }

        return [null, $swapped ?: $clean];
    }

    /**
     * Look up the customer's contact_number from the customers table,
     * matching customer_code against either customer_number or
     * customer_id (mirrors InvoiceController::resolveCustomerPhone()).
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
     * Look up the customer's salesperson from the customers table (synced
     * from Oracle), matching customer_code against either customer_number
     * or customer_id. This is the primary salesperson source — see
     * createLedgerRow() for why it's preferred over the PDF's scraped
     * "Salesperson :" line.
     */
    private function resolveCustomerSalesperson(?string $customerCode): ?string
    {
        if (! $customerCode) {
            return null;
        }

        $salesperson = Customer::where('customer_number', $customerCode)
            ->orWhere('customer_id', $customerCode)
            ->value('salesperson');

        return $salesperson ?: null;
    }

    /**
     * Normalize Pakistani phone numbers to canonical "+92XXXXXXXXXX" form
     * (mirrors InvoiceController::normalizePakistaniPhone()).
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

        if (str_starts_with($digits, '92')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+92'.substr($digits, 1);
        }

        if (preg_match('/^3\d{9}$/', $digits)) {
            return '+92'.$digits;
        }

        return '+'.$digits;
    }

    private function formatPageRange(array $pages): string
    {
        if (empty($pages)) {
            return '';
        }

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

    private function sanitizeFilename(string $filename): string
    {
        $sanitized = preg_replace('/[\/\\:*?"<>|]/', '', $filename);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        $sanitized = str_replace(' ', '_', $sanitized);
        $sanitized = trim($sanitized, '_');
        $sanitized = substr($sanitized, 0, 100);

        return $sanitized === '' ? 'ledger' : $sanitized;
    }

    /**
     * Download a customer's split ledger PDF.
     */
    public function download($id)
    {
        $ledger = CustomerLedger::findOrFail($id);

        if (! $ledger->pdf_path || ! Storage::disk('local')->exists($ledger->pdf_path)) {
            return back()->withErrors(['error' => 'Ledger file not found.']);
        }

        $sanitized = $this->sanitizeFilename($ledger->customer_name);
        $filename = $ledger->customer_code.'_'.$sanitized.'.pdf';

        return Storage::disk('local')->download($ledger->pdf_path, $filename);
    }

    /**
     * Update the phone number stored on a ledger row.
     */
    public function updatePhone(Request $request, $id)
    {
        $request->validate(['phone' => 'required|string|max:20']);

        try {
            $ledger = CustomerLedger::findOrFail($id);
            $phone = $this->normalizePakistaniPhone($request->phone);

            if (! $phone) {
                return response()->json(['success' => false, 'message' => 'Invalid phone number format'], 400);
            }

            $ledger->update(['customer_phone' => $phone]);

            return response()->json(['success' => true, 'message' => 'Phone number updated successfully', 'phone' => $phone]);
        } catch (\Exception $e) {
            Log::error('Failed to update ledger phone number', ['ledger_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to update phone number'], 500);
        }
    }

    /**
     * Queue a single WhatsApp send for one ledger row.
     */
    public function sendWhatsApp(Request $request, $id)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'template' => 'nullable|string|max:50',
        ]);

        try {
            $ledger = CustomerLedger::findOrFail($id);

            if ($ledger->processing_status !== 'completed' || ! $ledger->pdf_path) {
                return response()->json(['success' => false, 'message' => 'Ledger is not ready for sending'], 400);
            }

            $phone = $request->phone
                ?? $this->resolveCustomerPhone($ledger->customer_code)
                ?? $ledger->customer_phone;

            if (! $phone) {
                return response()->json(['success' => false, 'message' => 'Phone number is required (no contact_number on customer record).'], 400);
            }

            $phone = (new WhatsAppService)->formatPhoneNumber($phone);
            if (! $phone) {
                return response()->json(['success' => false, 'message' => 'Invalid phone number format'], 400);
            }

            if ($phone !== $ledger->customer_phone) {
                $ledger->update(['customer_phone' => $phone]);
            }

            $template = $request->template ?: 'invoice_ready';
            $url = route('ledgers.download', $ledger->id);
            SendWhatsAppLedgerJob::dispatch($ledger, $phone, $url, $template);

            $ledger->update(['whatsapp_status' => 'pending', 'whatsapp_error' => null]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'action' => 'update',
                'module' => 'Ledgers',
                'description' => "Queued WhatsApp ledger for {$ledger->customer_name} ({$ledger->customer_code})",
                'ip_address' => request()->ip(),
            ]);

            return response()->json(['success' => true, 'message' => 'Ledger queued for sending via WhatsApp!', 'data' => ['status' => 'pending']]);
        } catch (\Exception $e) {
            Log::error('WhatsApp send ledger error', ['ledger_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'An error occurred while queuing the ledger'], 500);
        }
    }

    /**
     * Bulk WhatsApp send — requires an explicit non-empty ledger_ids
     * selection (no "send everything unsent" fallback), since sends must
     * only ever be triggered by an explicit multiselect + button click.
     */
    public function bulkSendWhatsApp(Request $request)
    {
        $request->validate([
            'ledger_ids' => 'required|array|min:1',
            'ledger_ids.*' => 'integer',
            'template' => 'nullable|string|max:50',
        ]);

        try {
            $template = $request->template ?: 'invoice_ready';

            $ledgers = CustomerLedger::where('processing_status', 'completed')
                ->whereNotNull('pdf_path')
                ->whereIn('id', $request->ledger_ids)
                ->where(function ($q) {
                    $q->whereNull('whatsapp_status')->orWhere('whatsapp_status', '!=', 'sent');
                })
                ->get();

            if ($ledgers->isEmpty()) {
                return response()->json(['success' => true, 'message' => 'Nothing to send — selected ledgers are already sent or not ready.']);
            }

            $whatsappService = new WhatsAppService;
            $skipped = 0;

            foreach ($ledgers as $ledger) {
                $rawPhone = $this->resolveCustomerPhone($ledger->customer_code) ?? $ledger->customer_phone;
                $phone = $whatsappService->formatPhoneNumber($rawPhone);

                if (! $phone) {
                    $skipped++;

                    continue;
                }

                if ($phone !== $ledger->customer_phone) {
                    $ledger->update(['customer_phone' => $phone]);
                }

                $url = route('ledgers.download', $ledger->id);
                SendWhatsAppLedgerJob::dispatch($ledger, $phone, $url, $template);

                $ledger->update(['whatsapp_status' => 'pending', 'whatsapp_error' => null]);
            }

            ActivityLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'action' => 'update',
                'module' => 'Ledgers',
                'description' => 'Bulk queued WhatsApp ledgers for '.$ledgers->count().' customer(s)',
                'ip_address' => request()->ip(),
            ]);

            $message = 'Bulk sending initiated! '.($ledgers->count() - $skipped).' ledger(s) queued.';
            if ($skipped > 0) {
                $message .= " {$skipped} skipped (no valid phone number).";
            }

            return response()->json(['success' => true, 'message' => $message, 'count' => $ledgers->count() - $skipped]);
        } catch (\Exception $e) {
            Log::error('Bulk WhatsApp ledger send error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to initiate bulk send: '.$e->getMessage()], 500);
        }
    }

    /**
     * Real-time WhatsApp queue stats for the list page's polling banner.
     */
    public function getQueueStatus()
    {
        try {
            $stats = CustomerLedger::selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN whatsapp_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN whatsapp_status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN whatsapp_status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN whatsapp_status = 'failed' THEN 1 ELSE 0 END) as failed
                ")
                ->where('processing_status', 'completed')
                ->whereNotNull('pdf_path')
                ->first();

            $recentFailures = CustomerLedger::where('whatsapp_status', 'failed')
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->select('id', 'customer_name', 'whatsapp_error')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();

            return response()->json(['success' => true, 'stats' => $stats, 'recent_failures' => $recentFailures]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch status'], 500);
        }
    }

    /**
     * Delete a ledger row and its split PDF.
     */
    public function destroy($id)
    {
        $ledger = CustomerLedger::findOrFail($id);

        if ($ledger->pdf_path && Storage::disk('local')->exists($ledger->pdf_path)) {
            Storage::disk('local')->delete($ledger->pdf_path);
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'action' => 'delete',
            'module' => 'Ledgers',
            'description' => 'Deleted ledger for '.$ledger->customer_name,
            'ip_address' => request()->ip(),
        ]);

        $ledger->delete();

        return redirect()->route('ledgers.index')->with('success', 'Ledger deleted successfully.');
    }
}
