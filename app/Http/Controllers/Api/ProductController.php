<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /*
     * Retrieve all products.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function products()
    {
        $user = Auth::user();
        
        // Get pagination parameters
        $page = request()->input('page', 1);
        $perPage = request()->input('per_page', 10);
        $perPage = min($perPage, 100); // Limit max per page to prevent memory issues
        
        // Get filter parameter
        $filter = request()->input('filter', 'default'); // default, vendor, wholesaler, corporate, trade, karachi, lahore, karachi-trade-price, karachi-wholesale, karachi-corporate, lahore-trade-price, lahore-wholesale, lahore-corporate, qg-hbm
        
        // Create cache key based on pagination, filter, and user
        $cacheKey = "products_page_{$page}_per_page_{$perPage}_filter_{$filter}_user_{$user->id}";
        $cacheTime = 60; // Cache for 60 minutes
        
        // Use cache to store the expensive query results
        $result = Cache::remember($cacheKey, $cacheTime, function () use ($page, $perPage, $filter, $user) {
            // Define price list filters with role-based filtering and customer assignments
            $priceListFilters = $this->getPriceListFilters($filter, $user);
            
            // Apply role-based price list filtering for non-admin users
            if ($user->role !== 'admin') {
                if ($user->role === 'supply-chain') {
                    
                    // Supply-chain users see price lists based on their Oracle organizations
                    $allowedPriceLists = $this->getOracleOrganizationPriceLists($user);
                    if (!empty($allowedPriceLists)) {
                        if (empty($priceListFilters)) {
                            $priceListFilters = $allowedPriceLists;
                        } else {
                            $priceListFilters = array_intersect($priceListFilters, $allowedPriceLists);
                        }
                    }
                } elseif ($user->role === 'user') {
                    // Salesperson users see price lists based on their customers' price_list_id
                    $allowedPriceLists = $this->getSalespersonPriceLists($user);
                    if (!empty($allowedPriceLists)) {
                        if (empty($priceListFilters)) {
                            $priceListFilters = $allowedPriceLists;
                        } else {
                            $priceListFilters = array_intersect($priceListFilters, $allowedPriceLists);
                        }
                    }
                }
            }
            
            // Determine user's location BEFORE querying products
            $userLocation = null;
            $khiOuIds = [102, 103, 104, 105, 106];
            $lhrOuIds = [108, 109];

            if ($user->role !== 'admin') {
                if ($user->role === 'user' || $user->role === 'khi-sales-head') {
                    // Salesperson - get location from customers
                    $customerOuId = \App\Models\Customer::where('salesperson', $user->name)
                        ->whereNotNull('ou_id')
                        ->value('ou_id');
                    if ($customerOuId) {
                        if (in_array($customerOuId, $khiOuIds)) {
                            $userLocation = 1; // Karachi
                        } elseif (in_array($customerOuId, $lhrOuIds)) {
                            $userLocation = 2; // Lahore
                        }
                    }
                } else {
                    // Other roles - use organization mapping
                    $userOrgs = $user->getOracleOrganizations();
                    if (!empty(array_intersect($userOrgs, $khiOuIds))) {
                        $userLocation = 1; // Karachi
                    } elseif (!empty(array_intersect($userOrgs, $lhrOuIds))) {
                        $userLocation = 2; // Lahore
                    }
                }
            }

            // Use Eloquent with proper relationships for better performance

            // Build products query with location-based filtering
            $productsQuery = Item::with(['itemPrices' => function ($query) use ($userLocation) {
                    // Load prices based on user location
                    $query->select('id', 'item_id', 'item_code', 'price_list_id', 'price_list_name', 'uom', 'list_price', 'start_date_active', 'end_date_active');

                    // Filter prices by location if applicable
                    if ($userLocation === 1) {
                        // Karachi - filter to Karachi prices only
                        $query->where(function($q) {
                            $q->where('price_list_name', 'like', 'Karachi%')
                              ->orWhere('price_list_name', 'like', 'karachi%');
                        });
                    } elseif ($userLocation === 2) {
                        // Lahore - filter to Lahore prices only
                        $query->where(function($q) {
                            $q->where('price_list_name', 'like', 'Lahore%')
                              ->orWhere('price_list_name', 'like', 'lahore%');
                        });
                    }
                }])
                ->select('id', 'inventory_item_id', 'item_code', 'item_description',
                        'primary_uom_code', 'secondary_uom_code', 'major_category',
                        'minor_category', 'sub_minor_category', 'created_at', 'updated_at');

            // Filter products to only show items that have prices for user's location
            if ($userLocation !== null) {
                $priceListPattern = $userLocation === 1 ? 'Karachi%' : 'Lahore%';

                $productsQuery->whereHas('itemPrices', function($q) use ($priceListPattern) {
                    $q->where('price_list_name', 'like', $priceListPattern);
                });
            }

            $products = $productsQuery->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
            
            // Transform the data to include prices array based on filter
            $transformedData = $products->getCollection()->map(function ($item) use ($priceListFilters, $filter, $user, $userLocation) {
                // Define the 7 price list types
                $priceListTypes = [
                    'karachi_trade_price' => '7012',
                    'karachi_wholesale' => '7011',
                    'karachi_corporate' => '7010',
                    'lahore_trade_price' => '7009',
                    'lahore_wholesale' => '7008',
                    'lahore_corporate' => '7007',
                    'qg_hbm' => '1116080'
                ];

                // Filter price list types based on the filter parameter
                $filteredPriceListTypes = $priceListTypes;
                if (!empty($priceListFilters)) {
                    $filteredPriceListTypes = array_filter($priceListTypes, function ($priceListId) use ($priceListFilters) {
                        return in_array($priceListId, $priceListFilters);
                    });
                }

                // Create prices array - filter by price_list_name patterns
                $prices = [];

                // Normalize filter first for case-insensitive matching
                $normalizedFilter = strtolower($filter);
                // Note: userLocation is already determined above before querying products

                // Define which price_list_names to include based on filter
                $filterPatterns = [
                    'default' => [], // Will be modified based on location below
                    'karachi' => ['karachi'],
                    'karachi-trade' => ['karachi', 'trade'],
                    'karachi-trade-price' => ['karachi', 'trade'],
                    'karachi-wholesale' => ['karachi', 'wholesale'],
                    'karachi-corporate' => ['karachi', 'corporate'],
                    'lahore' => ['lahore'],
                    'lahore-trade' => ['lahore', 'trade'],
                    'lahore-trade-price' => ['lahore', 'trade'],
                    'lahore-wholesale' => ['lahore', 'wholesale'],
                    'lahore-corporate' => ['lahore', 'corporate'],
                    'trade' => ['trade'],
                    'wholesaler' => ['wholesale'],
                    'corporate' => ['corporate'],
                    'vendor' => ['hbm', 'vendor'],
                    'qg-hbm' => ['hbm', 'vendor'],
                ];

                $currentFilterPatterns = $filterPatterns[$normalizedFilter] ?? [];

                // Apply location-based filtering for default filter
                if ($normalizedFilter === 'default' && $userLocation !== null) {
                    if ($userLocation === 1) {
                        // Karachi users see only Karachi prices
                        $currentFilterPatterns = ['karachi'];
                    } elseif ($userLocation === 2) {
                        // Lahore users see only Lahore prices
                        $currentFilterPatterns = ['lahore'];
                    }
                }


                // Map price list names to display types (matching actual database values)
                $priceListNameMap = [
                    // Karachi (actual DB values when normalized)
                    'karachi - corporate' => 'karachi_corporate',        // Actual: "Karachi - Corporate"
                    'karachi - trade price' => 'karachi_trade_price',    // Actual: "Karachi - Trade Price"
                    'karachi-wholesale' => 'karachi_wholesale',           // Actual: "Karachi-Wholesale"

                    // Lahore (actual DB values when normalized)
                    'lahore - corporate' => 'lahore_corporate',           // Actual: "Lahore - Corporate"
                    'lahore - trade price' => 'lahore_trade_price',       // Actual: "Lahore - Trade Price"
                    'lahore - wholesale' => 'lahore_wholesale',           // Actual: "Lahore - Wholesale"

                    // Additional variations (for compatibility)
                    'karachi corporate' => 'karachi_corporate',
                    'karachi-corporate' => 'karachi_corporate',
                    'karachi trade price' => 'karachi_trade_price',
                    'karachi wholesale' => 'karachi_wholesale',
                    'lahore corporate' => 'lahore_corporate',
                    'lahore-corporate' => 'lahore_corporate',
                    'lahore trade price' => 'lahore_trade_price',
                    'lahore wholesale' => 'lahore_wholesale',
                    'lahore-wholesale' => 'lahore_wholesale',

                    // Vendor
                    'qg hbm' => 'qg_hbm',
                    'vendor' => 'qg_hbm',
                ];

                foreach ($item->itemPrices as $priceRecord) {
                    if (!$priceRecord || $priceRecord->list_price === null) {
                        continue;
                    }

                    $normalizedName = strtolower(trim($priceRecord->price_list_name ?? ''));

                    // Apply filter - check if price name contains required patterns
                    if (!empty($currentFilterPatterns)) {
                        $matchesFilter = true;
                        foreach ($currentFilterPatterns as $pattern) {
                            if (strpos($normalizedName, $pattern) === false) {
                                $matchesFilter = false;
                                break;
                            }
                        }
                        if (!$matchesFilter) {
                            continue; // Skip this price
                        }
                    }

                    // Get display type
                    $matchedType = $priceListNameMap[$normalizedName] ?? 'unknown';

                    $prices[] = [
                        'type' => $matchedType,
                        'price_list_id' => $priceRecord->price_list_id,
                        'price_list_name' => $priceRecord->price_list_name,
                        'uom' => $priceRecord->uom,
                        'list_price' => $priceRecord->list_price,
                        'start_date_active' => $priceRecord->start_date_active,
                        'end_date_active' => $priceRecord->end_date_active,
                    ];
                }

                return [
                    'id' => $item->id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'item_code' => $item->item_code,
                    'item_description' => $item->item_description,
                    'primary_uom_code' => $item->primary_uom_code,
                    'secondary_uom_code' => $item->secondary_uom_code,
                    'major_category' => $item->major_category,
                    'minor_category' => $item->minor_category,
                    'sub_minor_category' => $item->sub_minor_category,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                    'prices' => $prices,
                ];
            });
            
            return [
                'data' => $transformedData,
                'pagination' => [
                    'total' => $products->total(),
                    'count' => $transformedData->count(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'total_pages' => $products->lastPage(),
                    'next_page_url' => $products->hasMorePages(),
                    'prev_page_url' => $products->currentPage() > 1,
                ],
            ];
        });
        
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ], 200);
    }
    
    /**
     * Retrieve a specific product's details.
     */
    public function getProduct(Request $request): JsonResponse
    {
        // Validate the request to ensure 'inventory_item_id' is provided and exists
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:items,inventory_item_id',
        ]);

        $cacheKey = 'product_details_' . $validated['inventory_item_id'];
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $product = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            return Item::with(['itemPrice'])
                ->where('inventory_item_id', $validated['inventory_item_id'])
                ->first();
        });

        if (! $product) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Product not found',
            ], 404);
        }

        // Map the product details manually
        $mappedProduct = [
            'inventory_item_id' => $product->inventory_item_id,
            'item_code' => $product->item_code,
            'item_description' => $product->item_description,
            'primary_uom_code' => $product->primary_uom_code,
            'secondary_uom_code' => $product->secondary_uom_code,
            'major_category' => $product->major_category,
            'minor_category' => $product->minor_category,
            'sub_minor_category' => $product->sub_minor_category,
        ];

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Product details retrieved successfully',
            'data' => $mappedProduct,
        ], 200);
    }

    /**
     * Search for products by inventory_item_id, item_code, or item_description using LIKE and map results.
     */
    public function searchProduct(Request $request): JsonResponse
    {
        // Validate the request to ensure 'searchTerm' is provided
        $validated = $request->validate([
            'searchTerm' => 'required|string',
            'filter' => 'nullable|string|in:default,vendor,wholesaler,corporate,trade,karachi,lahore,karachi-trade-price,karachi-wholesale,karachi-corporate,lahore-trade-price,lahore-wholesale,lahore-corporate,qg-hbm',
        ]);

        // Extract the search term and break it into individual words
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm);
        $filter = $validated['filter'] ?? 'default';
        $user = Auth::user();

        // Generate a cache key based on the search term, filter, and user
        $cacheKey = 'search_products_' . md5($searchTerm . '_' . $filter . '_' . $user->id);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $products = Cache::remember($cacheKey, $cacheTime, function () use ($terms, $searchTerm, $filter, $user) {
            // Define price list filters with role-based filtering and customer assignments
            $priceListFilters = $this->getPriceListFilters($filter, $user);
            
            // Apply role-based price list filtering for non-admin users
            if ($user->role !== 'admin') {
                if ($user->role === 'supply-chain') {
                    // Supply-chain users see price lists based on their Oracle organizations
                    $allowedPriceLists = $this->getOracleOrganizationPriceLists($user);
                    if (!empty($allowedPriceLists)) {
                        if (empty($priceListFilters)) {
                            $priceListFilters = $allowedPriceLists;
                        } else {
                            $priceListFilters = array_intersect($priceListFilters, $allowedPriceLists);
                        }
                    }
                } elseif ($user->role === 'user') {
                    // Salesperson users see price lists based on their customers' price_list_id
                    $allowedPriceLists = $this->getSalespersonPriceLists($user);
                    if (!empty($allowedPriceLists)) {
                        if (empty($priceListFilters)) {
                            $priceListFilters = $allowedPriceLists;
                        } else {
                            $priceListFilters = array_intersect($priceListFilters, $allowedPriceLists);
                        }
                    }
                }
            }
            
            return Item::with(['itemPrices' => function ($query) use ($priceListFilters) {
                    // Load all 7 price list types or filtered ones based on filter parameter
                    $allPriceListIds = ['7012', '7011', '7010', '7009', '7008', '7007', '1116080'];
                    $targetPriceListIds = !empty($priceListFilters) ? $priceListFilters : $allPriceListIds;

                    $query->select('id', 'item_id', 'item_code', 'price_list_id', 'price_list_name', 'uom', 'list_price', 'start_date_active', 'end_date_active')
                          ->whereIn('price_list_id', $targetPriceListIds);
                }])
                ->select('id', 'inventory_item_id', 'item_code', 'item_description', 
                        'primary_uom_code', 'secondary_uom_code', 'major_category', 
                        'minor_category', 'sub_minor_category', 'created_at', 'updated_at')
                ->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        // Ensure each term is present in either item_description or item_code
                        $query->where(function ($q) use ($term) {
                            $q->where('item_description', 'like', '%' . $term . '%')
                                ->orWhere('item_code', 'like', '%' . $term . '%');
                        });
                    }
                })
                ->orderByRaw("
                        CASE
                            WHEN item_description LIKE ? OR item_code LIKE ? THEN 1                -- Exact match
                            WHEN item_description LIKE ? OR item_code LIKE ? THEN 2                -- Terms in order
                            WHEN item_description LIKE ? OR item_code LIKE ? THEN 3                -- Terms in reverse order
                            ELSE 4
                        END
                    ", [
                    "%$searchTerm%",
                    "%$searchTerm%",                     // Exact full-term match in either column
                    "%" . implode('%', $terms) . "%",
                    "%" . implode('%', $terms) . "%",    // Terms in order
                    "%" . implode('%', array_reverse($terms)) . "%",
                    "%" . implode('%', array_reverse($terms)) . "%" // Terms in reverse order
                ])
                ->limit(50) // Limit search results for better performance
                ->get();
        });

        // Map the results to include prices array based on filter
        $priceListFilters = $this->getPriceListFilters($filter);
        $mappedProducts = $products->map(function ($item) use ($priceListFilters) {
            // Define the 7 price list types
            $priceListTypes = [
                'karachi_trade_price' => '7012',
                'karachi_wholesale' => '7011', 
                'karachi_corporate' => '7010',
                'lahore_trade_price' => '7009',
                'lahore_wholesale' => '7008',
                'lahore_corporate' => '7007',
                'qg_hbm' => '1116080'
            ];
            
            // Filter price list types based on the filter parameter
            $filteredPriceListTypes = $priceListTypes;
            if (!empty($priceListFilters)) {
                $filteredPriceListTypes = array_filter($priceListTypes, function ($priceListId) use ($priceListFilters) {
                    return in_array($priceListId, $priceListFilters);
                });
            }
            
            // Create prices array with filtered types - include all UOMs
            $prices = [];
            foreach ($filteredPriceListTypes as $type => $priceListId) {
                $matchingPrices = $item->itemPrices->where('price_list_id', $priceListId);
                
                foreach ($matchingPrices as $priceRecord) {
                    // Only include if price record has actual data
                    if ($priceRecord && $priceRecord->list_price !== null) {
                        $prices[] = [
                            'type' => $type,
                            'price_list_id' => $priceRecord->price_list_id,
                            'price_list_name' => $priceRecord->price_list_name,
                            'uom' => $priceRecord->uom,
                            'list_price' => $priceRecord->list_price,
                            'start_date_active' => $priceRecord->start_date_active,
                            'end_date_active' => $priceRecord->end_date_active,
                        ];
                    }
                }
            }
            
            return [
                'id' => $item->id,
                'inventory_item_id' => $item->inventory_item_id,
                'item_code' => $item->item_code,
                'item_description' => $item->item_description,
                'primary_uom_code' => $item->primary_uom_code,
                'secondary_uom_code' => $item->secondary_uom_code,
                'major_category' => $item->major_category,
                'minor_category' => $item->minor_category,
                'sub_minor_category' => $item->sub_minor_category,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'prices' => $prices,
            ];
        });


        // Return the results in JSON format
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully.',
            'data' => $mappedProducts,
        ], 200);
    }

    /**
     * Clear product caches (to be called when products are updated)
     */
    public function clearProductCache(): JsonResponse
    {
        try {
            // Clear all product-related cache
            Cache::flush(); // Alternative: use more specific cache clearing
            
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product cache cleared successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get price list IDs based on filter type and user's customer assignments
     */
    private function getPriceListFilters($filter, $user = null): array
    {
        // Get dynamic price list mappings from Oracle or database
        $priceListMappings = $this->getPriceListMappings();
        
        // Get base filter results
        $basePriceLists = [];
        
        switch ($filter) {
            case 'vendor':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['vendor'] ?? []);
                break;
                
            case 'wholesaler':
                $basePriceLists = array_merge(
                    $this->cleanPriceListArray($priceListMappings['karachi_wholesale'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['lahore_wholesale'] ?? [])
                );
                break;
                
            case 'corporate':
                $basePriceLists = array_merge(
                    $this->cleanPriceListArray($priceListMappings['karachi_corporate'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['lahore_corporate'] ?? [])
                );
                break;
                
            case 'trade':
                $basePriceLists = array_merge(
                    $this->cleanPriceListArray($priceListMappings['karachi_trade'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['lahore_trade'] ?? [])
                );
                break;
                
            case 'karachi':
                $basePriceLists = array_merge(
                    $this->cleanPriceListArray($priceListMappings['karachi_trade'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['karachi_wholesale'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['karachi_corporate'] ?? [])
                );
                break;
                
            case 'lahore':
                $basePriceLists = array_merge(
                    $this->cleanPriceListArray($priceListMappings['lahore_trade'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['lahore_wholesale'] ?? []),
                    $this->cleanPriceListArray($priceListMappings['lahore_corporate'] ?? [])
                );
                break;
                
            // Specific city-price type combinations
            case 'karachi-trade':
            case 'karachi-trade-price':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['karachi_trade'] ?? []);
                break;

            case 'karachi-wholesale':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['karachi_wholesale'] ?? []);
                break;

            case 'karachi-corporate':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['karachi_corporate'] ?? []);
                break;

            case 'lahore-trade':
            case 'lahore-trade-price':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['lahore_trade'] ?? []);
                break;

            case 'lahore-wholesale':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['lahore_wholesale'] ?? []);
                break;

            case 'lahore-corporate':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['lahore_corporate'] ?? []);
                break;
                
            case 'qg-hbm':
                $basePriceLists = $this->cleanPriceListArray($priceListMappings['vendor'] ?? []);
                break;
                
            case 'default':
            default:
                // Return empty array to show all price lists (default behavior)
                $basePriceLists = [];
                break;
        }
        
        // Clean and validate all arrays before operations
        $basePriceLists = $this->cleanPriceListArray($basePriceLists);
        
        // If user is provided and is a salesperson, intersect with their customer's price lists
        if ($user && $user->role === 'user') {
            $customerPriceLists = $this->getSalespersonPriceLists($user);
            $customerPriceLists = $this->cleanPriceListArray($customerPriceLists);
            
            if (!empty($customerPriceLists)) {
                if (empty($basePriceLists)) {
                    // No filter specified, return customer price lists
                    return $customerPriceLists;
                } else {
                    // Filter specified, return intersection with customer price lists
                    return array_values(array_intersect($basePriceLists, $customerPriceLists));
                }
            }
        }
        
        return $basePriceLists;
    }

    /**
     * Get dynamic price list mappings from Oracle data
     */
    private function getPriceListMappings(): array
    {
        return Cache::remember('price_list_mappings', 3600, function () {
            try {
                // Get unique price lists from Oracle item_prices table
                $priceLists = DB::connection('oracle')
                    ->table('apps.qg_pos_item_price')
                    ->select('price_list_id', 'price_list_name')
                    ->distinct()
                    ->get()
                    ->keyBy('price_list_id')
                    ->toArray();

                // Create dynamic mappings based on price list names
                $mappings = [
                    'vendor' => [],
                    'karachi_trade' => [],
                    'karachi_wholesale' => [],
                    'karachi_corporate' => [],
                    'lahore_trade' => [],
                    'lahore_wholesale' => [],
                    'lahore_corporate' => [],
                ];

                foreach ($priceLists as $priceListId => $priceList) {
                    // Ensure price_list_id is valid
                    if ($priceListId === null || $priceListId === '' || (!is_string($priceListId) && !is_numeric($priceListId))) {
                        continue;
                    }
                    
                    // Convert to string for consistency
                    $priceListId = (string) $priceListId;
                    $name = strtolower($priceList->price_list_name ?? '');
                    
                    // Map based on naming patterns
                    if (str_contains($name, 'vendor') || str_contains($name, 'hbm')) {
                        $mappings['vendor'][] = $priceListId;
                    } elseif (str_contains($name, 'karachi')) {
                        if (str_contains($name, 'trade')) {
                            $mappings['karachi_trade'][] = $priceListId;
                        } elseif (str_contains($name, 'wholesale')) {
                            $mappings['karachi_wholesale'][] = $priceListId;
                        } elseif (str_contains($name, 'corporate')) {
                            $mappings['karachi_corporate'][] = $priceListId;
                        }
                    } elseif (str_contains($name, 'lahore')) {
                        if (str_contains($name, 'trade')) {
                            $mappings['lahore_trade'][] = $priceListId;
                        } elseif (str_contains($name, 'wholesale')) {
                            $mappings['lahore_wholesale'][] = $priceListId;
                        } elseif (str_contains($name, 'corporate')) {
                            $mappings['lahore_corporate'][] = $priceListId;
                        }
                    }
                }

                return $mappings;
            } catch (\Exception $e) {
                // Fallback to hardcoded mappings if Oracle query fails
                return [
                    'vendor' => ['1116080'],
                    'karachi_trade' => ['7012'],
                    'karachi_wholesale' => ['7011'],
                    'karachi_corporate' => ['7010'],
                    'lahore_trade' => ['7009'],
                    'lahore_wholesale' => ['7008'],
                    'lahore_corporate' => ['7007'],
                ];
            }
        });
    }

    /**
     * Get allowed price lists based on user's Oracle organizations
     */
    private function getOracleOrganizationPriceLists($user): array
    {
        if ($user->role === 'admin') {
            return []; // Admin sees all price lists
        }

        if (!$user->isOracleMapped()) {
            return []; // Non-Oracle users get default behavior
        }

        $userOrganizations = $user->getOracleOrganizations(); // This returns OU_IDs like [102, 103]
        
        if (empty($userOrganizations)) {
            return []; // No organizations = no specific filtering
        }

        // Map Oracle OU_IDs to price lists
        // This mapping should be based on your business logic
        // For now, I'll create a basic mapping structure
        $organizationPriceListMap = [
            102 => ['7012', '7011', '7010'], // Karachi organization gets Karachi price lists
            103 => ['7009', '7008', '7007'], // Lahore organization gets Lahore price lists
            104 => ['1116080'], // Another organization gets QG HBM
            105 => ['7012', '7011', '7010'], // Another Karachi org
            106 => ['7009', '7008', '7007'], // Another Lahore org
            // Add more mappings as needed based on your Oracle data
        ];

        $allowedPriceLists = [];
        foreach ($userOrganizations as $ouId) {
            if (isset($organizationPriceListMap[$ouId])) {
                $allowedPriceLists = array_merge($allowedPriceLists, $organizationPriceListMap[$ouId]);
            }
        }

        return array_unique($allowedPriceLists);
    }

    /**
     * Get allowed price lists based on salesperson's customers' price_list_id
     * This ensures products are filtered by:
     * 1. customers.salesperson = user.name
     * 2. customers.price_list_id = item_prices.price_list_id
     * 3. items.item_code = item_prices.item_code
     */
    private function getSalespersonPriceLists($user): array
    {
        if ($user->role !== 'user') {
            return [];
        }

        // Use user name to match salesperson column
        $salespersonName = $user->name;

        // Get unique price_list_ids from customers assigned to this salesperson
        $customerPriceLists = \App\Models\Customer::where('salesperson', $salespersonName)
            ->whereNotNull('price_list_id')
            ->where('price_list_id', '!=', '')
            ->distinct()
            ->pluck('price_list_id')
            ->filter(function($priceListId) {
                // Filter out any non-string/non-numeric values and ensure they're valid
                return $priceListId !== null && $priceListId !== '' && (is_string($priceListId) || is_numeric($priceListId));
            })
            ->map(function($priceListId) {
                // Convert to string to ensure consistency
                return (string) $priceListId;
            })
            ->values()
            ->toArray();

        return $customerPriceLists;
    }

    /**
     * Clean and validate price list array to prevent non-numeric errors
     */
    private function cleanPriceListArray(array $priceLists): array
    {
        return array_values(array_filter(array_map(function($priceListId) {
            // Ensure it's a valid price list ID
            if ($priceListId === null || $priceListId === '' || (!is_string($priceListId) && !is_numeric($priceListId))) {
                return null;
            }
            return (string) $priceListId;
        }, $priceLists), function($value) {
            return $value !== null && $value !== '';
        }));
    }
}