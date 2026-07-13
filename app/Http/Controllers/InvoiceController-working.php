<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader\StreamReader;
use Smalot\PdfParser\Parser;
use Maatwebsite\Excel\Facades\Excel; // Added Import

class InvoiceController extends Controller
{
    /**
     * Display invoice management page
     */
    public function index()
    {
        $invoices = Invoice::with('uploader')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.invoices.index', compact('invoices'));
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

            // Store the original PDF
            $filename = 'invoices/originals/' . Str::uuid() . '.pdf';
            Storage::disk('local')->put($filename, file_get_contents($file));

            Log::info('Processing PDF invoice', [
                'filename' => $originalFilename,
                'size' => $file->getSize()
            ]);

            // Extract text and identify customers
            $customersData = $this->extractCustomersFromPDF($file->getPathname());

            if (empty($customersData)) {
                return back()->withErrors(['error' => 'No customers found in the PDF. Please check the file format.']);
            }

            $processedInvoices = [];

            // Process each customer's invoices
            foreach ($customersData as $customerData) {
                $customerInvoice = $this->separateCustomerInvoices(
                    $file->getPathname(),
                    $customerData,
                    $originalFilename,
                    $request->notes
                );

                if ($customerInvoice) {
                    $processedInvoices[] = $customerInvoice;
                }
            }

            return redirect()->route('invoices.index')
                ->with(
                    'success',
                    'PDF processed successfully! ' . count($processedInvoices) .
                    ' customer invoice(s) separated: ' .
                    implode(', ', array_column($processedInvoices, 'customer_code'))
                );

        } catch (\Exception $e) {
            Log::error('Invoice processing failed: ' . $e->getMessage(), [
                'file' => $request->file('invoice_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Failed to process PDF: ' . $e->getMessage()]);
        }
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
                        'pages' => $c['pages']
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
     * Parse customer data from extracted PDF text
     */
    private function parseCustomersFromText($text)
    {
        $customersData = [];
        $lines = explode("\n", $text);
        $currentCustomerIndex = null;
        $pageNumber = 1;
        $linesSincePageBreak = 0; 
        $lastInvoiceNumber = null;
        $lastCustomerIndex = null;
        $pageOwners = []; // Track which customer owns which page to prevent overlap
        
        ini_set('memory_limit', '512M'); 

        Log::info('Starting PDF parse', [
            'text_length' => strlen($text)
        ]);

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            // Handle page breaks - increment page number if \f is present anywhere
            if (strpos($line, "\f") !== false) {
                $pageNumber++;
                $linesSincePageBreak = 0; 
                $lastCustomerIndex = ($currentCustomerIndex !== null) ? $currentCustomerIndex : $lastCustomerIndex;
                $currentCustomerIndex = null; 
                $line = str_replace("\f", "", $line);
                $line = trim($line);
                if (empty($line)) continue;
            }

            $linesSincePageBreak++;

            // Skip empty lines after handling potential page break
            if (empty($line)) {
                continue;
            }

            // Look for various "Bill To:" patterns
            $billToPatterns = [
                '/Bill\s+To:\s*([^0-9\n]+?)\s+([0-9]{3,10})/i',  // Standard format
                '/Bill\s+To\s*([^0-9\n]+?)\s+([0-9]{3,10})/i',   // Without colon
                '/Billed\s+To:\s*([^0-9\n]+?)\s+([0-9]{3,10})/i', // Alternative wording
                '/Customer:\s*([^0-9\n]+?)\s+([0-9]{3,10})/i',    // Customer field
                '/To:\s*([^0-9\n]+?)\s+([0-9]{3,10})/i',         // Simple "To:" format
                '/Customer\s+Code:\s*([0-9]{3,10})\s+([^0-9\n]+)/i', // Code first format
                '/([^0-9\n]+?)\s+Customer\s+Code:\s*([0-9]{3,10})/i', // Name with customer code
                '/^([0-9]{3,10})\s*[\-|:| ]\s*([^0-9\n]+)$/i', // Code - Name format
                '/^([^0-9\n]+?)\s+([0-9]{3,10})$/i', // Name Code (at end of line)
                '/^([0-9]{3,10})\s+([^0-9\n]+)$/i', // Code Name (at start of line)
            ];

            // Filter out common page artifacts like "Page X of Y" from being processed as data
            if (preg_match('/Page\s+\d+\s+of\s+\d+/i', $line)) {
                continue;
            }

            // Log lines that might contain customer info for debugging
            $mightContainCustomer = stripos($line, 'bill') !== false ||
                stripos($line, 'customer') !== false ||
                preg_match('/\b\d{3,6}\b/', $line);

            // Look for customer headers
            foreach ($billToPatterns as $patternIndex => $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    // Handle different pattern formats based on capture group positions
                    if ($patternIndex == 5 || $patternIndex == 7 || $patternIndex == 9) { 
                        // Patterns where Code is FIRST match: [CODE] [DELIMITER] [NAME]
                        $customerCode = trim($matches[1]);
                        $customerName = trim($matches[2]);
                    } else {
                        // Patterns where Name is FIRST match: [NAME] [DELIMITER] [CODE]
                        $customerName = trim($matches[1]);
                        $customerCode = trim($matches[2]);
                    }

                    // Clean up customer name (remove extra spaces, special chars)
                    $customerName = preg_replace('/\s+/', ' ', $customerName);
                    $customerName = trim($customerName, ' ,-');

                    // Validate customer name - exclude invoice-related terms and generic words
                    if ($this->isValidCustomerName($customerName, $customerCode)) {
                        // Check if this page is already locked by another customer
                        // If so, and we found a NEW customer, they might be the real owner of this page
                        // but to be safe and avoid multi-customer pages, we only take the FIRST one found for mapping
                        $isPageLocked = isset($pageOwners[$pageNumber]) && $pageOwners[$pageNumber] !== $customerCode;

                        // Check if this customer already exists
                        $existingIndex = -1;
                        foreach ($customersData as $idx => $cust) {
                            if ($cust['customer_code'] === $customerCode) {
                                $existingIndex = $idx;
                                break;
                            }
                        }

                        if ($existingIndex !== -1) {
                            // Add page to existing customer ONLY if it's not locked by someone else
                            if (!$isPageLocked) {
                                if (!in_array($pageNumber, $customersData[$existingIndex]['pages'])) {
                                    $customersData[$existingIndex]['pages'][] = $pageNumber;
                                }
                                $pageOwners[$pageNumber] = $customerCode;
                            }
                            $currentCustomerIndex = $existingIndex;
                        } else {
                            // Extract phone number for new customer
                            $phoneNumber = $this->extractPhoneNumber($lines, $lineNum, $customerName);

                            // New customer
                            $newCustomer = [
                                'customer_code' => $customerCode,
                                'customer_name' => $customerName,
                                'customer_phone' => $phoneNumber,
                                'pages' => [],
                                'invoices' => []
                            ];
                            
                            // Add page to new customer ONLY if it's not locked by someone else
                            if (!$isPageLocked) {
                                $newCustomer['pages'][] = $pageNumber;
                                $pageOwners[$pageNumber] = $customerCode;
                            }
                            
                            $customersData[] = $newCustomer;
                            $currentCustomerIndex = count($customersData) - 1;
                        }

                        Log::info('Found customer', [
                            'line' => $line,
                            'name' => $customersData[$currentCustomerIndex]['customer_name'],
                            'code' => $customersData[$currentCustomerIndex]['customer_code'],
                            'page' => $pageNumber,
                            'is_locked' => $isPageLocked
                        ]);
                    } else {
                        Log::info('Rejected invalid customer name', [
                            'line' => $line,
                            'rejected_name' => $customerName,
                            'rejected_code' => $customerCode,
                        ]);
                    }
                    break; // Found a match, no need to try other patterns
                }
            }

            // Standalone customer detection is now handled within the main loop above using pattern indices 8, 9 and 10.
            
            // Look for invoice number patterns
            $invoicePatterns = [
                '/Invoice\s*(?:No\.?\s*|Number\s*|[:#])*\s*(\d+)/i',
                '/Inv\.?\s*(?:No\.?\s*|Number\s*|[:#])*\s*(\d+)/i',
                '/Invoice\s+([0-9]{4,10})/i', // Direct label followed by number
            ];

            foreach ($invoicePatterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $invoiceNumber = $matches[1];

                    // Continuity Logic: If no customer found on this page yet, check if it belongs to previous customer
                    if ($currentCustomerIndex === null && $lastInvoiceNumber && $invoiceNumber === $lastInvoiceNumber && $lastCustomerIndex !== null) {
                        $currentCustomerIndex = $lastCustomerIndex;
                        Log::info('Invoice continuity detected', ['invoice' => $invoiceNumber, 'customer' => $customersData[$currentCustomerIndex]['customer_code']]);
                    }

                    if ($currentCustomerIndex !== null) {
                        $lastInvoiceNumber = $invoiceNumber;
                        $totalAmount = null;
                        $amountPatterns = [
                            '/Total\s+Receivable\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Total\s+Amount\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Grand\s+Total\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Net\s+Amount\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Net\s+Payable\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Payable\s+Amount\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Total\s*[:\-]?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Balance\s*:?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Sub\s*Total\s*[:\-]?\s*([\d,\-]+(?:\.\d{2})?)/i',
                            '/Amount\s*[:\-]?\s*([\d,\-]+(?:\.\d{2})?)/i',
                        ];

                        for ($i = max(0, $lineNum - 10); $i < min(count($lines), $lineNum + 10); $i++) {
                            foreach ($amountPatterns as $amountPattern) {
                                if (preg_match($amountPattern, $lines[$i], $amountMatches)) {
                                    $totalAmount = str_replace(',', '', $amountMatches[1]);
                                    break 2; // Break both loops
                                }
                            }
                        }

                        $customersData[$currentCustomerIndex]['invoices'][] = [
                            'invoice_number' => $invoiceNumber,
                            'total_amount' => $totalAmount,
                            'page' => $pageNumber
                        ];

                        Log::info('Found invoice', [
                            'customer_code' => $customersData[$currentCustomerIndex]['customer_code'],
                            'invoice_number' => $invoiceNumber,
                            'total_amount' => $totalAmount,
                            'page' => $pageNumber
                        ]);
                        break; // Found a match, no need to try other patterns
                    }
                }
            }

            // Map current page to the current customer IF they own it and it's not claimed
            if ($currentCustomerIndex !== null && !isset($pageOwners[$pageNumber])) {
                if (!in_array($pageNumber, $customersData[$currentCustomerIndex]['pages'])) {
                    $customersData[$currentCustomerIndex]['pages'][] = $pageNumber;
                }
                $pageOwners[$pageNumber] = $customersData[$currentCustomerIndex]['customer_code'];
            }

            // Page breaks are now handled at the top of the loop using the \f character
        }

        // Filter out customers with no pages assigned
        $customersData = array_filter($customersData, function($customer) {
            return !empty($customer['pages']);
        });
        $customersData = array_values($customersData);

        Log::info('Parsed customers from PDF (after filtering)', [
            'total_customers' => count($customersData)
        ]);

        return $customersData;
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
    private function separateCustomerInvoices($originalPdfPath, $customerData, $originalFilename, $notes)
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

            // Create database record
            $invoice = Invoice::create([
                'original_filename' => $originalFilename,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'customer_phone' => $customerData['customer_phone'] ?? null,
                'invoice_number' => $customerData['invoices'][0]['invoice_number'] ?? null,
                'total_amount' => $customerData['invoices'][0]['total_amount'] ?? null,
                'pdf_path' => $customerPdfPath,
                'extracted_pages' => $pages,
                'page_range' => $pageRange,
                'processing_status' => 'completed',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
                'notes' => $notes
            ]);

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

            // Create failed record
            Invoice::create([
                'original_filename' => $originalFilename,
                'customer_code' => $customerData['customer_code'],
                'customer_name' => $customerData['customer_name'],
                'customer_phone' => $customerData['customer_phone'] ?? null,
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
                    
                    // Use template without any overrides to avoid positioning errors
                    $fpdi->useTemplate($tplId);

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
     * Send invoice via WhatsApp
     */
    public function sendWhatsApp(Request $request, $id)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20'
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

            // Determine phone number to use
            $phone = $request->phone ?? $invoice->customer_phone;

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is required'
                ], 400);
            }

            // Update phone number if provided and different
            if ($request->phone && $request->phone !== $invoice->customer_phone) {
                $cleanPhone = $this->cleanPhoneNumber($request->phone);
                if ($cleanPhone) {
                    $invoice->update(['customer_phone' => $cleanPhone]);
                    $phone = $cleanPhone;
                }
            }

            // Send via WhatsApp
            $whatsappService = new \App\Services\WhatsAppService();
            // detailed url
            $url = route('invoices.download', $invoice->id);
            $result = $whatsappService->sendInvoice($phone, $invoice, $url);

            if ($result['success']) {
                $invoice->update([
                    'whatsapp_sent_at' => now(),
                    'whatsapp_message_id' => $result['document_message_id'] ?? $result['text_message_id'] ?? null,
                    'whatsapp_status' => 'sent',
                    'whatsapp_error' => null
                ]);

                Log::info('Invoice sent via WhatsApp', [
                    'invoice_id' => $invoice->id,
                    'customer_code' => $invoice->customer_code,
                    'customer_name' => $invoice->customer_name,
                    'phone' => $phone,
                    'text_message_id' => $result['text_message_id'] ?? null,
                    'document_message_id' => $result['document_message_id'] ?? null,
                    'pdf_sent' => $result['pdf_sent'] ?? false,
                    'sent_by' => auth()->id()
                ]);

                $message = 'Invoice sent successfully via WhatsApp!';
                if (!($result['pdf_sent'] ?? false)) {
                    $message .= ' (Note: PDF could not be sent, only text message was delivered)';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'text_message_id' => $result['text_message_id'] ?? null,
                        'document_message_id' => $result['document_message_id'] ?? null,
                        'pdf_sent' => $result['pdf_sent'] ?? false
                    ]
                ]);
            } else {
                $invoice->update([
                    'whatsapp_status' => 'failed',
                    'whatsapp_error' => $result['error'] ?? 'Unknown Error'
                ]);

                Log::error('Failed to send invoice via WhatsApp', [
                    'invoice_id' => $invoice->id,
                    'customer_code' => $invoice->customer_code,
                    'phone' => $phone,
                    'error' => $result['error'] ?? 'Unknown Error'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send WhatsApp message: ' . ($result['error'] ?? 'Unknown Error')
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp send invoice error', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the invoice'
            ], 500);
        }
    }

    /**
     * Validate if a customer name is legitimate
     */
    private function isValidCustomerName($customerName, $customerCode)
    {
        // List of invalid customer names (invoice-related terms, generic words)
        $invalidNames = [
            'invoice',
            'inv',
            'bill',
            'billing',
            'receipt',
            'payment',
            'total',
            'subtotal',
            'amount',
            'tax',
            'charges',
            'date',
            'number',
            'no',
            'code',
            'id',
            'reference',
            'ref'
        ];

        $nameLower = strtolower(trim($customerName));

        // Check if name is in invalid list
        if (in_array($nameLower, $invalidNames)) {
            return false;
        }

        // Check if name is too short (less than 3 characters)
        if (strlen($customerName) < 3) {
            return false;
        }

        // Check if name is just numbers or special characters
        if (!preg_match('/[A-Za-z]/', $customerName)) {
            return false;
        }

        // Check if customer code is reasonable (3-10 digits, not an invoice number)
        if (!preg_match('/^\d{3,10}$/', $customerCode)) {
            return false;
        }

        // Additional validation: customer codes starting with 83 are likely invoice numbers
        // based on the pattern we saw (83264, 83347, 83349, etc.)
        if (preg_match('/^83\d{3,4}$/', $customerCode)) {
            Log::info('Rejected customer code that looks like invoice number', [
                'name' => $customerName,
                'code' => $customerCode
            ]);
            return false;
        }

        return true;
    }
}