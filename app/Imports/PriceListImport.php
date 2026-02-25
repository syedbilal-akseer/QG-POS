<?php

namespace App\Imports;

use App\Models\ItemPrice;
use App\Models\OracleItem;
use App\Models\OraclePriceListUpdate;
use App\Models\PriceListUpload;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Concerns\WithLimit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PriceListImport implements 
    ToModel, 
    WithHeadingRow,
    WithBatchInserts, 
    WithChunkReading,
    WithLimit, // Add row limit capability
    SkipsOnError,
    SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $uploadRecord;
    protected $maxRows;
    protected $stats = [
        'total' => 0,
        'updated' => 0,
        'new' => 0,
        'errors' => 0
    ];

    public function __construct(?PriceListUpload $uploadRecord = null, ?int $maxRows = null)
    {
        $this->uploadRecord = $uploadRecord;
        $this->maxRows = $maxRows;
        
        Log::info('PriceListImport constructor called', [
            'has_upload_record' => $uploadRecord !== null,
            'upload_id' => $uploadRecord?->id ?? 'none',
            'max_rows' => $maxRows
        ]);
    }

    /**
     * Limit the number of rows to process (for large files).
     */
    public function limit(): int
    {
        return $this->maxRows ?? 10000; // Default limit of 10,000 rows
    }

    /**
     * Transform a row into multiple models (matrix format).
     */
    public function model(array $row)
    {
        $this->stats['total']++;
        
        // Enhanced debug logging for first few rows
        if ($this->stats['total'] <= 3) {
            Log::info("Excel import row {$this->stats['total']} processing", [
                'row_number' => $this->stats['total'],
                'column_count' => count($row),
                'column_headers' => array_keys($row),
                'has_item_code' => isset($row['Item Code']) || isset($row['item_code']) || 
                                 isset($row['Product Code']) || isset($row['ItemCode']),
                'first_few_values' => array_slice($row, 0, 8, true)
            ]);
        }

        // Progress logging for large files
        if ($this->stats['total'] % 100 == 0) {
            Log::info("Processing progress: {$this->stats['total']} rows completed", [
                'updated' => $this->stats['updated'],
                'new' => $this->stats['new'],
                'errors' => $this->stats['errors'],
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);
            
            // Force garbage collection every 100 rows
            gc_collect_cycles();
        }

        try {
            // Check if this is an empty row (skip completely empty rows)
            $hasData = false;
            foreach ($row as $value) {
                if (!empty(trim($value))) {
                    $hasData = true;
                    break;
                }
            }
            
            if (!$hasData) {
                // Skip completely empty rows without logging
                return null;
            }

            // Extract basic item information
            $itemInfo = $this->extractItemInfo($row);
            
            if (!$itemInfo || empty($itemInfo['item_code'])) {
                $this->stats['errors']++;
                
                // Only log first 10 missing item codes to avoid log spam
                if ($this->stats['errors'] <= 10) {
                    Log::warning('Row skipped - missing item code', [
                        'row' => $this->stats['total'],
                        'available_columns' => array_keys($row),
                        'sample_data' => array_slice($row, 0, 5, true)
                    ]);
                }
                return null;
            }

            // Process each price list column
            $this->processMatrixPrices($row, $itemInfo);
            
            // Return null since we handle updates directly
            return null;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Matrix price import error: ' . $e->getMessage(), [
                'row' => $this->stats['total'],
                'error' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Extract item information from row.
     */
    protected function extractItemInfo(array $row): ?array
    {
        $itemInfo = [];
        
        // Map basic product information fields with more variations
        $basicFields = [
            'item_code' => ['Item Code', 'item_code', 'Product Code', 'product_code', 'ItemCode', 'ProductCode', 'Code'],
            'item_description' => ['Item Description', 'item_description', 'Product Description', 'Description', 'product_description', 'ItemDescription'],
            'uom' => ['UOM', 'uom', 'Unit', 'unit', 'Unit of Measure'],
            'major_desc' => ['Major_Desc', 'major_desc', 'Major Description', 'MajorDesc', 'Major Category'],
            'minor_desc' => ['Minor_Desc', 'minor_desc', 'Minor Description', 'MinorDesc', 'Minor Category'],
            'sub_minor_desc' => ['Sub Minor Desc', 'sub_minor_desc', 'Sub Minor Description', 'SubMinorDesc'],
            'brand' => ['Brand', 'brand', 'Brand Name'],
            'start_date_active' => ['Start Date Active', 'start_date_active', 'StartDateActive', 'Start Date', 'start_date', 'Effective Date', 'effective_date'],
            'end_date_active' => ['End Date Active', 'end_date_active', 'EndDateActive', 'End Date', 'end_date', 'Expiry Date', 'expiry_date'],
            'system_last_update_date' => ['System Last Update Date', 'system_last_update_date'],
            'system_creation_date' => ['System Creation Date', 'system_creation_date']
        ];

        foreach ($basicFields as $field => $possibleColumns) {
            foreach ($possibleColumns as $column) {
                // First try exact match
                if (isset($row[$column]) && !empty(trim($row[$column]))) {
                    $itemInfo[$field] = trim($row[$column]);
                    break;
                }
                
                // Then try case-insensitive match
                foreach ($row as $rowKey => $rowValue) {
                    if (strcasecmp($rowKey, $column) === 0 && !empty(trim($rowValue))) {
                        $itemInfo[$field] = trim($rowValue);
                        break 2; // Break out of both loops
                    }
                }
            }
        }

        return !empty($itemInfo['item_code']) ? $itemInfo : null;
    }

    /**
     * Process matrix format prices for each price list column.
     */
    protected function processMatrixPrices(array $row, array $itemInfo): void
    {
        // Get all supported price list names and their mappings
        // Note: Karachi-Wholesale is stored without spaces in Oracle (special case)
        $supportedPriceLists = [
            'Karachi - Corporate' => ['price_list_id' => '7010', 'price_type' => 'corporate'],
            'Karachi-Wholesale' => ['price_list_id' => '7011', 'price_type' => 'wholesaler'],
            'Karachi - Trade Price' => ['price_list_id' => '7012', 'price_type' => 'trade'],
            'Lahore - Corporate' => ['price_list_id' => '7007', 'price_type' => 'corporate'],
            'Lahore - Wholesale' => ['price_list_id' => '7008', 'price_type' => 'wholesaler'],
            'Lahore - Trade Price' => ['price_list_id' => '7009', 'price_type' => 'trade'],
            'QG HBM' => ['price_list_id' => '1116080', 'price_type' => 'hbm'],
        ];

        foreach ($row as $columnName => $priceValue) {
            // Skip if this is not a price column or if price is empty/blank
            if (empty($priceValue) || !is_numeric($priceValue) || floatval($priceValue) <= 0) {
                continue;
            }

            // Find matching price list
            $priceListMapping = null;
            $matchedPriceListName = null;

            foreach ($supportedPriceLists as $priceListName => $mapping) {
                if ($this->isMatchingPriceList($columnName, $priceListName)) {
                    $priceListMapping = $mapping;
                    $matchedPriceListName = $priceListName;
                    break;
                }
            }

            if (!$priceListMapping) {
                continue; // Skip unknown price columns
            }

            // Process this price
            $this->processSinglePrice($itemInfo, $matchedPriceListName, $priceListMapping, floatval($priceValue));
        }
    }

    /**
     * Check if column name matches a price list name.
     */
    protected function isMatchingPriceList(string $columnName, string $priceListName): bool
    {
        $columnLower = strtolower(trim($columnName));
        $priceListLower = strtolower(trim($priceListName));

        // Exact match
        if ($columnLower === $priceListLower) {
            return true;
        }

        // City-specific matching to prevent cross-city mapping
        $priceListWords = explode(' ', str_replace(['-', '_'], ' ', $priceListLower));
        $columnWords = explode(' ', str_replace(['-', '_'], ' ', $columnLower));

        // First check if city matches (Karachi or Lahore)
        $cityMatch = false;
        if (in_array('karachi', $priceListWords) && in_array('karachi', $columnWords)) {
            $cityMatch = true;
        } elseif (in_array('lahore', $priceListWords) && in_array('lahore', $columnWords)) {
            $cityMatch = true;
        } elseif (in_array('qg', $priceListWords) && in_array('qg', $columnWords)) {
            $cityMatch = true; // For QG HBM
        } elseif (in_array('hbm', $priceListWords) && in_array('hbm', $columnWords)) {
            $cityMatch = true; // For QG HBM
        }

        // If no city match, return false to prevent cross-city mapping
        if (!$cityMatch && (in_array('karachi', $priceListWords) || in_array('lahore', $priceListWords))) {
            return false;
        }

        // Count other matching words (excluding city)
        $matchCount = 0;
        foreach ($priceListWords as $word) {
            if ($word !== 'karachi' && $word !== 'lahore' && in_array($word, $columnWords)) {
                $matchCount++;
            }
        }

        // Match if city matches AND at least 1 other word matches (e.g., "corporate", "trade", "wholesale")
        return $cityMatch && $matchCount >= 1;
    }

    /**
     * Process a single price for a specific price list (optimized for large files).
     */
    protected function processSinglePrice(array $itemInfo, string $priceListName, array $priceListMapping, float $newPrice): void
    {
        $itemCode = $itemInfo['item_code'];
        $priceListId = $priceListMapping['price_list_id'];
        $priceType = $priceListMapping['price_type'];
        $uom = $itemInfo['uom'] ?? 'PCS'; // Default UOM if not provided

        try {
            // Validate against MySQL item_prices table or Oracle item_master
            $existingItem = $this->validateOracleItem($itemCode, $uom);
            if (!$existingItem) {
                $this->stats['errors']++;
                return;
            }

            // If it's a new item found in Oracle but not yet in our price table,
            // we should also push it to Oracle as per instructions
            if (isset($existingItem->is_new_from_oracle) && $existingItem->is_new_from_oracle) {
                try {
                    OraclePriceListUpdate::create([
                        'item_code' => $itemCode,
                        'new_price' => $newPrice,
                        'list_header_id' => $priceListId,
                        'currency_code' => 'PKR',
                        'start_date' => now(),
                        'processed_flag' => 'N',
                    ]);
                    Log::info('New item discovered in Oracle Master, pushed price to Oracle', [
                        'item_code' => $itemCode,
                        'price' => $newPrice,
                        'price_list' => $priceListName
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to push new item price to Oracle: ' . $e->getMessage());
                }
            }

            // Get current price before updating (for comparison) - use same criteria as Oracle sync
            $currentRecord = ItemPrice::where('item_code', $itemCode)
                ->where('price_list_name', $priceListName)
                ->where('uom', $existingItem->uom)
                ->first();

            $previousPrice = $currentRecord ? $currentRecord->list_price : null;

            // Use existing MySQL data for accurate information - consistent with Oracle sync
            $result = ItemPrice::updateOrCreate(
                [
                    'item_code' => $itemCode,
                    'price_list_name' => $priceListName, // Use price_list_name instead of price_list_id
                    'uom' => $existingItem->uom, // Use existing UOM
                ],
                [
                    'price_list_name' => $priceListName,
                    'price_list_id' => $priceListId, // Also set price_list_id for consistency
                    'list_price' => $newPrice,
                    'previous_price' => $previousPrice, // Save old price for comparison
                    'item_description' => $existingItem->description, // Use existing description
                    'uom' => $existingItem->uom, // Use existing UOM
                    'price_type' => $priceType,
                    'major_desc' => $itemInfo['major_desc'] ?? $existingItem->major_desc ?? '',
                    'minor_desc' => $itemInfo['minor_desc'] ?? '',
                    'sub_minor_desc' => $itemInfo['sub_minor_desc'] ?? '',
                    'brand' => $itemInfo['brand'] ?? '',
                    'price_updated_at' => now(),
                    'price_updated_by' => auth()->id(),
                    'start_date_active' => $this->parseDate($itemInfo['start_date_active'] ?? null) ?? now(),
                    'end_date_active' => $this->parseDate($itemInfo['end_date_active'] ?? null),
                    'system_last_update_date' => $this->parseDate($itemInfo['system_last_update_date'] ?? null),
                    'system_creation_date' => $this->parseDate($itemInfo['system_creation_date'] ?? null),
                ]
            );

            if ($result->wasRecentlyCreated) {
                $this->stats['new']++;
            } else {
                // Check if price actually changed
                if ((float) $result->getOriginal('list_price') !== $newPrice) {
                    $result->update([
                        'previous_price' => $result->getOriginal('list_price'),
                        'price_changed' => true,
                    ]);
                    $this->stats['updated']++;
                }
            }

            // Reduced logging for better performance
            if ($this->stats['total'] <= 10 || $this->stats['total'] % 500 == 0) {
                Log::info('Price processed', [
                    'item_code' => $itemCode,
                    'price_list' => $priceListName,
                    'price' => $newPrice,
                    'action' => $result->wasRecentlyCreated ? 'created' : 'updated'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process price', [
                'item_code' => $itemCode,
                'price_list' => $priceListName,
                'error' => $e->getMessage()
            ]);
            $this->stats['errors']++;
        }
    }

    /**
     * Validation rules for each row.
     */
    public function rules(): array
    {
        return [
            'item_code' => 'required|string',
            'price_list_id' => 'required|string',
            'list_price' => 'required|numeric|min:0',
            'price_type' => 'nullable|string|in:corporate,wholesaler,hbm',
        ];
    }

    /**
     * Get the batch size for processing.
     */
    public function batchSize(): int
    {
        return 50; // Reduced from 100 for better memory management
    }

    /**
     * Get the chunk size for reading.
     */
    public function chunkSize(): int
    {
        return 50; // Reduced from 100 to prevent memory issues
    }

    /**
     * Map Excel column headers to expected field names.
     */
    protected function mapExcelColumns(array $row): array
    {
        $mapped = [];
        
        // Always log first few rows for debugging
        if ($this->stats['total'] <= 3) {
            $columnData = [
                'row_number' => $this->stats['total'],
                'keys' => array_keys($row),
                'sample_values' => array_map(function($value) { return is_string($value) ? substr($value, 0, 50) : $value; }, $row)
            ];
            Log::info('Available Excel columns:', $columnData);
            
            // Store for console output
            if (!isset($GLOBALS['excel_debug_columns'])) {
                $GLOBALS['excel_debug_columns'] = [];
            }
            $GLOBALS['excel_debug_columns'][] = $columnData;
        }
        
        // Mapping for Excel column headers - includes both expected and actual variations
        $columnMapping = [
            'item_code' => ['Item Code', 'item_code'],
            'price_list_name' => ['Price List Name', 'price_list_name'],  
            'major_desc' => ['Major_Desc', 'major_desc'],
            'minor_desc' => ['Minor_Desc', 'minor_desc'],
            'sub_minor_desc' => ['Sub Minor Desc', 'sub_minor_desc'],
            'brand' => ['Brand', 'brand'],
            'item_description' => ['Item Description', 'item_description'],
            'uom' => ['UOM', 'uom'],
            'listprice' => ['List Price', 'list_price', 'listprice'],
            'start_date_active' => ['Start Date Active', 'start_date_active'],
            'end_date_active' => ['End Date Active', 'end_date_active'],
            'system_last_update_date' => ['System Last Update Date', 'system_last_update_date'],
            'system_creation_date' => ['System Creation Date', 'system_creation_date']
        ];
        
        // Map each field by checking the possible column names (case-insensitive and flexible)
        foreach ($columnMapping as $field => $possibleKeys) {
            $mapped[$field] = ($field === 'listprice') ? 0 : '';
            
            foreach ($possibleKeys as $key) {
                // Check exact match first
                if (isset($row[$key])) {
                    if ($field === 'listprice') {
                        $numericValue = floatval($row[$key]);
                        if ($numericValue > 0) {
                            $mapped[$field] = $numericValue;
                            break;
                        }
                    } else {
                        $stringValue = trim($row[$key]);
                        if (!empty($stringValue)) {
                            $mapped[$field] = $stringValue;
                            break;
                        }
                    }
                }
                
                // If no exact match, try case-insensitive matching
                foreach ($row as $rowKey => $rowValue) {
                    if (strtolower(trim($rowKey)) === strtolower(trim($key))) {
                        if ($field === 'listprice') {
                            $numericValue = floatval($rowValue);
                            if ($numericValue > 0) {
                                $mapped[$field] = $numericValue;
                                break 2; // Break out of both loops
                            }
                        } else {
                            $stringValue = trim($rowValue);
                            if (!empty($stringValue)) {
                                $mapped[$field] = $stringValue;
                                break 2; // Break out of both loops
                            }
                        }
                    }
                }
            }
        }
        
        // Ensure price is numeric
        if ($mapped['listprice'] <= 0) {
            Log::warning('Price not found or invalid', [
                'listprice_value' => $mapped['listprice'],
                'row_data' => $row
            ]);
        }
        
        // Debug: Log the mapping result for first few rows
        if ($this->stats['total'] <= 5) {
            $mappingData = [
                'row_number' => $this->stats['total'],
                'item_code' => $mapped['item_code'],
                'price_list_name' => $mapped['price_list_name'],
                'listprice' => $mapped['listprice'],
                'uom' => $mapped['uom'],
                'item_description' => $mapped['item_description'],
                'start_date_active' => $mapped['start_date_active'],
                'all_mapped_data' => $mapped,
                'missing_required_fields' => [
                    'item_code_missing' => empty($mapped['item_code']),
                    'price_list_name_missing' => empty($mapped['price_list_name']),
                    'listprice_invalid' => $mapped['listprice'] <= 0
                ]
            ];
            Log::info('Column mapping result:', $mappingData);
            
            // Store for console output
            if (!isset($GLOBALS['excel_debug_mapping'])) {
                $GLOBALS['excel_debug_mapping'] = [];
            }
            $GLOBALS['excel_debug_mapping'][] = $mappingData;
        }
        
        return $mapped;
    }

    /**
     * Map price list name to price list ID and type.
     */
    protected function mapPriceListNameToType(string $priceListName): ?array
    {
        // Map based on the getPriceListFilters function from ProductController
        $mappings = [
            // Corporate - with and without spaces
            'Karachi - Corporate' => ['price_list_id' => '7010', 'price_type' => 'corporate'],
            'Karachi-Corporate' => ['price_list_id' => '7010', 'price_type' => 'corporate'],
            'Lahore - Corporate' => ['price_list_id' => '7007', 'price_type' => 'corporate'],
            'Lahore-Corporate' => ['price_list_id' => '7007', 'price_type' => 'corporate'],
            
            // Wholesaler - Karachi uses no spaces (special case), Lahore uses spaces
            'Karachi-Wholesale' => ['price_list_id' => '7011', 'price_type' => 'wholesaler'],
            'Karachi - Wholesale' => ['price_list_id' => '7011', 'price_type' => 'wholesaler'], // Fallback for Excel variations
            'Lahore - Wholesale' => ['price_list_id' => '7008', 'price_type' => 'wholesaler'],
            'Lahore-Wholesale' => ['price_list_id' => '7008', 'price_type' => 'wholesaler'], // Fallback
            
            // Trade - with and without spaces
            'Karachi - Trade Price' => ['price_list_id' => '7012', 'price_type' => 'trade'],
            'Karachi-Trade Price' => ['price_list_id' => '7012', 'price_type' => 'trade'],
            'Lahore - Trade Price' => ['price_list_id' => '7009', 'price_type' => 'trade'],
            'Lahore-Trade Price' => ['price_list_id' => '7009', 'price_type' => 'trade'],
            
            // HBM
            'QG HBM' => ['price_list_id' => '1116080', 'price_type' => 'hbm'],
        ];

        // Try exact match first
        if (isset($mappings[$priceListName])) {
            return $mappings[$priceListName];
        }

        // Try partial matches for flexibility
        $lowerName = strtolower($priceListName);
        
        if (strpos($lowerName, 'corporate') !== false) {
            if (strpos($lowerName, 'karachi') !== false) {
                return ['price_list_id' => '7010', 'price_type' => 'corporate'];
            } elseif (strpos($lowerName, 'lahore') !== false) {
                return ['price_list_id' => '7007', 'price_type' => 'corporate'];
            }
        }
        
        if (strpos($lowerName, 'wholesale') !== false) {
            if (strpos($lowerName, 'karachi') !== false) {
                return ['price_list_id' => '7011', 'price_type' => 'wholesaler'];
            } elseif (strpos($lowerName, 'lahore') !== false) {
                return ['price_list_id' => '7008', 'price_type' => 'wholesaler'];
            }
        }
        
        if (strpos($lowerName, 'trade') !== false) {
            if (strpos($lowerName, 'karachi') !== false) {
                return ['price_list_id' => '7012', 'price_type' => 'trade'];
            } elseif (strpos($lowerName, 'lahore') !== false) {
                return ['price_list_id' => '7009', 'price_type' => 'trade'];
            }
        }
        
        if (strpos($lowerName, 'hbm') !== false || strpos($lowerName, 'qg') !== false) {
            return ['price_list_id' => '1116080', 'price_type' => 'hbm'];
        }

        return null;
    }

    /**
     * Parse date from Excel format.
     */
    protected function parseDate($dateValue): ?string
    {
        if (!$dateValue) {
            return null;
        }

        try {
            // Handle Excel date formats
            if (is_numeric($dateValue)) {
                // Excel serial date
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                return $date->format('Y-m-d H:i:s');
            }
            
            // Try DD/MM/YYYY format first (European format like 01/09/2025)
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                $date = Carbon::createFromFormat('d/m/Y', $dateValue);
                return $date->format('Y-m-d H:i:s');
            }
            
            // Try MM/DD/YYYY format
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                try {
                    $date = Carbon::createFromFormat('m/d/Y', $dateValue);
                    return $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // If MM/DD/YYYY fails, fallback to DD/MM/YYYY already handled above
                }
            }
            
            // Try to parse other various date formats
            $date = Carbon::parse($dateValue);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', ['value' => $dateValue, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get processed data (for comparison mode).
     */
    public function getProcessedData(): array
    {
        // Since we're not in comparison mode anymore, return empty array
        // The data is already saved to database in regular mode
        return [];
    }
    
    /**
     * Get debug data for console output (optimized for large files)
     */
    public function getDebugData(): array
    {
        // Return minimal debug data for large files to save memory
        return [
            'total_processed' => $this->stats['total'],
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
            'peak_memory' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB',
            'stats' => $this->stats
        ];
    }

    /**
     * Clear memory-intensive debug globals to prevent memory leaks
     */
    public function clearDebugGlobals(): void
    {
        unset($GLOBALS['excel_file_info']);
        unset($GLOBALS['excel_raw_analysis']);
        unset($GLOBALS['excel_model_calls']);
        unset($GLOBALS['excel_structure_info']);
        unset($GLOBALS['excel_structure_error']);
        unset($GLOBALS['excel_raw_structure']);
        unset($GLOBALS['excel_debug_data']);
        unset($GLOBALS['excel_debug_columns']);
        unset($GLOBALS['excel_debug_mapping']);
        
        gc_collect_cycles();
    }

    /**
     * Update the upload record with final statistics.
     */
    public function updateUploadRecord()
    {
        if ($this->uploadRecord) {
            $this->uploadRecord->update([   
                'total_rows' => $this->stats['total'],
                'updated_rows' => $this->stats['updated'],
                'new_rows' => $this->stats['new'],
                'error_rows' => $this->stats['errors'],
                'status' => $this->stats['errors'] > 0 ? 'completed': 'completed',
                'error_details' => [
                    'failures' => $this->failures(),
                    'errors' => $this->errors(),
                ],
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Get statistics about the import operation.
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    protected function validateOracleItem(string $itemCode, string $uom): ?object
    {
        try {
            // First check local MySQL item_prices table
            $item = ItemPrice::where('item_code', $itemCode)
                ->where('uom', $uom)
                ->select([
                    'item_code',
                    'item_description as description',
                    'uom',
                    'major_desc',
                    'minor_desc'
                ])
                ->first();

            if ($item) {
                return (object) $item->toArray();
            }

            // If not found in local table, check Oracle item_master
            $oracleItem = OracleItem::where('item_code', $itemCode)
                ->where(function($q) use ($uom) {
                    $q->where('primary_uom_code', $uom)
                      ->orWhere('secondary_uom_code', $uom);
                })
                ->first();

            if ($oracleItem) {
                Log::info('Item found in Oracle item_master (missing in local item_prices)', [
                    'item_code' => $itemCode,
                    'uom' => $uom
                ]);

                return (object) [
                    'item_code' => $oracleItem->item_code,
                    'description' => $oracleItem->item_description,
                    'uom' => $uom,
                    'major_desc' => '',
                    'minor_desc' => '',
                    'is_new_from_oracle' => true
                ];
            }

            // Still not found, log warning
            Log::warning('Item not found in any table', [
                'item_code' => $itemCode,
                'uom' => $uom
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Validation failed', [
                'item_code' => $itemCode,
                'uom' => $uom,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}