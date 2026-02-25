<?php

namespace App\Http\Controllers;

use App\Models\ItemPrice;
use App\Models\PriceListUpload;
use App\Models\OracleItemPrice;
use App\Models\OraclePriceListUpdate;
use App\Imports\PriceListImport;
use App\Exports\PriceComparisonExport;
use App\Exports\PriceListsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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

        if ($request->filled('city')) {
            $city = $request->city;
            $query->where(function($q) use ($city) {
                if ($city === 'karachi') {
                    $q->where('price_list_name', 'like', 'Karachi%');
                } elseif ($city === 'lahore') {
                    $q->where('price_list_name', 'like', 'Lahore%');
                }
            });
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

        // Group prices by item_code + uom combination to handle different UOMs correctly
        $groupedPrices = $allPrices->groupBy(function($item) {
            return $item->item_code . '|' . $item->uom;
        })->map(function ($itemPrices, $itemKey) use ($priceListOrder, $priceListVariations) {
            // Create array indexed by price list name
            $pricesByList = $itemPrices->keyBy('price_list_name');

            // Get the first item for basic info
            $firstItem = $itemPrices->first();

            // Build the matrix row
            $matrixRow = [
                'item_code' => $firstItem->item_code,
                'item_description' => $firstItem->item_description,
                'uom' => $firstItem->uom, // Now correctly represents the UOM for this specific row
                'updated_at' => $itemPrices->max('price_updated_at'),
                'effective_date' => $firstItem->start_date_active,
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
            'total_items' => ItemPrice::distinct('id')->count(),
            'changed_items' => ItemPrice::where('price_changed', true)->distinct('item_code')->count(),
            'corporate_items' => ItemPrice::where('price_type', 'corporate')->distinct('item_code')->count(),
            'trade_items' => ItemPrice::where('price_type', 'trade')->distinct('item_code')->count(),
            'wholesaler_items' => ItemPrice::where('price_type', 'wholesaler')->distinct('item_code')->count(),
            'hbm_items' => ItemPrice::where('price_type', 'hbm')->distinct('item_code')->count(),
            'last_sync_date' => ItemPrice::max('updated_at'),
            'last_price_updated_date' => ItemPrice::where('price_changed', true)->max('price_updated_at'),
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

            // Set longer timeout for large files
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');

            // Process the Excel file using the PriceListImport class - this will update item_prices table
            $import = new PriceListImport($upload); // Regular mode to update database
            
            // Clear any previous debug data
            unset($GLOBALS['excel_debug_data']);
            unset($GLOBALS['excel_debug_columns']);
            unset($GLOBALS['excel_debug_mapping']);
            
            // Reset model call counter
            $GLOBALS['excel_model_calls'] = 0;
            
            $fileInfo = [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType()
            ];
            
            Log::info('Starting Excel import', $fileInfo);
            
            // Clear relevant caches before processing new file
            Cache::flush(); // Clear all caches to prevent old data interference
            
            // Optimize for large files
            ini_set('memory_limit', '2G'); // Increase memory limit
            ini_set('max_execution_time', 1800); // 30 minutes timeout
            
            // Store file info for console output
            $GLOBALS['excel_file_info'] = $fileInfo;
            
            // Read raw Excel file content for debugging
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();
                
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                
                Log::info('Raw Excel file analysis', [
                    'total_rows' => $highestRow,
                    'highest_column' => $highestColumn,
                    'worksheet_title' => $worksheet->getTitle()
                ]);
                
                // Read first 5 rows raw
                $rawRows = [];
                for ($row = 1; $row <= min(5, $highestRow); $row++) {
                    $rowData = [];
                    for ($col = 'A'; $col <= $highestColumn; $col++) {
                        $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                        $rowData[$col] = $cellValue;
                    }
                    $rawRows[$row] = $rowData;
                    Log::info("Raw Excel row {$row}", ['data' => $rowData]);
                }
                
                $GLOBALS['excel_raw_analysis'] = [
                    'total_rows' => $highestRow,
                    'highest_column' => $highestColumn,
                    'raw_rows' => $rawRows
                ];
                
            } catch (\Exception $e) {
                Log::error('Failed to read raw Excel content', [
                    'error' => $e->getMessage()
                ]);
                
                $GLOBALS['excel_raw_analysis'] = [
                    'error' => 'Failed to read raw Excel: ' . $e->getMessage()
                ];
            }
            
            try {
                // Process files with optimizations for large files
                $fileSize = $file->getSize();
                $estimatedRows = $fileSize / 1024; // Rough estimation
                
                if ($fileSize > 5 * 1024 * 1024 || $estimatedRows > 1000) {
                    Log::info('Large file detected, using optimized processing', [
                        'file_size' => $fileSize,
                        'estimated_rows' => $estimatedRows
                    ]);
                    
                    // Extend timeout for large files
                    ini_set('max_execution_time', 3600); // 60 minutes for very large files
                    
                    // Use import with row limit for large files
                    $import = new PriceListImport($upload, 10000); // Limit to 10,000 rows
                } else {
                    // Process smaller files normally
                    $import = new PriceListImport($upload);
                }
                
                Excel::import($import, $file);
            } catch (\Illuminate\Database\QueryException $e) {
                if (strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
                    Log::error('MySQL connection lost during import, attempting to continue...', [
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);
                    
                    // Try to reconnect and continue
                    DB::reconnect();
                    
                    // Update upload status
                    $upload->update([
                        'status' => 'completed_with_errors',
                        'error_details' => ['mysql_timeout' => 'Database connection lost during processing']
                    ]);
                    
                    return redirect()->route('price-lists.index')
                               ->with('error', 'Upload partially completed. Database connection was lost during processing. Please check the results and re-upload if needed.');
                }
                throw $e;
            }

            // Update the upload record with final statistics
            $import->updateUploadRecord();
            $stats = $import->getStats();
            
            if ($stats['total'] == 0) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No valid data found in Excel file. Please check the file format and required columns.',
                        'errors' => ['error' => ['No valid data found in Excel file. Please check the file format and required columns.']],
                        'debug_data' => $import->getDebugData(),
                        'import_stats' => $stats
                    ], 400);
                }
                return back()->withErrors(['error' => 'No valid data found in Excel file. Please check the file format and required columns.']);
            }

            // Debug Excel processing
            Log::info('Excel processing completed', [
                'upload_id' => $upload->id,
                'import_stats' => $stats
            ]);
            
            if ($stats['total'] == 0) {
                // No items processed, just return success
                Log::info('No items processed, redirecting to index with success');
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Price list uploaded successfully! ' . $stats['total'] . ' rows processed, ' . $stats['updated'] . ' updated, ' . $stats['new'] . ' new.',
                        'redirect_url' => route('price-lists.index')
                    ]);
                }
                
                return redirect()->route('price-lists.index')
                               ->with('success', 'Price list uploaded successfully! ' . $stats['total'] . ' rows processed, ' . $stats['updated'] . ' updated, ' . $stats['new'] . ' new.');
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Excel file processed and prices compared with local database. Review the changes and click "Send to Oracle" to update Oracle prices.',
                    'upload_id' => $upload->id,
                    'redirect_url' => route('price-lists.review-comparison', ['upload_id' => $upload->id]),
                    'debug_data' => $import->getDebugData(),
                    'import_stats' => $stats
                ]);
            }

            return redirect()->route('price-lists.review-comparison', ['upload_id' => $upload->id])
                           ->with('success', 'Excel file processed and prices compared with local database. Review the changes and click "Send to Oracle" to update Oracle prices.');

        } catch (\Exception $e) {
            Log::error('Price list upload failed: ' . $e->getMessage(), [
                'file' => $request->file('price_list_file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process file: ' . $e->getMessage(),
                    'errors' => ['error' => ['Failed to process file: ' . $e->getMessage()]]
                ], 500);
            }
            
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
            ['Item Code', 'Price List Name', 'Major_Desc', 'Minor_Desc', 'Sub Minor Desc', 'Brand', 'Item Description', 'UOM', 'List Price', 'Start Date Active', 'End Date Active', 'System Last Update Date', 'System Creation Date'],
            ['ITEM001', 'Karachi - Corporate', 'Electronics', 'Mobile Phones', 'Smartphones', 'Samsung', 'Sample Corporate Item 1', 'PCS', '100.00', '2024-01-01', '', '2024-01-01 10:00:00', '2024-01-01 10:00:00'],
            ['ITEM002', 'Karachi - Wholesale', 'Food', 'Dairy', 'Milk Products', 'Nestle', 'Sample Wholesale Item 2', 'KG', '250.50', '2024-01-01', '', '2024-01-01 10:00:00', '2024-01-01 10:00:00'],
            ['ITEM003', 'QG HBM', 'Beverages', 'Soft Drinks', 'Carbonated', 'Pepsi', 'Sample HBM Item 3', 'LTR', '75.25', '2024-01-01', '', '2024-01-01 10:00:00', '2024-01-01 10:00:00']
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
     * Sync Oracle item prices from QG_POS_ITEM_PRICE view and save to database
     */
    public function syncOraclePrices(Request $request)
    {
        // Release session lock to prevent blocking valid requests (like keep-alive)
        session()->save();

        try {
            $batchSize = 5000;
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $batchSize;
            
            // Get total count for progress tracking
            $totalCount = OracleItemPrice::active()->count();
            $totalPages = ceil($totalCount / $batchSize);
            
            Log::info('Oracle sync batch processing', [
                'page' => $page,
                'batch_size' => $batchSize,
                'offset' => $offset,
                'total_count' => $totalCount,
                'total_pages' => $totalPages
            ]);

            // Get Oracle prices for current batch
            $oraclePrices = OracleItemPrice::active()
                ->skip($offset)
                ->take($batchSize)
                ->get();

            // Process Oracle prices in batches and update/insert into item_prices table
            $processedCount = 0;
            $updatedCount = 0;
            $newCount = 0;
            
            foreach ($oraclePrices->chunk(100) as $chunk) {
                foreach ($chunk as $oraclePrice) {
                    try {
                        // Log Oracle data received (for first 20 items in each batch)
                        if ($processedCount < 20) {
                            Log::info('Oracle data received', [
                                'batch' => $page,
                                'item_count' => $processedCount + 1,
                                'oracle_data' => [
                                    'item_code' => $oraclePrice->item_code,
                                    'price_list_name' => $oraclePrice->price_list_name,
                                    'list_price' => $oraclePrice->list_price,
                                    'item_description' => $oraclePrice->item_description,
                                    'uom' => $oraclePrice->uom,
                                    'start_date_active' => $oraclePrice->start_date_active ?? 'NULL',
                                    'end_date_active' => $oraclePrice->end_date_active ?? 'NULL'
                                ]
                            ]);
                        }

                        // Find existing price record by item_code + price_list_name + uom
                        $existingPrice = ItemPrice::where('item_code', $oraclePrice->item_code)
                            ->where('price_list_name', $oraclePrice->price_list_name)
                            ->where('uom', $oraclePrice->uom)
                            ->first();

                        // Log both Oracle and existing MySQL data for comparison (first 20 items per batch)
                        if ($processedCount < 20) {
                            Log::info('Oracle vs MySQL comparison', [
                                'batch' => $page,
                                'item_count' => $processedCount + 1,
                                'search_criteria' => [
                                    'item_code' => $oraclePrice->item_code,
                                    'price_list_name' => $oraclePrice->price_list_name,
                                    'uom' => $oraclePrice->uom
                                ],
                                'oracle_data' => [
                                    'list_price' => $oraclePrice->list_price,
                                    'item_description' => $oraclePrice->item_description,
                                    'start_date_active' => $oraclePrice->start_date_active ?? 'NULL',
                                    'end_date_active' => $oraclePrice->end_date_active ?? 'NULL'
                                ],
                                'existing_mysql_data' => $existingPrice ? [
                                    'id' => $existingPrice->id,
                                    'list_price' => $existingPrice->list_price,
                                    'previous_price' => $existingPrice->previous_price,
                                    'item_description' => $existingPrice->item_description,
                                    'price_changed' => $existingPrice->price_changed,
                                    'updated_at' => $existingPrice->updated_at,
                                    'created_at' => $existingPrice->created_at
                                ] : 'NO_EXISTING_RECORD',
                                'action_needed' => $existingPrice ?
                                    ((float) $existingPrice->list_price !== (float) $oraclePrice->list_price ? 'UPDATE_PRICE' : 'NO_CHANGE') :
                                    'CREATE_NEW'
                            ]);
                        }

                        if ($existingPrice) {
                            // Update existing record if price differs
                            $newPrice = (float) $oraclePrice->list_price;
                            $oldPrice = (float) $existingPrice->list_price;

                            if ($oldPrice !== $newPrice) {
                                $updateData = [
                                    'previous_price' => $existingPrice->list_price,
                                    'list_price' => $newPrice,
                                    'price_changed' => false, // Oracle sync doesn't mark as changed
                                    'item_description' => $oraclePrice->item_description,
                                    'uom' => $oraclePrice->uom,
                                    'updated_at' => now(), // Track Oracle sync time
                                ];

                                $existingPrice->update($updateData);

                                // Log MySQL update (for first 20 updates in each batch)
                                if ($updatedCount < 20) {
                                    Log::info('MySQL item_prices UPDATED', [
                                        'batch' => $page,
                                        'update_count' => $updatedCount + 1,
                                        'mysql_record_id' => $existingPrice->id,
                                        'matching_criteria' => [
                                            'item_code' => $oraclePrice->item_code,
                                            'price_list_name' => $oraclePrice->price_list_name,
                                            'uom' => $oraclePrice->uom
                                        ],
                                        'price_change' => [
                                            'old_price' => $oldPrice,
                                            'new_price' => $newPrice,
                                            'difference' => $newPrice - $oldPrice
                                        ],
                                        'complete_update_data' => $updateData,
                                        'success' => 'RECORD_UPDATED'
                                    ]);
                                }

                                $updatedCount++;
                            } else {
                                // Log when prices are the same (for first 10 same prices)
                                if ($processedCount < 10) {
                                    Log::info('MySQL item_prices NO CHANGE', [
                                        'batch' => $page,
                                        'mysql_record_id' => $existingPrice->id,
                                        'item_code' => $oraclePrice->item_code,
                                        'price_list_name' => $oraclePrice->price_list_name,
                                        'uom' => $oraclePrice->uom,
                                        'same_price' => $oldPrice,
                                        'action' => 'TIMESTAMP_ONLY_UPDATE'
                                    ]);
                                }

                                // Update timestamp even if price is same to track Oracle sync
                                $existingPrice->touch();
                            }
                        } else {
                            // Create new price record
                            $createData = [
                                'item_code' => $oraclePrice->item_code,
                                'price_list_name' => $oraclePrice->price_list_name,
                                'price_list_id' => $this->determinePriceListId($oraclePrice->price_list_name), // Add price_list_id
                                'list_price' => (float) $oraclePrice->list_price,
                                'item_description' => $oraclePrice->item_description,
                                'uom' => $oraclePrice->uom,
                                'price_type' => $this->determinePriceType($oraclePrice->price_list_name),
                                'price_changed' => false, // Oracle sync doesn't mark as changed
                                'start_date_active' => now(),
                            ];

                            ItemPrice::create($createData);

                            // Log MySQL creation (for first 10 new items in each batch)
                            if ($newCount < 10) {
                                Log::info('MySQL item_prices created', [
                                    'batch' => $page,
                                    'new_count' => $newCount + 1,
                                    'item_code' => $oraclePrice->item_code,
                                    'price_list_name' => $oraclePrice->price_list_name,
                                    'created_data' => $createData
                                ]);
                            }

                            $newCount++;
                        }

                        $processedCount++;

                    } catch (\Exception $e) {
                        Log::error('Failed to sync Oracle price item', [
                            'batch' => $page,
                            'item_code' => $oraclePrice->item_code,
                            'price_list_name' => $oraclePrice->price_list_name,
                            'oracle_data' => [
                                'list_price' => $oraclePrice->list_price,
                                'item_description' => $oraclePrice->item_description,
                                'uom' => $oraclePrice->uom
                            ],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }

            // Count items updated in this sync session (use updated_at as proxy for sync time)
            $totalSyncedCount = ItemPrice::where('updated_at', '>=', now()->subMinutes(5))->count();

            $hasMorePages = $page < $totalPages;
            
            // Calculate actual progress based on items synced vs total items
            $actualProgress = round(($totalSyncedCount / $totalCount) * 100, 1);

            return response()->json([
                'success' => true,
                'message' => $hasMorePages
                    ? "Syncing Oracle prices - batch {$page} of {$totalPages} completed"
                    : "Oracle prices sync completed! Total {$totalCount} items processed to database.",
                'batch_info' => "Batch {$page} of {$totalPages}",
                'batch_processed' => $processedCount,
                'batch_updated' => $updatedCount,
                'batch_new' => $newCount,
                'total_synced' => $totalSyncedCount,
                'total_count' => $totalCount,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more_pages' => $hasMorePages,
                'progress_percentage' => $actualProgress,
                'progress_text' => "Processed batch {$page} of {$totalPages} ({$processedCount} items this batch, {$totalSyncedCount} total)"
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

            // Set longer timeout for large files
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');

            // Process the Excel file using the PriceListImport class
            $import = new PriceListImport(null, true); // Pass true for Oracle comparison mode
            
            try {
                Excel::import($import, $file);
            } catch (\Illuminate\Database\QueryException $e) {
                if (strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
                    Log::error('MySQL connection lost during Oracle comparison import...', [
                        'file' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);
                    
                    DB::reconnect();
                    
                    return back()->withErrors(['error' => 'Database connection was lost during processing. Please try with a smaller file or contact support.']);
                }
                throw $e;
            }

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
    public function reviewComparison(Request $request)
    {
        $uploadId = $request->get('upload_id');
        
        if (!$uploadId) {
            Log::warning('No upload_id provided to review comparison');
            return redirect()->route('price-lists.index')
                           ->with('error', 'No upload data found. Please upload an Excel file first.');
        }
        
        // Get upload record
        $upload = PriceListUpload::find($uploadId);
        if (!$upload) {
            Log::warning('Upload record not found', ['upload_id' => $uploadId]);
            return redirect()->route('price-lists.index')
                           ->with('error', 'Upload record not found. Please upload an Excel file first.');
        }
        
        // Get comparison data from database using upload_id
        $comparisonData = $this->prepareComparisonData($uploadId);
        
        Log::info('Review comparison from database', [
            'upload_id' => $uploadId,
            'comparison_data_count' => count($comparisonData),
            'first_comparison_item' => !empty($comparisonData) ? $comparisonData[0] : null
        ]);
        
        if (empty($comparisonData)) {
            Log::warning('No comparison data found for upload', ['upload_id' => $uploadId]);
            return redirect()->route('price-lists.index')
                           ->with('error', 'No price changes found for this upload. Data may have been processed already.');
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
                'entry_data.list_header_id' => 'required|string',
                'entry_data.item_id' => 'required|string',
                'entry_data.uom_code' => 'required|string',
                'entry_data.new_price' => 'required|numeric|min:0',
                'entry_data.start_date' => 'nullable|date|after_or_equal:today',
            ]);
            
            $entryData = $request->entry_data;
            $startDate = $entryData['start_date'] ? \Carbon\Carbon::parse($entryData['start_date']) : now();
            $creationDate = now();
            
            $insertData = [[
                'list_header_id' => $entryData['list_header_id'],
                'item_id' => $entryData['item_id'],
                'uom_code' => $entryData['uom_code'],
                'new_price' => $entryData['new_price'],
                'creation_date' => $creationDate,
                'start_date' => $startDate,
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
                $creationDate = now();
                
                foreach ($itemsToUpdate as $item) {
                    // Get additional required fields from item data or defaults
                    $listHeaderId = $item['list_header_id'] ?? $request->input('list_header_id', '1001');
                    $itemId = $item['list_line_id'] ?? $item['oracle_list_line_id'];
                    $uomCode = $item['uom_code'] ?? $item['uom'] ?? 'PCS';
                    
                    $insertData[] = [
                        'creation_date' => $creationDate,
                        'end_date' => null, // No end date for new prices
                        'error_message' => null,
                        'item_code' => $item['item_code'] ?? null,
                        'list_header_id' => $listHeaderId,
                        'list_line_id' => $itemId, // Standardized field name
                        'item_id' => $itemId, // Keep for Oracle compatibility
                        'uom_code' => $uomCode,
                        'new_price' => $item['list_price'],
                        'start_date' => $startDate,
                        'processed_date' => null,
                        'status' => 'N',
                        'operation_type' => 'new_price' // Mark for deduplication
                    ];
                }
            }

            // Execute Oracle price update workflow in transaction
            DB::connection('oracle')->transaction(function () use ($insertData, $request) {
                $today = now();
                $newStartDate = $insertData[0]['start_date'] ?? $today->addDay(); // Get start date from first item
                
                // Convert to Carbon if it's a string
                if (!($newStartDate instanceof \Carbon\Carbon)) {
                    $newStartDate = \Carbon\Carbon::parse($newStartDate);
                }
                
                // Calculate end date for current prices based on business logic
                if ($newStartDate->diffInDays($today, false) > 1) {
                    // Scenario 2: New prices start more than 1 day from today
                    // End current prices 1 day before new start date
                    $endDateForCurrentPrices = $newStartDate->copy()->subDay();
                } else {
                    // Scenario 1: New prices start tomorrow or sooner
                    // End current prices on system date (today)
                    $endDateForCurrentPrices = $today->copy();
                }
                
                Log::info('Price update date logic applied', [
                    'today' => $today->format('Y-m-d'),
                    'new_start_date' => $newStartDate->format('Y-m-d'),
                    'end_date_for_current_prices' => $endDateForCurrentPrices->format('Y-m-d'),
                    'days_difference' => $newStartDate->diffInDays($today, false),
                    'scenario' => $newStartDate->diffInDays($today, false) > 1 ? 'Future start date' : 'Near-term start date'
                ]);
                
                // Get synced Oracle prices from session to find current prices
                $oraclePrices = session('synced_oracle_prices', []);
                $oraclePricesMap = collect($oraclePrices)->keyBy('item_code');
                
                $allInsertData = [];
                
                // Process each item to create end-date and new price records
                foreach ($insertData as $newPriceData) {
                    $itemCode = $newPriceData['item_code'];
                    $oracleItem = $oraclePricesMap->get($itemCode);
                    
                    // Step 1: Insert end-date record for current price (if exists in Oracle)
                    if ($oracleItem && isset($oracleItem['oracle_price'])) {
                        $allInsertData[] = [
                            'creation_date' => now(),
                            'end_date' => $endDateForCurrentPrices, // Calculated end date
                            'error_message' => null,
                            'item_code' => $itemCode,
                            'list_header_id' => $oracleItem['price_list_id'] ?? null,
                            'list_line_id' => $oracleItem['list_line_id'] ?? null,
                            'new_price' => $oracleItem['oracle_price'], // Keep current price
                            'currency_code' => 'PKR',
                            'start_date' => null, // No start date change
                            'processed_date' => null,
                            'status' => 'N', // Standardized field name
                            'operation_type' => 'end_date' // Mark for deduplication
                        ];
                    }
                    
                    // Step 2: Insert new price record
                    $allInsertData[] = $newPriceData;
                }
                
                // Remove duplicates and insert all records in batches with separated operations
                $uniqueInsertData = $this->removeDuplicatesByListLineId($allInsertData);

                // Separate operations for consistent field structures
                $endDateOps = array_filter($uniqueInsertData, fn($op) => ($op['operation_type'] ?? '') === 'end_date');
                $newPriceOps = array_filter($uniqueInsertData, fn($op) => ($op['operation_type'] ?? '') === 'new_price');

                // Process end date operations
                if (!empty($endDateOps)) {
                    $endDateRecords = array_map(function ($record) {
                        return [
                            'creation_date' => $record['creation_date'],
                            'end_date' => $record['end_date'],
                            'error_message' => $record['error_message'],
                            'item_code' => $record['item_code'],
                            'list_header_id' => $record['list_header_id'],
                            'list_line_id' => $record['list_line_id'],
                            'new_price' => $record['new_price'],
                            'currency_code' => $record['currency_code'] ?? null,
                            'start_date' => $record['start_date'],
                            'processed_date' => $record['processed_date'],
                            'status' => $record['status'],
                        ];
                    }, $endDateOps);

                    foreach (array_chunk($endDateRecords, 100) as $chunk) {
                        DB::connection('oracle')
                            ->table('apps.qg_price_list_enddate')
                            ->insert($chunk);
                    }
                }

                // Process new price operations
                if (!empty($newPriceOps)) {
                    $newPriceRecords = array_map(function ($record) {
                        return [
                            'creation_date' => $record['creation_date'],
                            'end_date' => $record['end_date'],
                            'error_message' => $record['error_message'],
                            'item_code' => $record['item_code'],
                            'list_header_id' => $record['list_header_id'],
                            'list_line_id' => $record['list_line_id'],
                            'item_id' => $record['item_id'] ?? null,
                            'uom_code' => $record['uom_code'] ?? null,
                            'new_price' => $record['new_price'],
                            'start_date' => $record['start_date'],
                            'processed_date' => $record['processed_date'],
                            'status' => $record['status'],
                        ];
                    }, $newPriceOps);

                    foreach (array_chunk($newPriceRecords, 100) as $chunk) {
                        DB::connection('oracle')
                            ->table('apps.qg_price_list_enddate')
                            ->insert($chunk);
                    }
                }
                
                Log::info('Price updates inserted into QG_PRICE_LIST_UPDATES', [
                    'total_records' => count($allInsertData),
                    'new_price_records' => count($insertData),
                    'end_date_records' => count($allInsertData) - count($insertData),
                    'item_codes' => collect($insertData)->pluck('item_code')->unique()->toArray(),
                    'end_date_applied' => $endDateForCurrentPrices->format('Y-m-d H:i:s')
                ]);
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
                ->table('apps.qg_price_list_enddate')
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
            $creationDate = now();
            
            foreach ($updatedPrices as $priceItem) {
                // Get required fields from price item or defaults
                $listHeaderId = $priceItem->list_header_id ?? '1001'; // Default price list header
                $itemId = $priceItem->list_line_id ?? $priceItem->oracle_list_line_id;
                $uomCode = $priceItem->uom ?? 'PCS'; // Default UOM
                
                $insertData[] = [
                    'creation_date' => $creationDate,
                    'end_date' => null, // No end date for new prices
                    'error_message' => null,
                    'item_code' => $priceItem->item_code,
                    'list_header_id' => $listHeaderId,
                    'list_line_id' => $itemId, // Standardized field name
                    'item_id' => $itemId, // Keep for Oracle compatibility
                    'uom_code' => $uomCode,
                    'new_price' => $priceItem->list_price,
                    'start_date' => $priceItem->price_updated_at ?? now(),
                    'processed_date' => null,
                    'status' => 'N',
                ];
            }

            // Insert into Oracle table in batches with deduplication
            DB::connection('oracle')->transaction(function () use ($insertData) {
                // Apply deduplication before insertion
                $uniqueInsertData = $this->removeDuplicatesByListLineId($insertData);

                foreach (array_chunk($uniqueInsertData, 100) as $chunk) {
                    DB::connection('oracle')
                        ->table('apps.qg_price_list_enddate')
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
     * Prepare comparison data showing Excel vs Local Database differences
     */
    private function prepareComparisonData(int $uploadId): array
    {
        // Get upload record
        $upload = PriceListUpload::find($uploadId);
        if (!$upload) {
            Log::error('Upload record not found', ['upload_id' => $uploadId]);
            return [];
        }
        
        // Get the item codes from the Excel file we just processed
        // Dynamically detect Item Code column from header row (like import does)
        $excelItemCodes = [];
        if (isset($GLOBALS['excel_raw_analysis']['raw_rows'])) {
            $itemCodeColumn = null;

            // Find Item Code column from header row (row 1)
            if (isset($GLOBALS['excel_raw_analysis']['raw_rows'][1])) {
                $headerRow = $GLOBALS['excel_raw_analysis']['raw_rows'][1];
                $possibleItemCodeHeaders = ['Item Code', 'item_code', 'Product Code', 'product_code', 'ItemCode', 'ProductCode', 'Code'];

                foreach ($headerRow as $colLetter => $headerValue) {
                    foreach ($possibleItemCodeHeaders as $possibleHeader) {
                        if (strcasecmp(trim($headerValue), $possibleHeader) === 0) {
                            $itemCodeColumn = $colLetter;
                            Log::info('Detected Item Code column', [
                                'column' => $colLetter,
                                'header_value' => $headerValue
                            ]);
                            break 2;
                        }
                    }
                }
            }

            // Extract item codes from data rows using detected column
            if ($itemCodeColumn) {
                foreach ($GLOBALS['excel_raw_analysis']['raw_rows'] as $rowNum => $rowData) {
                    if ($rowNum > 1 && !empty($rowData[$itemCodeColumn])) { // Skip header row
                        $excelItemCodes[] = trim($rowData[$itemCodeColumn]);
                    }
                }

                Log::info('Extracted item codes from Excel', [
                    'item_code_column' => $itemCodeColumn,
                    'total_item_codes' => count($excelItemCodes),
                    'first_5_items' => array_slice($excelItemCodes, 0, 5)
                ]);
            } else {
                Log::warning('Could not detect Item Code column from Excel headers', [
                    'header_row' => $GLOBALS['excel_raw_analysis']['raw_rows'][1] ?? 'not found'
                ]);
            }
        }
        
        // If we don't have Excel item codes from raw analysis, fallback to recent items
        if (empty($excelItemCodes)) {
            $uploadTime = $upload->uploaded_at;
            $startTime = $uploadTime->copy()->subMinutes(2);
            $endTime = $uploadTime->copy()->addMinutes(10);
            
            $recentItems = ItemPrice::whereBetween('price_updated_at', [$startTime, $endTime])
                ->where('price_updated_by', $upload->uploaded_by)
                ->get();
                
            $excelItemCodes = $recentItems->pluck('item_code')->unique()->toArray();
        }
        
        // Now get only items that were actually updated/created during this upload
        $uploadTime = $upload->uploaded_at;
        $startTime = $uploadTime->copy()->subMinutes(2);
        $endTime = $uploadTime->copy()->addMinutes(10);

        // Get items processed during this specific upload (exact match by upload ID and timing)
        $processedItems = ItemPrice::whereBetween('price_updated_at', [$startTime, $endTime])
            ->where('price_updated_by', $upload->uploaded_by)
            ->orderBy('item_code')
            ->orderBy('uom')
            ->orderBy('price_list_name')
            ->get();
        
        // Debug logging
        Log::info('Preparing comparison data by item codes', [
            'upload_id' => $uploadId,
            'excel_item_codes_count' => count($excelItemCodes),
            'first_5_item_codes' => array_slice($excelItemCodes, 0, 5),
            'processed_items_count' => $processedItems->count(),
            'first_processed_item' => $processedItems->first() ? [
                'item_code' => $processedItems->first()->item_code,
                'list_price' => $processedItems->first()->list_price,
                'previous_price' => $processedItems->first()->previous_price,
                'price_updated_at' => $processedItems->first()->price_updated_at,
            ] : null
        ]);
        
        $comparisonData = [];

        foreach ($processedItems as $item) {
            // Skip items with zero or empty prices (from empty Excel cells)
            if (!$item->list_price || $item->list_price <= 0) {
                continue;
            }

            // Use the saved previous_price from the import process
            $priceStatus = 'new';
            $oldPrice = $item->previous_price;
            $priceChange = null;
            $backgroundColor = '#d1ecf1'; // Light blue for new

            if ($oldPrice && $oldPrice > 0) {
                // This is an update to existing price
                $priceChange = $item->list_price - $oldPrice;

                if (abs($priceChange) < 0.01) {
                    $priceStatus = 'same';
                    $backgroundColor = '#f8f9fa'; // Light gray for same price
                } elseif ($priceChange > 0) {
                    $priceStatus = 'increased';
                    $backgroundColor = '#fff3cd'; // Light yellow for increase
                } else {
                    $priceStatus = 'decreased';
                    $backgroundColor = '#f8d7da'; // Light red for decrease
                }
            }
            
            $comparisonData[] = [
                'item_code' => $item->item_code,
                'price_list_name' => $item->price_list_name,
                'uom' => $item->uom,
                'list_price' => $item->list_price,
                'previous_price' => $oldPrice,
                'price_change' => $priceChange,
                'price_status' => $priceStatus,
                'background_color' => $backgroundColor,
                'start_date_active' => $item->start_date_active,
                'end_date_active' => $item->end_date_active,
                'item_description' => $item->item_description,
                'major_desc' => $item->major_desc,
                'minor_desc' => $item->minor_desc,
                'sub_minor_desc' => $item->sub_minor_desc,
                'brand' => $item->brand,
                'price_type' => $item->price_type,
            ];
        }
        
        return $comparisonData;
    }

    /**
     * Enrich Excel data with Oracle LINE_ID and HEADER_ID
     */
    private function enrichWithOracleIds(array $excelData): array
    {
        $enrichedData = [];

        foreach ($excelData as $item) {
            $itemCode = $item['item_code'];
            $uom = $item['uom'];
            $priceListName = $item['price_list_name'];

            // Log Excel data being processed
            Log::info('Processing Excel item for Oracle enrichment', [
                'item_code' => $itemCode,
                'uom' => $uom,
                'price_list_name' => $priceListName,
                'excel_price' => $item['list_price'],
                'excel_data' => $item
            ]);

            try {
                // Log what we're sending to Oracle for lookup
                Log::info('Sending Oracle lookup query', [
                    'table' => 'apps.qg_price_list_view',
                    'select_fields' => ['list_line_id', 'inventory_item_id', 'price_list_id', 'list_price as current_price'],
                    'where_conditions' => [
                        'item_code' => $itemCode,
                        'uom' => $uom,
                        'price_list_name' => $priceListName
                    ]
                ]);

                // Try original name first
                $oraclePrice = DB::connection('oracle')
                    ->table('apps.qg_price_list_view')
                    ->select(['list_line_id', 'inventory_item_id', 'price_list_id', 'list_price as current_price'])
                    ->where('item_code', $itemCode)
                    ->where('uom', $uom)
                    ->where('price_list_name', $priceListName)
                    ->first();

                // If not found, try normalized version (remove spaces around hyphens)
                if (!$oraclePrice) {
                    $normalizedPriceListName = $this->normalizePriceListName($priceListName);

                    Log::info('Trying normalized price list name', [
                        'original' => $priceListName,
                        'normalized' => $normalizedPriceListName
                    ]);

                    $oraclePrice = DB::connection('oracle')
                        ->table('apps.qg_price_list_view')
                        ->select(['list_line_id', 'inventory_item_id', 'price_list_id', 'list_price as current_price'])
                        ->where('item_code', $itemCode)
                        ->where('uom', $uom)
                        ->where('price_list_name', $normalizedPriceListName)
                        ->first();
                }

                // Log raw Oracle response
                Log::info('Raw Oracle price_list_view lookup response', [
                    'item_code' => $itemCode,
                    'uom' => $uom,
                    'price_list_name' => $priceListName,
                    'raw_oracle_response' => $oraclePrice ? [
                        'list_line_id' => $oraclePrice->list_line_id,
                        'inventory_item_id' => $oraclePrice->inventory_item_id,
                        'price_list_id' => $oraclePrice->price_list_id,
                        'current_price' => $oraclePrice->current_price,
                        'response_type' => gettype($oraclePrice),
                        'all_fields' => (array) $oraclePrice
                    ] : 'NO_RECORD_FOUND'
                ]);

                // Initialize variables
                $finalListHeaderId = null;
                $finalItemId = null;
                $currentPrice = null;
                $oracleFound = false;

                // Get list_header_id from Oracle using price_list_name from Excel
                $finalListHeaderId = null;
                try {
                    // Try original name first
                    $priceListHeader = DB::connection('oracle')
                        ->table('apps.qg_price_list_view')
                        ->select(['price_list_id'])
                        ->where('price_list_name', $priceListName)
                        ->first();

                    // If not found, try normalized version
                    if (!$priceListHeader) {
                        $normalizedPriceListName = $this->normalizePriceListName($priceListName);
                        $priceListHeader = DB::connection('oracle')
                            ->table('apps.qg_price_list_view')
                            ->select(['price_list_id'])
                            ->where('price_list_name', $normalizedPriceListName)
                            ->first();
                    }

                    $finalListHeaderId = $priceListHeader->price_list_id ?? null;

                    Log::info('Oracle list_header_id lookup from price_list_name', [
                        'item_code' => $itemCode,
                        'excel_price_list_name' => $priceListName,
                        'oracle_list_header_id' => $finalListHeaderId,
                        'lookup_success' => $priceListHeader !== null
                    ]);

                    // Fallback to mapping if Oracle lookup failed
                    if (!$finalListHeaderId) {
                        $priceListMapping = [
                            'Karachi - Corporate' => '7010',
                            'Karachi - Trade Price' => '7012',
                            'Karachi - Wholesale' => '7007',
                            'Lahore - Corporate' => '7009',
                            'Lahore - Trade Price' => '7008',
                            'QG HBM' => '1116080',
                        ];
                        $finalListHeaderId = $priceListMapping[$priceListName] ?? null;

                        Log::info('Using fallback mapping for list_header_id', [
                            'item_code' => $itemCode,
                            'excel_price_list_name' => $priceListName,
                            'fallback_list_header_id' => $finalListHeaderId
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to get list_header_id from Oracle', [
                        'item_code' => $itemCode,
                        'price_list_name' => $priceListName,
                        'error' => $e->getMessage()
                    ]);

                    // Use fallback mapping on exception
                    $priceListMapping = [
                        'Karachi - Corporate' => '7010',
                        'Karachi - Trade Price' => '7012',
                        'Karachi - Wholesale' => '7007',
                        'Lahore - Corporate' => '7009',
                        'Lahore - Trade Price' => '7008',
                        'QG HBM' => '1116080',
                    ];
                    $finalListHeaderId = $priceListMapping[$priceListName] ?? null;

                    Log::info('Using exception fallback mapping for list_header_id', [
                        'item_code' => $itemCode,
                        'excel_price_list_name' => $priceListName,
                        'fallback_list_header_id' => $finalListHeaderId,
                        'exception_reason' => $e->getMessage()
                    ]);
                }

                // If price_list_view has data, use it for item_id and current price
                if ($oraclePrice) {
                    $finalItemId = $oraclePrice->inventory_item_id;
                    $currentPrice = $oraclePrice->current_price;
                    $oracleFound = true;
                } else {
                    // If no price_list_view data, get item_id from qg_pos_item_master
                    Log::info('Fallback to qg_pos_item_master for item_id', [
                        'item_code' => $itemCode,
                        'reason' => 'No data from price_list_view, need item_id from item_master'
                    ]);

                    try {
                        $itemMaster = DB::connection('oracle')
                            ->table('apps.qg_pos_item_master')
                            ->select(['inventory_item_id'])
                            ->where('item_code', $itemCode)
                            ->first();

                        Log::info('qg_pos_item_master lookup response', [
                            'item_code' => $itemCode,
                            'item_master_found' => $itemMaster !== null,
                            'item_master_data' => $itemMaster ? [
                                'inventory_item_id' => $itemMaster->inventory_item_id
                            ] : null
                        ]);

                        if ($itemMaster) {
                            $finalItemId = $itemMaster->inventory_item_id;
                        } else {
                            // Item doesn't exist in Oracle item master - CREATE IT
                            Log::info('Item not found in Oracle item_master, attempting to create', [
                                'item_code' => $itemCode,
                                'uom' => $uom,
                                'item_description' => $item['item_description'] ?? $itemCode
                            ]);

                            try {
                                // Get the next available inventory_item_id
                                $maxId = DB::connection('oracle')
                                    ->table('apps.qg_pos_item_master')
                                    ->max('inventory_item_id');

                                $newInventoryItemId = ($maxId ?? 0) + 1;

                                // Insert new item into Oracle item master
                                DB::connection('oracle')
                                    ->table('apps.qg_pos_item_master')
                                    ->insert([
                                        'inventory_item_id' => $newInventoryItemId,
                                        'item_code' => $itemCode,
                                        'item_description' => $item['item_description'] ?? $itemCode,
                                        'primary_uom_code' => $uom,
                                        'secondary_uom_code' => null,
                                    ]);

                                $finalItemId = $newInventoryItemId;

                                Log::info('Successfully created item in Oracle item_master', [
                                    'item_code' => $itemCode,
                                    'inventory_item_id' => $newInventoryItemId,
                                    'item_description' => $item['item_description'] ?? $itemCode,
                                    'primary_uom_code' => $uom
                                ]);

                            } catch (\Exception $createError) {
                                Log::error('Failed to create item in Oracle item_master', [
                                    'item_code' => $itemCode,
                                    'error' => $createError->getMessage(),
                                    'trace' => $createError->getTraceAsString()
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('qg_pos_item_master lookup failed', [
                            'item_code' => $itemCode,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Log final Oracle lookup result
                Log::info('Final Oracle lookup result', [
                    'item_code' => $itemCode,
                    'uom' => $uom,
                    'price_list_name' => $priceListName,
                    'oracle_found' => $oracleFound,
                    'final_data' => [
                        'list_line_id' => $oraclePrice->list_line_id ?? null,
                        'item_id' => $finalItemId,
                        'list_header_id' => $finalListHeaderId,
                        'current_price' => $currentPrice,
                        'data_source' => $oraclePrice ? 'price_list_view' : 'item_master_fallback'
                    ]
                ]);

                $enrichedItem = array_merge($item, [
                    'oracle_list_line_id' => $oraclePrice->list_line_id ?? null,
                    'oracle_item_id' => $finalItemId,
                    'oracle_list_header_id' => $finalListHeaderId,
                    'current_oracle_price' => $currentPrice,
                    'oracle_found' => $oracleFound,
                    'price_difference' => $currentPrice ? ((float)$item['list_price'] - (float)$currentPrice) : 0,
                ]);

                $enrichedData[] = $enrichedItem;

            } catch (\Exception $e) {
                Log::error('Failed to get Oracle IDs for item', [
                    'item_code' => $itemCode,
                    'uom' => $uom,
                    'price_list_name' => $priceListName,
                    'error' => $e->getMessage()
                ]);

                // Add item without Oracle data
                $enrichedData[] = array_merge($item, [
                    'oracle_list_line_id' => null,
                    'oracle_item_id' => null,
                    'oracle_list_header_id' => null,
                    'current_oracle_price' => null,
                    'oracle_found' => false,
                    'price_difference' => 0,
                    'oracle_error' => $e->getMessage()
                ]);
            }
        }

        return $enrichedData;
    }

    /**
     * End current prices in Oracle for items that have Oracle IDs
     */
    private function endCurrentOraclePrices(array $enrichedData): array
    {
        $results = [];
        
        foreach ($enrichedData as $item) {
            if (!$item['oracle_found'] || !$item['oracle_list_line_id']) {
                $results[] = [
                    'item_code' => $item['item_code'],
                    'status' => 'skipped',
                    'message' => 'No current Oracle price found to end'
                ];
                continue;
            }
            
            try {
                // Use end date from Excel if provided, otherwise use system date
                $endDate = !empty($item['end_date_active']) 
                    ? \Carbon\Carbon::parse($item['end_date_active'])
                    : now();
                
                // Insert end-date record to Oracle price end date table
                DB::connection('oracle')
                    ->table('apps.qg_price_list_enddate')
                    ->insert([
                        'list_header_id' => $item['oracle_list_header_id'],
                        'list_line_id' => $item['oracle_list_line_id'],
                        'end_date' => $endDate,
                        'status' => 'N',
                        'error_message' => null,
                        'creation_date' => now(),
                        'proccessed_on' => null,
                    ]);
                
                $results[] = [
                    'item_code' => $item['item_code'],
                    'status' => 'success',
                    'message' => "Current price ended on {$endDate->format('Y-m-d')}",
                    'end_date' => $endDate->format('Y-m-d')
                ];
                
            } catch (\Exception $e) {
                Log::error('Failed to end Oracle price', [
                    'item_code' => $item['item_code'],
                    'list_line_id' => $item['oracle_list_line_id'],
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'item_code' => $item['item_code'],
                    'status' => 'error',
                    'message' => 'Failed to end current price: ' . $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Send end dates for existing prices to Oracle (NEW METHOD)
     */
    public function endExistingPricesInOracle(Request $request)
    {
        $request->validate([
            'selected_items' => 'required|array|min:1',
            'upload_id' => 'required|integer',
            'end_date' => 'nullable|date',
        ]);

        try {
            $uploadId = $request->upload_id;
            $selectedItems = $request->selected_items;
            $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : now();
            
            // Get comparison data from database
            $comparisonData = $this->prepareComparisonData($uploadId);
            
            if (empty($comparisonData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No price data found for this upload.',
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

            // Step 1: Get Oracle LIST_HEADER_ID and LIST_LINE_ID for each item
            $oracleEnrichedData = $this->enrichWithOracleIds($itemsToUpdate);
            
            // Step 2: Send only end dates for existing prices
            $endResults = [];
            $dataToSend = [];
            
            foreach ($oracleEnrichedData as $item) {
                try {
                    if ($item['oracle_found'] && $item['oracle_list_line_id']) {
                        $endDateRecord = [
                            'list_header_id' => $item['oracle_list_header_id'],
                            'list_line_id' => $item['oracle_list_line_id'],
                            'end_date' => $endDate,
                            'status' => 'N',
                            'error_message' => null,
                            'creation_date' => now(),
                            'proccessed_on' => null,
                        ];

                        // Log what data will be sent to Oracle
                        Log::info('Sending end date to Oracle for existing price', [
                            'item_code' => $item['item_code'],
                            'list_header_id' => $item['oracle_list_header_id'],
                            'list_line_id' => $item['oracle_list_line_id'],
                            'end_date' => $endDate->format('Y-m-d H:i:s'),
                            'oracle_table' => 'apps.qg_price_list_enddate'
                        ]);

                        $dataToSend[] = $endDateRecord;
                        
                        DB::connection('oracle')
                            ->table('apps.qg_price_list_enddate')
                            ->insert($endDateRecord);
                        
                        $endResults[] = [
                            'item_code' => $item['item_code'],
                            'status' => 'ended',
                            'list_header_id' => $item['oracle_list_header_id'],
                            'list_line_id' => $item['oracle_list_line_id'],
                            'end_date' => $endDate->format('Y-m-d'),
                            'current_price' => $item['current_oracle_price']
                        ];
                    } else {
                        $endResults[] = [
                            'item_code' => $item['item_code'],
                            'status' => 'skipped',
                            'message' => 'No Oracle price record found to end'
                        ];
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Failed to end Oracle price', [
                        'item_code' => $item['item_code'],
                        'error' => $e->getMessage()
                    ]);
                    
                    $endResults[] = [
                        'item_code' => $item['item_code'],
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
            
            // Log summary of data sent to Oracle
            Log::info('Oracle end date operation completed', [
                'total_items_processed' => count($itemsToUpdate),
                'total_records_sent_to_oracle' => count($dataToSend),
                'end_date_applied' => $endDate->format('Y-m-d H:i:s'),
                'successful_endings' => count(array_filter($endResults, fn($r) => $r['status'] === 'ended')),
                'data_sent_to_oracle' => $dataToSend
            ]);
            
            $successCount = count(array_filter($endResults, fn($r) => $r['status'] === 'ended'));
            $errorCount = count(array_filter($endResults, fn($r) => $r['status'] === 'error'));
            $skippedCount = count(array_filter($endResults, fn($r) => $r['status'] === 'skipped'));
            
            return response()->json([
                'success' => true,
                'message' => "End dates sent to Oracle! {$successCount} prices ended, {$skippedCount} skipped (no Oracle record), {$errorCount} errors.",
                'results' => $endResults,
                'summary' => [
                    'ended' => $successCount,
                    'skipped' => $skippedCount,
                    'errors' => $errorCount
                ],
                'redirect_url' => route('price-lists.index')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to end existing prices in Oracle: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to end existing prices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send end dates and new prices to Oracle using Start Date and End Date from Excel
     */
    public function enterNewPricesToOracle(Request $request)
    {
        // Increase execution time for large batch operations
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '512M');

        // UNCOMMENTED - Now sending both end dates and new prices with start dates
        $request->validate([
            'selected_items' => 'required|array|min:1',
            'upload_id' => 'required|integer',
        ]);

        try {
            $uploadId = $request->upload_id;
            $selectedItems = $request->selected_items;

            Log::info('Starting Oracle price update', [
                'upload_id' => $uploadId,
                'total_items' => count($selectedItems)
            ]);
            
            // Get comparison data from database
            $comparisonData = $this->prepareComparisonData($uploadId);
            
            if (empty($comparisonData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No price data found for this upload.',
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

            // Step 1: Get Oracle LIST_HEADER_ID and LIST_LINE_ID for each item
            $oracleEnrichedData = $this->enrichWithOracleIds($itemsToUpdate);

            // Price list mapping for fallback (avoid repeated Oracle lookups)
            $priceListMapping = [
                'Karachi - Corporate' => '7010',
                'Karachi - Trade Price' => '7012',
                'Karachi-Wholesale' => '7011',
                'Karachi - Wholesale' => '7007',
                'Lahore - Corporate' => '7009',
                'Lahore - Trade Price' => '7008',
                'Lahore - Wholesale' => '7008',
                'QG HBM' => '1116080',
            ];

            // Step 2: Prepare batch data for Oracle inserts
            $endDateBatch = [];
            $newPriceBatch = [];
            $endResults = [];
            $newPriceResults = [];
            $batchSize = 100; // Process 100 items at a time

            Log::info('Processing items in batches', [
                'total_items' => count($oracleEnrichedData),
                'batch_size' => $batchSize
            ]);

            foreach ($oracleEnrichedData as $index => $item) {
                try {
                    // Get list_header_id from mapping
                    $listHeaderId = $item['oracle_list_header_id']
                        ?? $priceListMapping[$item['price_list_name']]
                        ?? $priceListMapping[$this->normalizePriceListName($item['price_list_name'])]
                        ?? null;

                    // Get item_id (either from existing price or from item master)
                    $itemId = $item['oracle_item_id'] ?? null;

                    if ($item['oracle_found'] && $item['oracle_list_line_id']) {
                        // EXISTING PRICE IN ORACLE - End current price and create new one
                        $endDate = !empty($item['end_date_active'])
                            ? \Carbon\Carbon::parse($item['end_date_active'])
                            : now();

                        // Add to end date batch
                        $endDateBatch[] = [
                            'list_header_id' => $listHeaderId,
                            'list_line_id' => $item['oracle_list_line_id'],
                            'end_date' => $endDate,
                            'status' => 'N',
                            'error_message' => null,
                            'creation_date' => now(),
                            'proccessed_on' => null,
                        ];

                        $endResults[] = [
                            'item_code' => $item['item_code'],
                            'status' => 'ended',
                            'end_date' => $endDate->format('Y-m-d')
                        ];

                        // Prepare new price data
                        $startDate = !empty($item['start_date_active'])
                            ? \Carbon\Carbon::parse($item['start_date_active'])
                            : now()->addDay();

                        $newPriceBatch[] = [
                            'list_header_id' => $listHeaderId,
                            'item_id' => $itemId,
                            'uom_code' => $item['uom'] ?? 'PCS',
                            'new_price' => $item['list_price'],
                            'creation_date' => now(),
                            'start_date' => $startDate,
                        ];

                        $newPriceResults[] = [
                            'item_code' => $item['item_code'],
                            'status' => 'success',
                            'new_price' => $item['list_price'],
                            'start_date' => $startDate->format('Y-m-d')
                        ];
                    } elseif ($itemId && $listHeaderId) {
                        // NEW PRICE - Item exists in item_master but not in price list
                        // Create new price without ending anything
                        $startDate = !empty($item['start_date_active'])
                            ? \Carbon\Carbon::parse($item['start_date_active'])
                            : now();

                        $newPriceBatch[] = [
                            'list_header_id' => $listHeaderId,
                            'item_id' => $itemId,
                            'uom_code' => $item['uom'] ?? 'PCS',
                            'new_price' => $item['list_price'],
                            'creation_date' => now(),
                            'start_date' => $startDate,
                        ];

                        $newPriceResults[] = [
                            'item_code' => $item['item_code'],
                            'status' => 'created',
                            'new_price' => $item['list_price'],
                            'start_date' => $startDate->format('Y-m-d'),
                            'message' => 'New price created in Oracle'
                        ];

                        Log::info('Creating new price in Oracle', [
                            'item_code' => $item['item_code'],
                            'list_header_id' => $listHeaderId,
                            'item_id' => $itemId,
                            'price' => $item['list_price']
                        ]);
                    } else {
                        // SKIP - Item not found in item_master or missing list_header_id
                        $newPriceResults[] = [
                            'item_code' => $item['item_code'],
                            'status' => 'skipped',
                            'message' => 'Item not found in Oracle item master or invalid price list'
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to prepare Oracle price update', [
                        'item_code' => $item['item_code'],
                        'error' => $e->getMessage()
                    ]);
                    $newPriceResults[] = [
                        'item_code' => $item['item_code'],
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }

                // Insert in batches to avoid memory issues and timeouts
                if (count($endDateBatch) >= $batchSize) {
                    try {
                        DB::connection('oracle')->table('apps.qg_price_list_enddate')->insert($endDateBatch);
                        DB::connection('oracle')->table('apps.qg_price_list_enddate')->insert($newPriceBatch);
                        Log::info('Batch inserted to Oracle', [
                            'batch_number' => floor($index / $batchSize) + 1,
                            'end_dates' => count($endDateBatch),
                            'new_prices' => count($newPriceBatch)
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Batch insert failed', ['error' => $e->getMessage()]);
                    }
                    $endDateBatch = [];
                    $newPriceBatch = [];
                }
            }

            // Insert remaining items
            if (!empty($endDateBatch)) {
                try {
                    DB::connection('oracle')->table('apps.qg_price_list_enddate')->insert($endDateBatch);
                    DB::connection('oracle')->table('apps.qg_price_list_enddate')->insert($newPriceBatch);
                    Log::info('Final batch inserted to Oracle', [
                        'end_dates' => count($endDateBatch),
                        'new_prices' => count($newPriceBatch)
                    ]);
                } catch (\Exception $e) {
                    Log::error('Final batch insert failed', ['error' => $e->getMessage()]);
                }
            }
            
            // Data is from database, no session to clear

            $successCount = count(array_filter($newPriceResults, fn($r) => $r['status'] === 'success'));
            $createdCount = count(array_filter($newPriceResults, fn($r) => $r['status'] === 'created'));
            $skippedCount = count(array_filter($newPriceResults, fn($r) => $r['status'] === 'skipped'));
            $errorCount = count(array_filter($newPriceResults, fn($r) => $r['status'] === 'error'));
            $endedCount = count($endResults);

            Log::info('Oracle price update completed', [
                'total_items' => count($newPriceResults),
                'ended' => $endedCount,
                'updated' => $successCount,
                'created' => $createdCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ]);

            // Return summary only (not all individual results to avoid large response)
            return response()->json([
                'success' => true,
                'message' => "Oracle prices updated! {$endedCount} current prices ended, {$successCount} prices updated, {$createdCount} new prices created, {$skippedCount} skipped, {$errorCount} errors.",
                'summary' => [
                    'total' => count($newPriceResults),
                    'ended' => $endedCount,
                    'updated' => $successCount,
                    'created' => $createdCount,
                    'skipped' => $skippedCount,
                    'errors' => $errorCount
                ],
                'redirect_url' => route('price-lists.index')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to enter new prices to Oracle: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to enter new prices: ' . $e->getMessage(),
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
    
    /**
     * Normalize price list name to match Oracle format
     * Converts "Karachi - Wholesale" to "Karachi-Wholesale" (removes spaces around hyphens)
     */
    private function normalizePriceListName(string $priceListName): string
    {
        // Remove spaces around hyphens to match Oracle format
        return preg_replace('/\s*-\s*/', '-', $priceListName);
    }

    /**
     * Determine price type from price list name
     */
    private function determinePriceType(string $priceListName): string
    {
        $priceListName = strtolower($priceListName);

        if (str_contains($priceListName, 'corporate')) {
            return 'corporate';
        } elseif (str_contains($priceListName, 'wholesale')) {
            return 'wholesaler';
        } elseif (str_contains($priceListName, 'trade')) {
            return 'trade';
        } elseif (str_contains($priceListName, 'hbm') || str_contains($priceListName, 'qg hbm')) {
            return 'hbm';
        } else {
            return 'other';
        }
    }

    /**
     * Determine price_list_id based on price list name.
     */
    private function determinePriceListId(string $priceListName): string
    {
        $mappings = [
            'Karachi - Corporate' => '7010',
            'Karachi - Wholesale' => '7011',
            'Karachi - Trade Price' => '7012',
            'Lahore - Corporate' => '7007',
            'Lahore - Wholesale' => '7008',
            'Lahore - Trade Price' => '7009',
            'QG HBM' => '1116080',
        ];

        return $mappings[$priceListName] ?? '7010'; // Default to Karachi Corporate
    }

    /**
     * Export price comparison data to Excel
     */
    public function exportComparison($uploadId)
    {
        $upload = PriceListUpload::findOrFail($uploadId);
        $comparisonData = $this->prepareComparisonData($uploadId);

        $uploadInfo = [
            'uploaded_at' => $upload->uploaded_at->format('Y-m-d H:i:s'),
            'uploaded_by' => $upload->uploaded_by_name ?? 'System',
            'filename' => $upload->filename
        ];

        // Use original_filename and remove .xlsx extension, then sanitize
        $baseFilename = str_replace('.xlsx', '', $upload->original_filename ?? 'comparison');
        $baseFilename = preg_replace('/[\/\\\\]/', '_', $baseFilename); // Replace slashes with underscores
        $filename = 'price_comparison_' . $baseFilename . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            new PriceComparisonExport($comparisonData, $uploadInfo),
            $filename
        );
    }

    /**
     * Export price lists index data to Excel
     */
    public function exportIndex(Request $request)
    {
        // Reuse the same logic from index method to get filtered data
        $query = ItemPrice::query();

        // Apply filters
        if ($request->filled('changed_only')) {
            $query->where('price_changed', true);
        }

        if ($request->filled('city')) {
            $city = $request->city;
            $query->where(function($q) use ($city) {
                if ($city === 'karachi') {
                    $q->where('price_list_name', 'like', 'Karachi%');
                } elseif ($city === 'lahore') {
                    $q->where('price_list_name', 'like', 'Lahore%');
                }
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                  ->orWhere('item_description', 'like', "%{$search}%")
                  ->orWhere('price_list_name', 'like', "%{$search}%");
            });
        }

        // Get all prices and group by item_code + uom
        $allPrices = $query->get();

        // Define the 7 price lists in order
        $priceListOrder = [
            'Karachi - Trade Price',
            'Karachi - Wholesale',
            'Karachi - Corporate',
            'Lahore - Trade Price',
            'Lahore - Wholesale',
            'Lahore - Corporate',
            'QG HBM'
        ];

        $priceListVariations = [
            'Karachi - Trade Price' => ['Karachi - Trade Price', 'Karachi-Trade Price'],
            'Karachi - Wholesale' => ['Karachi - Wholesale', 'Karachi-Wholesale', 'Karachi Wholesale'],
            'Karachi - Corporate' => ['Karachi - Corporate', 'Karachi-Corporate', 'Karachi Corporate'],
            'Lahore - Trade Price' => ['Lahore - Trade Price', 'Lahore-Trade Price'],
            'Lahore - Wholesale' => ['Lahore - Wholesale', 'Lahore-Wholesale', 'Lahore Wholesale'],
            'Lahore - Corporate' => ['Lahore - Corporate', 'Lahore-Corporate', 'Lahore Corporate'],
            'QG HBM' => ['QG HBM', 'HBM', 'QG-HBM']
        ];

        // Group prices by item_code + uom combination
        $groupedPrices = $allPrices->groupBy(function($item) {
            return $item->item_code . '|' . $item->uom;
        })->map(function ($itemPrices, $itemKey) use ($priceListOrder, $priceListVariations) {
            $pricesByList = $itemPrices->keyBy('price_list_name');
            $firstItem = $itemPrices->first();

            $matrixRow = [
                'item_code' => $firstItem->item_code,
                'item_description' => $firstItem->item_description,
                'uom' => $firstItem->uom,
                'updated_at' => $itemPrices->max('price_updated_at'),
                'has_changes' => $itemPrices->where('price_changed', true)->count() > 0,
                'prices' => []
            ];

            // Fill in prices for each price list
            foreach ($priceListOrder as $standardPriceListName) {
                $priceItem = null;
                $variations = $priceListVariations[$standardPriceListName] ?? [$standardPriceListName];
                foreach ($variations as $variation) {
                    $priceItem = $pricesByList->get($variation);
                    if ($priceItem) {
                        break;
                    }
                }

                $matrixRow['prices'][$standardPriceListName] = [
                    'id' => $priceItem->id ?? null,
                    'list_price' => $priceItem->list_price ?? null,
                    'exists' => $priceItem !== null,
                ];
            }

            return $matrixRow;
        });

        // Apply price_type filter after grouping if needed
        if ($request->filled('price_type')) {
            $filterPriceType = $request->price_type;
            $groupedPrices = $groupedPrices->filter(function ($item) use ($filterPriceType, $priceListOrder) {
                foreach ($priceListOrder as $priceListName) {
                    $price = $item['prices'][$priceListName];
                    if ($price['exists']) {
                        $priceType = $this->determinePriceType($priceListName);
                        if ($priceType === $filterPriceType) {
                            return true;
                        }
                    }
                }
                return false;
            });
        }

        $priceListData = $groupedPrices->values()->toArray();

        // Prepare filter info for Excel
        $filters = [
            'search' => $request->get('search'),
            'price_type' => $request->get('price_type'),
            'changed_only' => $request->get('changed_only')
        ];

        $filename = 'price_lists_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            new PriceListsExport($priceListData, $priceListOrder, $filters),
            $filename
        );
    }

    /**
     * Remove duplicate records by list_line_id to prevent Oracle constraint violations
     */
    private function removeDuplicatesByListLineId(array $insertData): array
    {
        $seen = [];
        $uniqueData = [];
        
        foreach ($insertData as $record) {
            $listLineId = $record['list_line_id'] ?? $record['item_id'] ?? null;
            
            if ($listLineId && !isset($seen[$listLineId])) {
                $seen[$listLineId] = true;
                $uniqueData[] = $record;
            } elseif (!$listLineId) {
                // If no list_line_id, include the record (for new prices)
                $uniqueData[] = $record;
            }
        }
        
        Log::info('Duplicate prevention applied', [
            'original_count' => count($insertData),
            'unique_count' => count($uniqueData),
            'duplicates_removed' => count($insertData) - count($uniqueData)
        ]);

        return $uniqueData;
    }

    /**
     * API: Get inventory_item_id from Oracle view
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInventoryItemId(Request $request)
    {
        $request->validate([
            'item_code' => 'required|string',
            'uom' => 'nullable|string',
            'price_list_name' => 'nullable|string',
        ]);

        $itemCode = $request->item_code;
        $uom = $request->uom;
        $priceListName = $request->price_list_name;

        try {
            // Log the incoming request
            Log::info('API: Oracle inventory_item_id lookup request', [
                'item_code' => $itemCode,
                'uom' => $uom,
                'price_list_name' => $priceListName
            ]);

            // Query Oracle view to get inventory_item_id
            $query = DB::connection('oracle')
                ->table('apps.qg_price_list_view')
                ->select(['item_code', 'inventory_item_id', 'list_line_id', 'price_list_id', 'uom', 'price_list_name', 'list_price'])
                ->where('item_code', $itemCode);

            // Add optional filters
            if ($uom) {
                $query->where('uom', $uom);
            }
            if ($priceListName) {
                $query->where('price_list_name', $priceListName);
            }

            $results = $query->get();

            // Log Oracle response
            Log::info('API: Oracle inventory_item_id lookup response', [
                'item_code' => $itemCode,
                'uom' => $uom,
                'price_list_name' => $priceListName,
                'records_found' => $results->count(),
                'results' => $results->toArray()
            ]);

            if ($results->count() > 0) {
                return response()->json([
                    'success' => true,
                    'data' => $results->map(function($result) {
                        return [
                            'item_code' => $result->item_code,
                            'inventory_item_id' => $result->inventory_item_id,
                            'list_line_id' => $result->list_line_id,
                            'price_list_id' => $result->price_list_id,
                            'uom' => $result->uom,
                            'price_list_name' => $result->price_list_name,
                            'list_price' => $result->list_price
                        ];
                    }),
                    'count' => $results->count()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in Oracle price list view',
                    'error' => 'NO_RECORD_FOUND',
                    'searched_criteria' => [
                        'item_code' => $itemCode,
                        'uom' => $uom,
                        'price_list_name' => $priceListName
                    ]
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('API: Oracle inventory_item_id lookup failed', [
                'item_code' => $itemCode,
                'uom' => $uom,
                'price_list_name' => $priceListName,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to lookup inventory_item_id',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export upload history with error highlights and status
     */
    public function exportUploadHistory($uploadId)
    {
        $upload = PriceListUpload::findOrFail($uploadId);
        $comparisonData = $this->prepareComparisonData($uploadId);

        $uploadInfo = [
            'uploaded_at' => $upload->uploaded_at->format('Y-m-d H:i:s'),
            'uploaded_by' => $upload->uploadedBy->name ?? 'System',
            'filename' => $upload->original_filename,
            'status' => $upload->status,
            'total_rows' => $upload->total_rows,
            'updated_rows' => $upload->updated_rows,
            'new_rows' => $upload->new_rows,
            'error_rows' => $upload->error_rows,
        ];

        // Sanitize filename - remove .xlsx extension and replace slashes with underscores
        $baseFilename = str_replace('.xlsx', '', $upload->original_filename ?? 'history');
        $baseFilename = preg_replace('/[\/\\\\]/', '_', $baseFilename); // Replace slashes with underscores
        $filename = 'upload_history_' . $baseFilename . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            new PriceComparisonExport($comparisonData, $uploadInfo),
            $filename
        );
    }
}