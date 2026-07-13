<?php

namespace App\Http\Controllers;

use App\Models\ItemPrice;
use App\Models\PriceListUpload;
use App\Models\OracleItemPrice;
use App\Models\OraclePriceListUpdate;
use App\Imports\PriceListImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PriceListController extends Controller
{
    /**
     * Display the price list dashboard.
     */
    public function index(Request $request)
    {
        $query = ItemPrice::query();

        // Apply filters
        if ($request->filled('changed_only')) {
            $query->where('price_changed', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                  ->orWhere('item_description', 'like', "%{$search}%")
                  ->orWhere('price_list_name', 'like', "%{$search}%");
            });
        }

        // Get all prices and group by item_code
        $allPrices = $query->get();
        
        // Debug: Log unique price list names to see what we actually have
        $uniquePriceListNames = $allPrices->pluck('price_list_name')->unique()->sort();
        Log::info('Available price list names in database:', $uniquePriceListNames->toArray());
        
        // Define the 7 price lists in order (handle variations in naming)
        $priceListOrder = [
            'Karachi - Trade Price',
            'Karachi - Wholesale', 
            'Karachi - Corporate',
            'Lahore - Trade Price',
            'Lahore - Wholesale',
            'Lahore - Corporate',
            'QG HBM'
        ];
        
        // Also check for variations like "Karachi-Wholesale" vs "Karachi - Wholesale"
        $priceListVariations = [
            'Karachi - Trade Price' => ['Karachi - Trade Price', 'Karachi-Trade Price'],
            'Karachi - Wholesale' => ['Karachi - Wholesale', 'Karachi-Wholesale', 'Karachi Wholesale'],
            'Karachi - Corporate' => ['Karachi - Corporate', 'Karachi-Corporate', 'Karachi Corporate'],
            'Lahore - Trade Price' => ['Lahore - Trade Price', 'Lahore-Trade Price'],
            'Lahore - Wholesale' => ['Lahore - Wholesale', 'Lahore-Wholesale', 'Lahore Wholesale'],
            'Lahore - Corporate' => ['Lahore - Corporate', 'Lahore-Corporate', 'Lahore Corporate'],
            'QG HBM' => ['QG HBM', 'HBM', 'QG-HBM']
        ];

        // Group prices by item_code
        $groupedPrices = $allPrices->groupBy('item_code')->map(function ($itemPrices, $itemCode) use ($priceListOrder, $priceListVariations) {
            // Create array indexed by price list name
            $pricesByList = $itemPrices->keyBy('price_list_name');
            
            // Get the first item for basic info
            $firstItem = $itemPrices->first();
            
            // Build the matrix row
            $matrixRow = [
                'item_code' => $itemCode,
                'item_description' => $firstItem->item_description,
                'uom' => $firstItem->uom,
                'updated_at' => $itemPrices->max('price_updated_at'),
                'has_changes' => $itemPrices->where('price_changed', true)->count() > 0,
                'prices' => []
            ];
            
            // Fill in prices for each price list (check variations)
            foreach ($priceListOrder as $standardPriceListName) {
                $priceItem = null;
                
                // Check all variations for this price list
                $variations = $priceListVariations[$standardPriceListName] ?? [$standardPriceListName];
                foreach ($variations as $variation) {
                    $priceItem = $pricesByList->get($variation);
                    if ($priceItem) {
                        break; // Found a match, stop looking
                    }
                }
                
                $matrixRow['prices'][$standardPriceListName] = [
                    'id' => $priceItem->id ?? null,
                    'list_price' => $priceItem->list_price ?? null,
                    'previous_price' => $priceItem->previous_price ?? null,
                    'price_changed' => $priceItem->price_changed ?? false,
                    'price_updated_at' => $priceItem->price_updated_at ?? null,
                    'price_type' => $priceItem->price_type ?? null,
                    'exists' => $priceItem !== null,
                    'actual_name' => $priceItem->price_list_name ?? null // Store the actual name found
                ];
            }
            
            return $matrixRow;
        });

        // Apply price_type filter after grouping if needed
        if ($request->filled('price_type')) {
            $filterPriceType = $request->price_type;
            $groupedPrices = $groupedPrices->filter(function ($item) use ($filterPriceType, $priceListOrder) {
                // Check if any price in the row matches the filter
                foreach ($priceListOrder as $priceListName) {
                    $price = $item['prices'][$priceListName];
                    if ($price['exists'] && $price['price_type'] === $filterPriceType) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Convert to collection and paginate manually
        $perPage = 50;
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $paginatedItems = $groupedPrices->slice($offset, $perPage);
        $total = $groupedPrices->count();
        
        // Create paginator
        $prices = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems->values(), 
            $total, 
            $perPage, 
            $currentPage, 
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $recentUploads = PriceListUpload::latest()
                                       ->take(5)
                                       ->get();

        $stats = [
            'total_items' => ItemPrice::distinct('item_code')->count(),
            'changed_items' => ItemPrice::where('price_changed', true)->distinct('item_code')->count(),
            'corporate_items' => ItemPrice::where('price_type', 'corporate')->distinct('item_code')->count(),
            'wholesaler_items' => ItemPrice::where('price_type', 'wholesaler')->distinct('item_code')->count(),
            'hbm_items' => ItemPrice::where('price_type', 'hbm')->distinct('item_code')->count(),
        ];

        return view('admin.price-lists.index', compact('prices', 'recentUploads', 'stats', 'priceListOrder'));
    }

    /**
     * Show the upload form.
     */
    public function upload()
    {
        return view('admin.price-lists.upload');
    }

    /**
     * Process the uploaded CSV file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'price_list_file' => 'required|file|mimes:xlsx|max:10240',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $file = $request->file('price_list_file');
            $filename = 'price-lists/' . Str::uuid() . '.xlsx';
            
            // Store the file
            Storage::disk('local')->put($filename, file_get_contents($file));

            // Create upload record
            $upload = PriceListUpload::create([
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'notes' => $request->notes,
                'uploaded_at' => now(),
                'uploaded_by' => auth()->id(),
                'status' => 'processing',
            ]);

            // Process the Excel file using the PriceListImport class
            $import = new PriceListImport($upload);
            Excel::import($import, $file);

            // Update the upload record with final statistics
            $import->updateUploadRecord();

            $stats = $import->getStats();

            return redirect()->route('price-lists.index')
                           ->with('success', 'Price list uploaded successfully! ' . $stats['total'] . ' rows processed, ' . $stats['updated'] . ' updated, ' . $stats['new'] . ' new.');

        } catch (\Exception $e) {
            Log::error('Price list upload failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to process file: ' . $e->getMessage()]);
        }
    }

    /**
     * Process CSV file and update prices.
     */
    private function processCsvFile($file, PriceListUpload $upload)
    {
        $stats = [
            'total' => 0,
            'updated' => 0,
            'new' => 0,
            'errors' => 0
        ];

        $handle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($handle);
        
        if (!$headers) {
            throw new \Exception('Invalid CSV file - no headers found');
        }

        // Map headers to expected columns
        $columnMap = [];
        foreach ($headers as $index => $header) {
            $columnMap[strtolower(trim($header))] = $index;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $stats['total']++;
            
            try {
                // Extract data from row
                $itemCode = trim($row[$columnMap['item_code'] ?? 0] ?? '');
                $priceListId = trim($row[$columnMap['price_list_id'] ?? 1] ?? '');
                $priceListName = trim($row[$columnMap['price_list_name'] ?? 2] ?? '');
                $newPrice = (float) ($row[$columnMap['list_price'] ?? 5] ?? 0);
                $priceType = strtolower(trim($row[$columnMap['price_type'] ?? 6] ?? ''));

                if (empty($itemCode) || empty($priceListId) || $newPrice <= 0) {
                    $stats['errors']++;
                    continue;
                }

                // Find existing price record
                $existingPrice = ItemPrice::where('item_code', $itemCode)
                    ->where('price_list_id', $priceListId)
                    ->first();

                if ($existingPrice) {
                    // Check if price has changed
                    if ((float) $existingPrice->list_price !== $newPrice) {
                        $existingPrice->update([
                            'previous_price' => $existingPrice->list_price,
                            'list_price' => $newPrice,
                            'price_changed' => true,
                            'price_updated_at' => now(),
                            'price_updated_by' => auth()->id(),
                            'price_type' => $priceType,
                        ]);
                        $stats['updated']++;
                    }
                } else {
                    // Create new price record
                    ItemPrice::create([
                        'price_list_id' => $priceListId,
                        'price_list_name' => $priceListName,
                        'item_code' => $itemCode,
                        'list_price' => $newPrice,
                        'price_type' => $priceType,
                        'price_changed' => false,
                        'price_updated_at' => now(),
                        'price_updated_by' => auth()->id(),
                        'start_date_active' => now(),
                    ]);
                    $stats['new']++;
                }

            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Price import row error: ' . $e->getMessage());
            }
        }

        fclose($handle);
        return $stats;
    }

    /**
     * Update a single price.
     */
    public function updatePrice(Request $request, ItemPrice $price)
    {
        $request->validate([
            'list_price' => 'required|numeric|min:0',
        ]);

        $oldPrice = $price->list_price;
        $newPrice = $request->list_price;

        if ($oldPrice != $newPrice) {
            $price->update([
                'previous_price' => $oldPrice,
                'list_price' => $newPrice,
                'price_changed' => true,
                'price_updated_at' => now(),
                'price_updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully',
                'new_price' => number_format($newPrice, 2),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No changes detected',
        ]);
    }

    /**
     * Get upload history.
     */
    public function uploadHistory()
    {
        $uploads = PriceListUpload::latest()->paginate(20);
        return view('admin.price-lists.history', compact('uploads'));
    }

    /**
     * Download sample CSV template.
     */
    public function downloadTemplate()
    {
        $data = [
            ['item_code', 'price_list_id', 'price_list_name', 'item_description', 'uom', 'list_price', 'price_type'],
            ['ITEM001', 'PL001', 'Corporate Price List', 'Sample Item 1', 'PCS', '100.00', 'corporate'],
            ['ITEM002', 'PL002', 'Wholesaler Price List', 'Sample Item 2', 'KG', '250.50', 'wholesaler'],
            ['ITEM003', 'PL003', 'HBM Price List', 'Sample Item 3', 'LTR', '75.25', 'hbm']
        ];

        $filename = 'price-list-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Sync Oracle item prices from QG_POS_ITEM_PRICE view
     */
    public function syncOraclePrices(Request $request)
    {
        try {
            // Get Oracle prices with active flag
            $oraclePrices = OracleItemPrice::active()
                ->limit(1000)
                ->get();

            $syncedPrices = [];
            foreach ($oraclePrices as $oraclePrice) {
                $syncedPrices[] = [
                    'item_code' => $oraclePrice->item_code,
                    'item_description' => $oraclePrice->item_description,
                    'oracle_price' => (float) $oraclePrice->list_price,
                    'price_list_name' => $oraclePrice->price_list_name,
                    'uom' => $oraclePrice->uom,
                    'is_active' => $oraclePrice->is_active,
                ];
            }

            // Store synced prices in session for comparison
            session(['synced_oracle_prices' => $syncedPrices]);

            return response()->json([
                'success' => true,
                'message' => 'Oracle prices synced successfully',
                'data' => $syncedPrices,
                'total_count' => count($syncedPrices),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync Oracle prices: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync Oracle prices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process Excel with Oracle price comparison
     */
    public function processWithOracleComparison(Request $request)
    {
        $request->validate([
            'price_list_file' => 'required|file|mimes:xlsx|max:10240',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $file = $request->file('price_list_file');
            $filename = 'price-lists/' . Str::uuid() . '.xlsx';
            
            // Store the file
            Storage::disk('local')->put($filename, file_get_contents($file));

            // Get synced Oracle prices from session
            $oraclePrices = session('synced_oracle_prices', []);
            $oraclePricesMap = collect($oraclePrices)->keyBy('item_code');

            // Process the Excel file using the PriceListImport class
            $import = new PriceListImport(null, true); // Pass true for Oracle comparison mode
            Excel::import($import, $file);

            $excelData = $import->getProcessedData();
            
            // Debug: Log the processed data
            Log::info('Excel processed data:', [
                'count' => count($excelData),
                'first_item' => $excelData[0] ?? 'No data',
                'oracle_prices_count' => count($oraclePrices),
                'import_stats' => $import->getStats(),
            ]);

            // Check if we have any data to work with
            if (empty($excelData)) {
                Log::warning('No data processed from Excel file', [
                    'import_stats' => $import->getStats(),
                    'oracle_prices_count' => count($oraclePrices)
                ]);
                
                return back()->withErrors(['error' => 'No valid data found in Excel file. Please check the file format and try again.']);
            }
            
            // Compare with Oracle prices and add color coding
            $comparisonData = [];
            foreach ($excelData as $item) {
                $itemCode = $item['item_code'];
                $newPrice = (float) $item['list_price'];
                
                $oracleItem = $oraclePricesMap->get($itemCode);
                $oraclePrice = $oracleItem ? (float) $oracleItem['oracle_price'] : null;
                
                // Determine background color and status
                $backgroundColor = 'white';
                $priceStatus = 'new';
                $priceDifference = null;
                $priceDifferencePercent = null;
                
                if ($oraclePrice !== null) {
                    $priceDifference = $newPrice - $oraclePrice;
                    $priceDifferencePercent = $oraclePrice > 0 ? (($priceDifference / $oraclePrice) * 100) : 0;
                    
                    if (abs($priceDifference) < 0.01) {
                        $backgroundColor = '#f8f9fa'; // Light gray for same price
                        $priceStatus = 'same';
                    } elseif ($priceDifference > 0) {
                        $backgroundColor = '#fff3cd'; // Light yellow for price increase
                        $priceStatus = 'increased';
                    } else {
                        $backgroundColor = '#f8d7da'; // Light red for price decrease
                        $priceStatus = 'decreased';
                    }
                } else {
                    $backgroundColor = '#d1ecf1'; // Light blue for new items
                    $priceStatus = 'new';
                }
                
                $comparisonData[] = array_merge($item, [
                    'oracle_price' => $oraclePrice,
                    'price_difference' => $priceDifference,
                    'price_difference_percent' => $priceDifferencePercent ? round($priceDifferencePercent, 2) : null,
                    'background_color' => $backgroundColor,
                    'price_status' => $priceStatus,
                ]);
            }

            // Store comparison data in session
            session(['price_comparison_data' => $comparisonData]);

            // Create upload record
            $upload = PriceListUpload::create([
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'notes' => $request->notes,
                'uploaded_at' => now(),
                'uploaded_by' => auth()->id(),
                'status' => 'completed',
                'total_rows' => count($comparisonData),
                'updated_rows' => 0,
                'new_rows' => count($comparisonData),
                'error_rows' => $import->getStats()['errors'] ?? 0,
            ]);

            return redirect()->route('price-lists.review-comparison')
                           ->with('success', 'Excel file processed and compared with Oracle prices successfully!');

        } catch (\Exception $e) {
            Log::error('Price list Oracle comparison failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to process file: ' . $e->getMessage()]);
        }
    }

    /**
     * Show price comparison review page
     */
    public function reviewComparison()
    {
        $comparisonData = session('price_comparison_data', []);
        
        if (empty($comparisonData)) {
            Log::warning('No comparison data in session');
            return redirect()->route('price-lists.index')
                           ->with('error', 'No comparison data found. Please sync Oracle prices and upload an Excel file first.');
        }

        // Generate summary statistics
        $summary = [
            'total_items' => count($comparisonData),
            'new_items' => count(array_filter($comparisonData, fn($item) => $item['price_status'] === 'new')),
            'same_price' => count(array_filter($comparisonData, fn($item) => $item['price_status'] === 'same')),
            'price_increased' => count(array_filter($comparisonData, fn($item) => $item['price_status'] === 'increased')),
            'price_decreased' => count(array_filter($comparisonData, fn($item) => $item['price_status'] === 'decreased')),
        ];

        return view('admin.price-lists.review-comparison', compact('comparisonData', 'summary'));
    }

    /**
     * Update Oracle prices via QG_PRICE_LIST_UPDATES table
     */
    public function updateOraclePrices(Request $request)
    {
        // Handle single entry from Oracle entry form
        if ($request->has('single_entry') && $request->single_entry) {
            $request->validate([
                'entry_data' => 'required|array',
                'entry_data.item_code' => 'required|string',
                'entry_data.new_price' => 'required|numeric|min:0',
                'entry_data.currency_code' => 'nullable|string',
                'entry_data.start_date' => 'nullable|date|after_or_equal:today',
            ]);
            
            $entryData = $request->entry_data;
            $startDate = $entryData['start_date'] ? \Carbon\Carbon::parse($entryData['start_date']) : now()->addDay();
            
            $insertData = [[
                'list_header_id' => $entryData['list_header_id'] ?? null,
                'list_line_id' => $entryData['list_line_id'] ?? null,
                'item_code' => $entryData['item_code'],
                'new_price' => $entryData['new_price'],
                'currency_code' => $entryData['currency_code'] ?? 'PKR',
                'start_date' => $startDate,
                'end_date' => $entryData['end_date'] ? \Carbon\Carbon::parse($entryData['end_date']) : null,
                'processed_flag' => 'N',
                'error_message' => null,
                'processed_date' => null,
            ]];
        } else {
            // Handle bulk updates from comparison review
            $request->validate([
                'selected_items' => 'required|array|min:1',
                'start_date' => 'nullable|date|after_or_equal:today',
            ]);
        }

        try {
            // If not single entry, handle bulk updates from comparison review
            if (!($request->has('single_entry') && $request->single_entry)) {
                $comparisonData = session('price_comparison_data', []);
                $selectedItems = $request->selected_items;
                $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->addDay();
                
                if (empty($comparisonData)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No comparison data found.',
                    ], 400);
                }

                // Filter only selected items
                $itemsToUpdate = array_filter($comparisonData, function($item) use ($selectedItems) {
                    return in_array($item['item_code'], $selectedItems);
                });

                if (empty($itemsToUpdate)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No valid items selected for update.',
                    ], 400);
                }

                // Prepare data for Oracle QG_PRICE_LIST_UPDATES table
                $insertData = [];
                foreach ($itemsToUpdate as $item) {
                    $insertData[] = [
                        'list_header_id' => null,
                        'list_line_id' => null,
                        'item_code' => $item['item_code'],
                        'new_price' => $item['list_price'],
                        'currency_code' => 'PKR',
                        'start_date' => $startDate,
                        'end_date' => null,
                        'processed_flag' => 'N',
                        'error_message' => null,
                        'processed_date' => null,
                    ];
                }
            }

            // Execute Oracle price update workflow in transaction
            DB::connection('oracle')->transaction(function () use ($insertData, $request) {
                $today = now();
                $endDate = $today->copy()->subDay(); // Yesterday to end current prices
                
                // Step 1: End-date current active prices for items being updated
                $itemCodes = collect($insertData)->pluck('item_code')->unique()->toArray();
                
                if (!empty($itemCodes)) {
                    Log::info('End-dating current prices for items', ['item_codes' => $itemCodes, 'end_date' => $endDate]);
                    
                    // Update active prices to set end_date_active to system date
                    foreach ($itemCodes as $itemCode) {
                        DB::connection('oracle')
                            ->table('apps.qg_pos_item_price')
                            ->where('item_code', $itemCode)
                            ->where(function($query) use ($today) {
                                $query->whereNull('end_date_active') // Only active prices (no end date)
                                      ->orWhere('end_date_active', '>', $today); // Or future end dates
                            })
                            ->update(['end_date_active' => $endDate]);
                    }
                }
                
                // Step 2: Insert new price updates with future start dates
                foreach (array_chunk($insertData, 100) as $chunk) {
                    DB::connection('oracle')
                        ->table('apps.qg_price_list_updates')
                        ->insert($chunk);
                }
            });

            // Log the operation
            Log::info('Price updates with date management submitted to Oracle', [
                'total_updates' => count($insertData),
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
                'single_entry' => $request->has('single_entry') && $request->single_entry,
                'start_date' => $insertData[0]['start_date'] ?? null,
            ]);

            // Clear session data only for bulk updates
            if (!($request->has('single_entry') && $request->single_entry)) {
                session()->forget(['price_comparison_data', 'synced_oracle_prices']);
            }

            return response()->json([
                'success' => true,
                'message' => count($insertData) . ' price update' . (count($insertData) > 1 ? 's' : '') . ' submitted to Oracle successfully! Current prices end-dated and new prices scheduled.',
                'redirect_url' => $request->has('single_entry') ? null : route('price-lists.index'),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update Oracle prices: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Oracle prices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get price update status from Oracle
     */
    public function getUpdateStatus(Request $request)
    {
        try {
            $updates = DB::connection('oracle')
                ->table('apps.qg_price_list_updates')
                ->orderBy('processed_date', 'desc')
                ->limit(100)
                ->get();

            $statusData = $updates->map(function ($update) {
                return [
                    'item_code' => $update->item_code,
                    'new_price' => $update->new_price,
                    'currency_code' => $update->currency_code,
                    'processed_flag' => $update->processed_flag,
                    'error_message' => $update->error_message,
                    'processed_date' => $update->processed_date,
                    'status_text' => $this->getStatusText($update->processed_flag),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $statusData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get update status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get update status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit all updated prices from current price list to Oracle
     */
    public function enterToOracle(Request $request)
    {
        try {
            // Get all items with price changes (updated prices)
            $updatedPrices = ItemPrice::where('price_changed', true)
                ->where('price_updated_at', '>=', now()->subDays(30)) // Only recent updates
                ->get();

            if ($updatedPrices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No updated prices found to submit to Oracle.',
                ], 400);
            }

            // Prepare data for Oracle QG_PRICE_LIST_UPDATES table
            $insertData = [];
            foreach ($updatedPrices as $priceItem) {
                $insertData[] = [
                    'list_header_id' => null,
                    'list_line_id' => null,
                    'item_code' => $priceItem->item_code,
                    'new_price' => $priceItem->list_price,
                    'currency_code' => 'PKR',
                    'start_date' => $priceItem->price_updated_at ?? now(),
                    'end_date' => null,
                    'processed_flag' => 'N',
                    'error_message' => null,
                    'processed_date' => null,
                ];
            }

            // Insert into Oracle table in batches
            DB::connection('oracle')->transaction(function () use ($insertData) {
                foreach (array_chunk($insertData, 100) as $chunk) {
                    DB::connection('oracle')
                        ->table('apps.qg_price_list_updates')
                        ->insert($chunk);
                }
            });

            // Log the operation
            Log::info('All updated prices submitted to Oracle', [
                'total_submitted' => count($insertData),
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All updated prices submitted to Oracle successfully!',
                'total_submitted' => count($insertData),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to submit updated prices to Oracle: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit prices to Oracle: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get status text for processed flag
     */
    private function getStatusText($flag): string
    {
        switch ($flag) {
            case 'Y':
                return 'Processed Successfully';
            case 'E':
                return 'Error';
            case 'N':
            default:
                return 'Pending';
        }
    }
}       