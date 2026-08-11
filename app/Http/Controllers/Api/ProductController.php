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

    /**
     * Retrieve unique minor and sub-minor categories filtered by user location.
    */
    public function categories(): JsonResponse
    {
        $user = Auth::user();
        $filter = request()->input('filter', 'default');
        
        // Create cache key based on user and filter
        $cacheKey = "product_categories_filter_{$filter}_user_{$user->id}";
        $cacheTime = 60; // Cache for 60 minutes
        
        $categories = Cache::remember($cacheKey, $cacheTime, function () use ($user, $filter) {
            // Define price list filters with role-based filtering and customer assignments
            // (Same logic as in products() method)
            $priceListFilters = $this->getPriceListFilters($filter, $user);
            
            // Apply role-based price list filtering for non-admin users
            if ($user->role !== 'admin') {
                if ($user->role === 'supply-chain') {
                    $allowedPriceLists = $this->getOracleOrganizationPriceLists($user);
                    if (!empty($allowedPriceLists)) {
                        if (empty($priceListFilters)) {
                            $priceListFilters = $allowedPriceLists;
                        } else {
                            $priceListFilters = array_intersect($priceListFilters, $allowedPriceLists);
                        }
                    }
                } elseif ($user->role === 'user') {
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

            // Determine user's location
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

            // Query items to get categories
            $query = Item::query()
                ->excludePackingMaterial()
                ->select('minor_category', 'sub_minor_category')
                ->whereNotNull('minor_category')
                ->where('minor_category', '!=', '');

            // Filter items based on products() query logic
            if ($userLocation !== null) {
                $priceListPattern = $userLocation === 1 ? 'Karachi%' : 'Lahore%';
                $query->whereHas('itemPrices', function($q) use ($priceListPattern) {
                    $q->where('price_list_name', 'like', $priceListPattern);
                });
            }

            $items = $query->distinct()->get();

            // Structure the data: Minor Category -> [Sub Minor Categories]
            $structured = [];
            foreach ($items as $item) {
                $minor = $item->minor_category;
                $subMinor = $item->sub_minor_category;
                
                if (!isset($structured[$minor])) {
                    $structured[$minor] = [];
                }
                
                if ($subMinor && !in_array($subMinor, $structured[$minor])) {
                    $structured[$minor][] = $subMinor;
                }
            }

            // Convert to array of objects
            $result = [];
            foreach ($structured as $minor => $subMinors) {
                sort($subMinors); // Optional: sort sub-categories
                $result[] = [
                    'minor_category' => $minor,
                    'sub_minor_categories' => $subMinors
                ];
            }
            
            // Sort minor categories alphabetically
            usort($result, function($a, $b) {
                return strcmp($a['minor_category'], $b['minor_category']);
            });

            return $result;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Product categories retrieved successfully',
            'data' => $categories,
        ], 200);
    }
    
    public function products()
    {
        $user = Auth::user();

        // Get pagination parameters
        $page = request()->input('page', 1);
        $perPage = request()->input('per_page', 10);
        $perPage = min($perPage, 100); // Limit max per page to prevent memory issues

        // Get filter parameter
        $filter = request()->input('filter', 'default'); // default, vendor, wholesaler, corporate, trade, karachi, lahore, karachi-trade-price, karachi-wholesale, karachi-corporate, lahore-trade-price, lahore-wholesale, lahore-corporate, qg-hbm

        // Get category filters
        $minor = request()->input('minor');
        $subMinor = request()->input('sub_minor');

        // Endpoint-identification log. Every product-fetch endpoint stamps
        // a unique tag so the logs can pinpoint which one the mobile uses
        // for the post-customer-select search flow.
        \Log::info('ProductSearch [GET /api/products/all] hit', [
            'endpoint'    => 'GET /api/products/all',
            'controller'  => 'ProductController@products',
            'user_id'     => $user?->id,
            'user_role'   => $user?->role,
            'raw_input'   => request()->all(),
            'page'        => $page,
            'per_page'    => $perPage,
            'filter'      => $filter,
            'minor'       => $minor,
            'sub_minor'   => $subMinor,
            'customer_id' => request()->input('customer_id'),
            'server_now'  => now()->toDateTimeString(),
        ]);
        
        // Create cache key based on pagination, filter, categories, and user
        $cacheKey = "products_page_{$page}_per_page_{$perPage}_filter_{$filter}_minor_{$minor}_sub_minor_{$subMinor}_user_{$user->id}";
        $cacheTime = 60; // Cache for 60 minutes
        
        // Use cache to store the expensive query results
        $result = Cache::remember($cacheKey, $cacheTime, function () use ($page, $perPage, $filter, $minor, $subMinor, $user) {
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
            $today = now()->format('Y-m-d');
            $productsQuery = Item::with(['itemPrices' => function ($query) use ($userLocation, $today) {
                    // Load prices based on user location
                    $query->select('id', 'item_id', 'item_code', 'price_list_id', 'price_list_name', 'uom', 'list_price', 'discounted_price', 'start_date_active', 'end_date_active');

                    // Only currently-active price rows — filters out end-dated
                    // name-variant duplicates that otherwise leak into the
                    // response as extra lines with stale prices.
                    $query->where(function ($sq) use ($today) {
                        $sq->whereNull('start_date_active')
                           ->orWhere('start_date_active', '<=', $today);
                    })->where(function ($sq) use ($today) {
                        $sq->whereNull('end_date_active')
                           ->orWhere('end_date_active', '>=', $today);
                    });

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
                ->excludePackingMaterial()
                ->select('id', 'inventory_item_id', 'item_code', 'item_description',
                        'primary_uom_code', 'secondary_uom_code', 'major_category',
                        'minor_category', 'sub_minor_category', 'created_at', 'updated_at');

            // Apply price-based filtering based on requested filter
            if ($filter && $filter !== 'default') {
                $requestedFilters = array_map('trim', explode(',', strtolower($filter)));
                
                // Define internal patterns matching the view's $filterPatterns logic
                $queryPatterns = [
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

                $productsQuery->whereHas('itemPrices', function($q) use ($requestedFilters, $queryPatterns) {
                    $q->where(function($subQ) use ($requestedFilters, $queryPatterns) {
                        foreach ($requestedFilters as $rf) {
                            $patterns = $queryPatterns[$rf] ?? [];
                            if (!empty($patterns)) {
                                $subQ->orWhere(function($patternQ) use ($patterns) {
                                    foreach ($patterns as $pattern) {
                                        $patternQ->where('price_list_name', 'like', "%{$pattern}%");
                                    }
                                });
                            }
                        }
                    })->whereNotNull('list_price');
                });
            } elseif ($userLocation !== null) {
                // Apply default location-based filtering if no specific filter
                $priceListPattern = $userLocation === 1 ? 'Karachi%' : 'Lahore%';
                $productsQuery->whereHas('itemPrices', function($q) use ($priceListPattern) {
                    $q->where('price_list_name', 'like', $priceListPattern)->whereNotNull('list_price');
                });
            }

            // Apply minor category filter if provided
            if ($minor) {
                $minorCategories = array_map('trim', explode(',', $minor));
                $productsQuery->whereIn('minor_category', $minorCategories);
            }

            // Apply sub minor category filter if provided
            if ($subMinor) {
                $subMinorCategories = array_map('trim', explode(',', $subMinor));
                $productsQuery->whereIn('sub_minor_category', $subMinorCategories);
            }

            $products = $productsQuery->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
            
            // Pre-fetch all discounted prices for these items across any price list for global lookup
            $allItemCodes = $products->getCollection()->pluck('item_code')->unique()->filter()->toArray();
            $globalDiscounts = \App\Models\ItemPrice::whereIn('item_code', $allItemCodes)
                ->whereNotNull('discounted_price')
                ->select('item_code', 'discounted_price')
                ->get()
                ->groupBy('item_code');

            // Transform the data to include prices array based on filter
            $transformedData = $products->getCollection()->map(function ($item) use ($priceListFilters, $filter, $user, $userLocation, $globalDiscounts) {
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

                $requestedFilters = explode(',', $normalizedFilter);

                // Apply location-based filtering for default filter if no other patterns exist
                if ($normalizedFilter === 'default' && $userLocation !== null) {
                    $requestedFilters = [$userLocation === 1 ? 'karachi' : 'lahore'];
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

                    // Check if price record matches ANY of the requested filter groups
                    $matchesAnyFilter = false;
                    foreach ($requestedFilters as $rf) {
                        $rf = trim($rf);
                        $patterns = $filterPatterns[$rf] ?? [];
                        
                        // Handle default location if rf is exactly 'default'
                        if ($rf === 'default' && empty($patterns) && $userLocation !== null) {
                            $patterns = $userLocation === 1 ? ['karachi'] : ['lahore'];
                        }
                        
                        if (empty($patterns)) {
                            // If this specific filter has no patterns (e.g. unknown filter name), 
                            // we don't consider it a match unless it' the 'default' filter without location
                            if ($rf === 'default') {
                                $matchesAnyFilter = true;
                                break;
                            }
                            continue;
                        }

                        $matchesThisFilter = true;
                        foreach ($patterns as $pattern) {
                            if (strpos($normalizedName, $pattern) === false) {
                                $matchesThisFilter = false;
                                break;
                            }
                        }
                        
                        if ($matchesThisFilter) {
                            $matchesAnyFilter = true;
                            break;
                        }
                    }

                    if (!$matchesAnyFilter && !empty($requestedFilters) && $requestedFilters[0] !== '') {
                        continue;
                    }

                    // Get display type
                    $matchedType = $priceListNameMap[$normalizedName] ?? 'unknown';

                    // Use record's discount if present, otherwise look for ANY available discount for this item
                    $discount = $priceRecord->discounted_price;
                    if ($discount === null && isset($globalDiscounts[$item->item_code])) {
                        $discount = $globalDiscounts[$item->item_code]->first()->discounted_price;
                    }

                    $prices[] = [
                        'type' => $matchedType,
                        'price_list_id' => $priceRecord->price_list_id,
                        'price_list_name' => $priceRecord->price_list_name,
                        'uom' => $priceRecord->uom,
                        'list_price' => $priceRecord->list_price,
                        'discounted_price' => $discount,
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
        
        // Personalised ordering — recently/frequently used by the auth user come first.
        $rankedCodes = \App\Services\UserActivityRanker::recentItemCodes(Auth::id());
        $sortedData  = $result['data']->map(function ($p) use ($rankedCodes) {
            $idx = array_search((string) ($p['inventory_item_id'] ?? ''), array_map('strval', $rankedCodes), true);
            $p['is_recent']   = $idx !== false;
            $p['recent_rank'] = $idx !== false ? ($idx + 1) : null;
            return $p;
        });
        $sortedData = \App\Services\UserActivityRanker::sortByRanked(
            $sortedData,
            $rankedCodes,
            fn ($p) => $p['inventory_item_id'] ?? ''
        );

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully',
            'data' => $sortedData,
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

        // Endpoint-identification log.
        \Log::info('ProductSearch [GET /api/products/get] hit', [
            'endpoint'          => 'GET /api/products/get',
            'controller'        => 'ProductController@getProduct',
            'user_id'           => Auth::id(),
            'user_role'         => Auth::user()?->role,
            'raw_input'         => $request->all(),
            'inventory_item_id' => $validated['inventory_item_id'],
            'customer_id'       => $request->input('customer_id'),
            'server_now'        => now()->toDateTimeString(),
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
        // Validate the request to ensure 'searchTerm' is provided. customer_id
        // is OPTIONAL — when sent, the response is scoped to ONLY that
        // customer's price_list_id (and the cache key includes it). Without
        // it the legacy behaviour applies: results are scoped to the
        // salesperson's role/customers and the mobile is responsible for
        // picking the right price-list row from the itemPrices array on the
        // client side. The optional path is what mobile should adopt to
        // eliminate the cached-wrong-list bug.
        // customer_id is optional. When sent, the response price-list scope
        // is pinned to that one customer's price_list_id so the mobile can
        // never render a row from a different customer's list. When NOT
        // sent (or sent with an empty value) the endpoint returns an empty
        // data array — there is no fallback to a multi-list response,
        // because that was the root cause of the 640-vs-615.16 mismatch on
        // order 20262139. Mobile should always include customer_id from
        // the cart screen for results to come back populated.
        $validated = $request->validate([
            'searchTerm'  => 'required|string',
            'customer_id' => 'nullable|exists:customers,customer_id',
            'filter'      => 'nullable|string|in:default,vendor,wholesaler,corporate,trade,karachi,lahore,karachi-trade-price,karachi-wholesale,karachi-corporate,lahore-trade-price,lahore-wholesale,lahore-corporate,qg-hbm',
            'minor'       => 'nullable|string',
            'sub_minor'   => 'nullable|string',
        ]);

        // Endpoint-identification log. Every product-search endpoint stamps a
        // unique tag here so we can tell from the logs which one the mobile
        // hit for any given search-after-customer-select flow.
        \Log::info('ProductSearch [GET /api/products/search] hit', [
            'endpoint'    => 'GET /api/products/search',
            'controller'  => 'ProductController@searchProduct',
            'user_id'     => Auth::id(),
            'user_role'   => Auth::user()?->role,
            'raw_input'   => $request->all(),
            'validated'   => $validated,
            'searchTerm'  => $validated['searchTerm']  ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'server_now'  => now()->toDateTimeString(),
        ]);

        // Extract the search term and other parameters
        $searchTerm = $validated['searchTerm'];

        // Normalize: replace punctuation (quotes, parens, brackets, slashes,
        // common separators) with spaces so e.g. `3" (75mm) Valcro` becomes
        // `3 75mm Valcro`. Hyphens and apostrophes stay so item codes like
        // `0078-0048` still tokenize as a single term and names like
        // `O'Brien` aren't shredded.
        $normalizedTerm = preg_replace('/["()\[\]{},.;:!?\/\\\\]+/u', ' ', $searchTerm);
        $normalizedTerm = trim(preg_replace('/\s+/u', ' ', $normalizedTerm));

        // Tokens for the per-word AND-match — drop empty pieces so multiple
        // consecutive punctuation chars don't create blank LIKE-%% terms
        // (which would match every row).
        $terms = array_values(array_filter(
            explode(' ', $normalizedTerm),
            fn ($t) => $t !== ''
        ));

        // If sanitization stripped everything, fall back to the raw term so
        // the user still gets a result instead of an unintended match-all.
        if (empty($terms)) {
            $terms = [$searchTerm];
            $normalizedTerm = $searchTerm;
        }
        $filter   = $validated['filter'] ?? 'default';
        $minor    = $validated['minor'] ?? null;
        $subMinor = $validated['sub_minor'] ?? null;
        $user     = Auth::user();

        // Resolve the customer's allocated price list. When customer_id is
        // sent we pin the price-list scope to that single id (strict). When
        // it's missing we fall back to the legacy role-based price-list
        // expansion (whatever lists the salesperson is allowed to quote
        // through getPriceListFilters + getSalespersonPriceLists). The
        // fallback is only there so generic searches still return rows; the
        // mobile cart should always pass customer_id to guarantee single-
        // list prices and avoid the cross-list mismatch.
        $customerPriceListId   = null;
        $customerPriceListName = null;
        if (!empty($validated['customer_id'])) {
            $cust = \App\Models\Customer::where('customer_id', $validated['customer_id'])
                ->first(['customer_id', 'price_list_id', 'price_list_name']);
            if ($cust) {
                $customerPriceListId   = $cust->price_list_id   ? (string) $cust->price_list_id   : null;
                $customerPriceListName = $cust->price_list_name ? (string) $cust->price_list_name : null;
            }
        }

        // Compute the effective price-list scope ONCE here so every downstream
        // consumer (the main query, fetchRelatedItems, and the $mapItem
        // closure that builds the response prices[] array) sees the same
        // scope. Previously this was re-derived later from
        // getPriceListFilters($filter, $user) which silently widened the
        // scope back to the salesperson's role-based lists — and related
        // items would end up carrying rows from price lists other than the
        // customer's, producing the "wrong price" mismatches reported here.
        if ($customerPriceListId !== null) {
            // Strict customer scope: ONLY this customer's list. No other rows
            // can attach to either matched or related items, so the mobile
            // cart can't mistakenly render a different tier's price.
            $priceListFilters = [$customerPriceListId];
        } else {
            // Legacy role-based scope when customer_id is not sent.
            $priceListFilters = $this->getPriceListFilters($filter, $user);
            if ($user->role !== 'admin') {
                if ($user->role === 'supply-chain') {
                    $allowed = $this->getOracleOrganizationPriceLists($user);
                    if (!empty($allowed)) {
                        $priceListFilters = empty($priceListFilters)
                            ? $allowed
                            : array_intersect($priceListFilters, $allowed);
                    }
                } elseif ($user->role === 'user') {
                    $allowed = $this->getSalespersonPriceLists($user);
                    if (!empty($allowed)) {
                        $priceListFilters = empty($priceListFilters)
                            ? $allowed
                            : array_intersect($priceListFilters, $allowed);
                    }
                }
            }
        }

        // Determine user's location
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

        // Caching is disabled for this endpoint. The customer_id deliberately
        // does NOT participate in any cache key — the prior leak pattern was
        // that a customer-specific response would be cached and then served
        // back to a different customer on the same user account, producing
        // the cross-list price mismatches we just fixed. Computing fresh on
        // every request guarantees the response carries only the price-list
        // scope we just derived from the current customer_id in this call.
        $products = (function () use ($terms, $normalizedTerm, $minor, $subMinor, $priceListFilters) {
            // Build SQL fragments dynamically so the ranking can react to
            // how many terms each row matches.
            $allTermsAnd  = collect($terms)
                ->map(fn () => '(item_description LIKE ? OR item_code LIKE ?)')
                ->implode(' AND ');
            $matchCountSum = collect($terms)
                ->map(fn () => '(CASE WHEN item_description LIKE ? OR item_code LIKE ? THEN 1 ELSE 0 END)')
                ->implode(' + ');

            $orderBindings = [];
            // 1) Exact phrase (highest tier)
            $orderBindings[] = "%$normalizedTerm%";
            $orderBindings[] = "%$normalizedTerm%";
            // 2) All terms present (any order, any column)
            foreach ($terms as $t) {
                $orderBindings[] = "%$t%";
                $orderBindings[] = "%$t%";
            }
            // 3) Terms in original order
            $orderBindings[] = "%" . implode('%', $terms) . "%";
            $orderBindings[] = "%" . implode('%', $terms) . "%";
            // Tie-breaker: number of distinct terms matched (DESC)
            foreach ($terms as $t) {
                $orderBindings[] = "%$t%";
                $orderBindings[] = "%$t%";
            }

            // Pre-compute the price-list scope so it's shared between the
            // eager-load (which filters the relation) AND the whereHas
            // (which excludes items that have no priced row at all).
            $allPriceListIds = ['7012', '7011', '7010', '7009', '7008', '7007', '1116080'];
            $targetPriceListIds = !empty($priceListFilters) ? $priceListFilters : $allPriceListIds;

            $today = now()->format('Y-m-d');
            return Item::with(['itemPrices' => function ($query) use ($targetPriceListIds, $today) {
                    $query->select('id', 'item_id', 'item_code', 'price_list_id', 'price_list_name', 'uom', 'list_price', 'discounted_price', 'start_date_active', 'end_date_active')
                          ->whereIn('price_list_id', $targetPriceListIds)
                          ->whereNotNull('list_price')
                          ->where(function ($sq) use ($today) {
                              $sq->whereNull('start_date_active')
                                 ->orWhere('start_date_active', '<=', $today);
                          })
                          ->where(function ($sq) use ($today) {
                              $sq->whereNull('end_date_active')
                                 ->orWhere('end_date_active', '>=', $today);
                          });
                }])
                // SQL-level filter: only return items that have at least one
                // priced, currently-active row in scope. Without this the
                // eager-load would attach an empty collection and the item
                // would still come back with prices=[] in the response —
                // surfacing un-orderable items the cart can't actually price.
                ->whereHas('itemPrices', function ($q) use ($targetPriceListIds, $today) {
                    $q->whereIn('price_list_id', $targetPriceListIds)
                      ->whereNotNull('list_price')
                      ->where(function ($sq) use ($today) {
                          $sq->whereNull('start_date_active')
                             ->orWhere('start_date_active', '<=', $today);
                      })
                      ->where(function ($sq) use ($today) {
                          $sq->whereNull('end_date_active')
                             ->orWhere('end_date_active', '>=', $today);
                      });
                })
                ->excludePackingMaterial()
                ->select('id', 'inventory_item_id', 'item_code', 'item_description',
                        'primary_uom_code', 'secondary_uom_code', 'major_category',
                        'minor_category', 'sub_minor_category', 'created_at', 'updated_at')
                ->where(function ($query) use ($terms) {
                    // Tiered match: items where ANY term hits item_code or
                    // item_description qualify. Ranking below promotes rows
                    // that match every term to the top so the user still sees
                    // their best matches first, then everything else by how
                    // many of the typed terms it contains.
                    foreach ($terms as $term) {
                        $query->orWhere('item_description', 'like', '%' . $term . '%');
                        $query->orWhere('item_code',        'like', '%' . $term . '%');
                    }
                })
                ->when($minor, function ($query) use ($minor) {
                    $minorCategories = array_map('trim', explode(',', $minor));
                    return $query->whereIn('minor_category', $minorCategories);
                })
                ->when($subMinor, function ($query) use ($subMinor) {
                    $subMinorCategories = array_map('trim', explode(',', $subMinor));
                    return $query->whereIn('sub_minor_category', $subMinorCategories);
                })
                ->orderByRaw("
                        CASE
                            WHEN item_description LIKE ? OR item_code LIKE ? THEN 1   -- Exact normalized phrase
                            WHEN $allTermsAnd                                THEN 2   -- All terms present
                            WHEN item_description LIKE ? OR item_code LIKE ? THEN 3   -- Terms in original order
                            ELSE 4                                                    -- Some terms present
                        END,
                        ($matchCountSum) DESC                                          -- Tie-break: more matches first
                    ", $orderBindings)
                ->limit(50) // Limit search results for better performance
                ->get();
        })();

        // Pull related items (same item-code prefix or same minor+sub-minor
        // category) as FULL first-class entries so they appear alongside the
        // matched items in the response — same shape, same `prices` array per
        // item, no nested `related_products` field anymore. Matched items keep
        // their relevance order at the top; related items follow.
        $matchedCodes = $products->pluck('item_code')->filter()->unique()->values()->all();
        // Use the SAME $priceListFilters already pinned above (strict to the
        // customer's list when customer_id was sent). Re-deriving it here from
        // getPriceListFilters($filter, $user) would silently re-widen the
        // scope and let related items carry rows from other lists — that's
        // the bug behind the intermittent wrong-price reports.
        $relatedItems = $this->fetchRelatedItems($products, $matchedCodes, $priceListFilters);

        // Pre-fetch all discounted prices across BOTH matched and related items
        // so the mapping closure can look up fallback discounts for either.
        $allItemCodes = array_values(array_unique(array_merge(
            $matchedCodes,
            $relatedItems->pluck('item_code')->filter()->unique()->values()->all()
        )));
        $globalDiscounts = \App\Models\ItemPrice::whereIn('item_code', $allItemCodes)
            ->whereNotNull('discounted_price')
            ->select('item_code', 'discounted_price')
            ->get()
            ->groupBy('item_code');

        // Map the results to include prices array based on filter
        $mapItem = function ($item) use ($priceListFilters, $globalDiscounts, $filter, $userLocation) {
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
            
            // Define which price_list_names to include based on filter
            $filterPatterns = [
                'default' => [],
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

            $normalizedFilter = strtolower($filter ?? 'default');
            $allPatterns = [];
            $requestedFilters = explode(',', $normalizedFilter);
            
            foreach ($requestedFilters as $rf) {
                $rf = trim($rf);
                if (isset($filterPatterns[$rf])) {
                    $allPatterns = array_merge($allPatterns, $filterPatterns[$rf]);
                }
            }

            $currentFilterPatterns = array_unique($allPatterns);
            
            // Map price list names for display types
            $priceListNameMap = [
                'karachi - corporate' => 'karachi_corporate',
                'karachi - trade price' => 'karachi_trade_price',
                'karachi-wholesale' => 'karachi_wholesale',
                'lahore - corporate' => 'lahore_corporate',
                'lahore - trade price' => 'lahore_trade_price',
                'lahore - wholesale' => 'lahore_wholesale',
                'karachi corporate' => 'karachi_corporate',
                'karachi-corporate' => 'karachi_corporate',
                'karachi trade price' => 'karachi_trade_price',
                'karachi wholesale' => 'karachi_wholesale',
                'lahore corporate' => 'lahore_corporate',
                'lahore-corporate' => 'lahore_corporate',
                'lahore trade price' => 'lahore_trade_price',
                'lahore wholesale' => 'lahore_wholesale',
                'lahore-wholesale' => 'lahore_wholesale',
                'qg hbm' => 'qg_hbm',
                'vendor' => 'qg_hbm',
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
                        
                        $normalizedName = strtolower(trim($priceRecord->price_list_name ?? ''));

                        // Check if price record matches ANY of the requested filter groups
                        $matchesAnyFilter = false;
                        foreach ($requestedFilters as $rf) {
                            $rf = trim($rf);
                            $patterns = $filterPatterns[$rf] ?? [];
                            
                            // Handle default location if rf is 'default' and we have a location
                            if ($rf === 'default' && empty($patterns) && $userLocation !== null) {
                                $patterns = $userLocation === 1 ? ['karachi'] : ['lahore'];
                            }
                            
                            if (empty($patterns)) {
                                if ($rf === 'default') {
                                    $matchesAnyFilter = true;
                                    break;
                                }
                                continue;
                            }

                            $matchesThisFilter = true;
                            foreach ($patterns as $pattern) {
                                if (strpos($normalizedName, $pattern) === false) {
                                    $matchesThisFilter = false;
                                    break;
                                }
                            }
                            
                            if ($matchesThisFilter) {
                                $matchesAnyFilter = true;
                                break;
                            }
                        }

                        if (!$matchesAnyFilter && !empty($requestedFilters) && $requestedFilters[0] !== '') {
                            continue;
                        }

                        // Use record's discount if present, otherwise look for ANY available discount for this item
                        $discount = $priceRecord->discounted_price;
                        if ($discount === null && isset($globalDiscounts[$item->item_code])) {
                            $discount = $globalDiscounts[$item->item_code]->first()->discounted_price;
                        }

                        $prices[] = [
                            'type' => $type,
                            'price_list_id' => $priceRecord->price_list_id,
                            'price_list_name' => $priceRecord->price_list_name,
                            'uom' => $priceRecord->uom,
                            'list_price' => $priceRecord->list_price,
                            'discounted_price' => $discount,
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
        };

        // Drop any item whose final prices[] is empty. The whereHas above
        // catches items with NO priced row in scope at all; this catches the
        // narrower case where every priced row was filtered out by the
        // in-closure filter/discount logic, leaving prices=[].
        $hasPrices = fn ($p) => !empty($p['prices']);
        $mappedMatched = $products->map($mapItem)->filter($hasPrices)->values();
        $mappedRelated = $relatedItems->map($mapItem)->filter($hasPrices)->values();

        // ── Personalised ordering ────────────────────────────────────────
        // The "recently used" sort only applies to matched items so a related
        // item never overtakes a search match. Both sets get the is_recent
        // flag so the mobile UI can highlight history items either way.
        $rankedCodes = \App\Services\UserActivityRanker::recentItemCodes($user->id);
        $annotateRecent = function ($p) use ($rankedCodes) {
            $idx = array_search((string) ($p['inventory_item_id'] ?? ''), array_map('strval', $rankedCodes), true);
            $p['is_recent']   = $idx !== false;
            $p['recent_rank'] = $idx !== false ? ($idx + 1) : null;
            return $p;
        };
        $mappedMatched = $mappedMatched->map($annotateRecent);
        $mappedRelated = $mappedRelated->map($annotateRecent);

        $mappedMatched = \App\Services\UserActivityRanker::sortByRanked(
            $mappedMatched,
            $rankedCodes,
            fn ($p) => $p['inventory_item_id'] ?? ''
        );

        // Matched items (relevance-ranked + recently-used promoted) come first,
        // then related items in their natural order.
        $mappedProducts = $mappedMatched->concat($mappedRelated)->values();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully.',
            'data' => $mappedProducts,
        ], 200);
    }

    /**
     * Fetch sibling products as full Item models (with eager-loaded itemPrices)
     * so they can be returned alongside the matched items in the search response,
     * sharing the same per-item shape (id, item_code, prices[], etc.).
     *
     * Match rule (mirrors the legacy attachRelatedProducts):
     *   1. Same item_code prefix (first chunk before the dash, e.g. "0078" for
     *      0078-0048) — strongest signal that two products are part of the
     *      same range.
     *   2. Same minor_category + sub_minor_category — secondary fallback.
     *
     * Excludes any item already in the matched set. Caps the result at 50 so
     * the response stays manageable when a popular prefix has hundreds of
     * variants.
     */
    private function fetchRelatedItems($matchedProducts, array $matchedCodes, array $priceListFilters)
    {
        if ($matchedProducts->isEmpty()) {
            return collect();
        }

        $prefixes  = [];
        $categories = [];
        foreach ($matchedProducts as $p) {
            if (preg_match('/^([A-Za-z0-9]+)/', (string) $p->item_code, $m)) {
                $prefixes[] = $m[1];
            }
            if ($p->minor_category && $p->sub_minor_category) {
                $categories[] = ['minor' => $p->minor_category, 'sub' => $p->sub_minor_category];
            }
        }
        $prefixes = array_values(array_unique($prefixes));

        if (empty($prefixes) && empty($categories)) {
            return collect();
        }

        // Eager-load itemPrices restricted to the same price-list IDs the
        // matched-items query used, so related items show the same set of
        // prices/UOMs as the matches.
        $allPriceListIds = ['7012', '7011', '7010', '7009', '7008', '7007', '1116080'];
        $targetPriceListIds = !empty($priceListFilters) ? $priceListFilters : $allPriceListIds;

        $today = now()->format('Y-m-d');
        return \App\Models\Item::query()
            ->with(['itemPrices' => function ($query) use ($targetPriceListIds, $today) {
                $query->select('id', 'item_id', 'item_code', 'price_list_id', 'price_list_name', 'uom', 'list_price', 'discounted_price', 'start_date_active', 'end_date_active')
                      ->whereIn('price_list_id', $targetPriceListIds)
                      ->where(function ($sq) use ($today) {
                          $sq->whereNull('start_date_active')
                             ->orWhere('start_date_active', '<=', $today);
                      })
                      ->where(function ($sq) use ($today) {
                          $sq->whereNull('end_date_active')
                             ->orWhere('end_date_active', '>=', $today);
                      });
            }])
            ->excludePackingMaterial()
            ->select('id', 'inventory_item_id', 'item_code', 'item_description',
                     'primary_uom_code', 'secondary_uom_code', 'major_category',
                     'minor_category', 'sub_minor_category', 'created_at', 'updated_at')
            ->whereNotIn('item_code', $matchedCodes)
            ->where(function ($q) use ($prefixes, $categories) {
                foreach ($prefixes as $pref) {
                    $q->orWhere('item_code', 'like', $pref . '%');
                }
                foreach ($categories as $cat) {
                    $q->orWhere(function ($qq) use ($cat) {
                        $qq->where('minor_category', $cat['minor'])
                           ->where('sub_minor_category', $cat['sub']);
                    });
                }
            })
            ->orderBy('item_code')
            ->limit(50)
            ->get();
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
        // Support for comma-separated filters
        $filters = explode(',', $filter);
        $combinedPriceLists = [];
        
        foreach ($filters as $f) {
            $f = trim($f);
            if (empty($f)) continue;
            
            $lists = $this->getSinglePriceListFilter($f);
            $combinedPriceLists = array_merge($combinedPriceLists, $lists);
        }
        
        // Remove duplicates after merging
        $basePriceLists = array_unique($combinedPriceLists);
        
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
     * Internal helper for single filter mapping
     */
    private function getSinglePriceListFilter($filter): array
    {
        // Get dynamic price list mappings from Oracle or database
        $priceListMappings = $this->getPriceListMappings();
        $basePriceLists = [];
        
        switch ($filter) {
            case 'vendor':
            case 'qg-hbm':
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
                
            case 'default':
            default:
                $basePriceLists = [];
                break;
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