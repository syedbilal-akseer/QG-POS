<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CustomerController extends Controller
{
    /*
     * Retrieve all customers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customers(): JsonResponse
    {
        $user = Auth::user();
        $cacheKey = 'customers_page_'.request()->input('page', 1).'_user_'.$user->id;
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $customers = Cache::remember($cacheKey, $cacheTime, function () use ($user) {
            $query = Customer::select('customer_id', 'customer_name', 'customer_number');
            
            // Admin users see all customers
            if ($user->role === 'admin') {
                return $query->paginate(10);
            }
            
            // Role-based filtering for non-admin users
            if ($user->role === 'supply-chain') {
                // Supply-chain users see customers from their Oracle organizations
                if ($user->isOracleMapped()) {
                    $userOrgs = $user->getOracleOrganizations();
                    if (!empty($userOrgs)) {
                        $query->whereIn('oracle_ou_id', $userOrgs);
                    } else {
                        $query->where('customer_id', null);
                    }
                }
            } elseif ($user->role === 'user') {
                // Salesperson users see customers whose normalized salesperson key
                // matches their normalized Oracle user name (see normalizeSalespersonKey()).
                $query->where('salesperson_key', normalizeSalespersonKey($user->name));
            } else {
                // Other roles get no access by default
                $query->where('customer_id', null);
            }

            return $query->paginate(10);
        });

        // Personalised ordering — customers this user has dealt with recently come first.
        $rankedNums = \App\Services\UserActivityRanker::recentCustomerNumbers($user->id);
        $items      = collect($customers->items())->map(function ($c) use ($rankedNums) {
            $key = (string) ($c->customer_number ?? $c->customer_id ?? '');
            $idx = array_search($key, array_map('strval', $rankedNums), true);
            $c->is_recent   = $idx !== false;
            $c->recent_rank = $idx !== false ? ($idx + 1) : null;
            return $c;
        });
        $items = \App\Services\UserActivityRanker::sortByRanked(
            $items,
            $rankedNums,
            fn ($c) => $c->customer_number ?? $c->customer_id ?? ''
        );

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customers retrieved successfully',
            'data' => $items,
            'pagination' => [
                'total' => $customers->total(),
                'count' => $customers->count(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'total_pages' => $customers->lastPage(),
                'next_page_url' => $customers->nextPageUrl(),
                'prev_page_url' => $customers->previousPageUrl(),
            ],
        ], 200);
    }

        /**
     * Retrieve a specific customer's details.
     */
       public function getCustomer(Request $request): JsonResponse
    {
        // Validate the request to ensure 'customer_id' is provided
        $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        // Extract the customer ID from the request
        $customerId = $request->customer_id;
        $user = Auth::user();

        // Build query with role-based access control.
        // Note: itemPrices is intentionally NOT eager-loaded here. A single
        // price_list can have ~15k rows; hydrating that many Eloquent models
        // is what was causing 20s response times. We use a direct join below.
        $query = Customer::where('customer_id', $customerId);

        // Admin users can access any customer
        if ($user->role !== 'admin') {
            // Role-based filtering for non-admin users
            if ($user->role === 'supply-chain') {
                // Supply-chain users see customers from their Oracle organizations
                if ($user->isOracleMapped()) {
                    $userOrgs = $user->getOracleOrganizations();
                    if (!empty($userOrgs)) {
                        $query->whereIn('oracle_ou_id', $userOrgs);
                    } else {
                        $query->where('customer_id', null);
                    }
                }
            } elseif ($user->role === 'user') {
                // Salesperson users see customers whose normalized salesperson key
                // matches their normalized Oracle user name (see normalizeSalespersonKey()).
                $query->where('salesperson_key', normalizeSalespersonKey($user->name));
            } else {
                // Other roles get no access by default
                $query->where('customer_id', null);
            }
        }

        // Retrieve the customer
        $customer = $query->first();

        // Check if customer was found
        if (!$customer) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer not found or access denied.',
            ], 404);
        }

        $customerArray = $customer->toArray();

        // Pull the customer's priced products + matching item info in ONE
        // joined query, instead of hydrating ItemPrice + Item Eloquent models
        // for every row in the price list.
        $products = [];
        if (!empty($customer->price_list_id)) {
            $priceRows = \Illuminate\Support\Facades\DB::table('item_prices')
                ->leftJoin('items', 'items.item_code', '=', 'item_prices.item_code')
                ->where('item_prices.price_list_id', $customer->price_list_id)
                ->select(
                    'item_prices.item_code as ip_item_code',
                    'item_prices.item_description as ip_item_description',
                    'item_prices.uom as ip_uom',
                    'item_prices.list_price as ip_list_price',
                    'item_prices.discounted_price as ip_discounted_price',
                    'items.item_code as i_item_code',
                    'items.item_description as i_item_description',
                    'items.inventory_item_id as i_inventory_item_id'
                )
                ->get();

            if ($priceRows->isNotEmpty()) {
                // Only look up fallback discounts for codes whose own price row
                // has no discount. This shrinks the whereIn from ~15k codes to
                // typically just the few hundred that actually need fallback.
                $codesNeedingDiscount = $priceRows
                    ->filter(fn ($r) => $r->ip_discounted_price === null && !empty($r->ip_item_code))
                    ->pluck('ip_item_code')
                    ->unique()
                    ->values()
                    ->all();

                $globalDiscounts = collect();
                if (!empty($codesNeedingDiscount)) {
                    // orderBy(id) preserves the original "first matching row wins"
                    // behavior from ->get()->groupBy()->first().
                    $globalDiscounts = \App\Models\ItemPrice::whereIn('item_code', $codesNeedingDiscount)
                        ->whereNotNull('discounted_price')
                        ->orderBy('id')
                        ->select('item_code', 'discounted_price')
                        ->get()
                        ->groupBy('item_code');
                }

                $products = $priceRows->map(function ($r) use ($globalDiscounts) {
                    $discount = $r->ip_discounted_price;
                    if ($discount === null && isset($globalDiscounts[$r->ip_item_code])) {
                        $discount = $globalDiscounts[$r->ip_item_code]->first()->discounted_price;
                    }

                    // list_price is stored as VARCHAR; Eloquent's decimal:2 cast
                    // formats it as e.g. "625.00". We reproduce that format here
                    // so the response is byte-identical to the previous version.
                    $listPrice = $r->ip_list_price;
                    if ($listPrice !== null && $listPrice !== '') {
                        $listPrice = number_format((float) $listPrice, 2, '.', '');
                    }

                    return [
                        'inventory_item_id' => $r->i_inventory_item_id,
                        'item_code' => $r->ip_item_code ?: $r->i_item_code,
                        'item_description' => $r->ip_item_description ?: $r->i_item_description,
                        'item_uom_code' => $r->ip_uom,
                        'item_price' => $listPrice,
                        'discounted_price' => $discount,
                    ];
                })->toArray();
            }
        }

        // Add products to customer data
        $customerArray['products'] = $products;

        // Return the response
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer retrieved successfully',
            'data' => $customerArray,
        ], 200);
    }

    /**
     * Retrieve the customer's products with their prices.
     */
    public function getCustomerProducts(Request $request): JsonResponse
    {
        // Validate the request to ensure 'customer_id' is provided and exists
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        $user = Auth::user();

        // Endpoint-identification log.
        \Log::info('ProductSearch [POST /api/customers/get-products] hit', [
            'endpoint'    => 'POST /api/customers/get-products',
            'controller'  => 'CustomerController@getCustomerProducts',
            'user_id'     => $user?->id,
            'user_role'   => $user?->role,
            'raw_input'   => $request->all(),
            'customer_id' => $validated['customer_id'],
            'server_now'  => now()->toDateTimeString(),
        ]);

        $cacheKey = 'customer_products_'.$validated['customer_id'].'_user_'.$user->id;
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            $user = Auth::user();
            
            // Constrain the itemPrices eager load to currently-active rows so
            // end-dated name-variant duplicates don't leak into the response.
            $today = now()->format('Y-m-d');
            $query = Customer::with(['itemPrices' => function ($q) use ($today) {
                $q->whereNotNull('list_price')
                  ->where(function ($sq) use ($today) {
                      $sq->whereNull('start_date_active')
                         ->orWhere('start_date_active', '<=', $today);
                  })
                  ->where(function ($sq) use ($today) {
                      $sq->whereNull('end_date_active')
                         ->orWhere('end_date_active', '>=', $today);
                  });
            }, 'itemPrices.item'])->where('customer_id', $validated['customer_id']);
            
            // Role-based filtering for non-admin users
            if ($user->role === 'supply-chain') {
                // Supply-chain users see customers from their Oracle organizations
                if ($user->isOracleMapped()) {
                    $userOrgs = $user->getOracleOrganizations();
                    if (!empty($userOrgs)) {
                        $query->whereIn('oracle_ou_id', $userOrgs);
                    } else {
                        $query->where('customer_id', null);
                    }
                }
            } elseif ($user->role === 'user') {
                // Salesperson users see customers whose normalized salesperson key
                // matches their normalized Oracle user name (see normalizeSalespersonKey()).
                $query->where('salesperson_key', normalizeSalespersonKey($user->name));
            } else {
                // Other roles get no access by default (except admin)
                $query->where('customer_id', null);
            }
            
            // Retrieve the customer with their item prices
            $customer = $query->first();

            // If the customer was not found, return an empty array
            if (! $customer) {
                return [];
            }

            // Check if the customer has a price list
            if (! $customer->itemPrices->isNotEmpty()) {
                return [];
            }

            // Pre-fetch all discounted prices for these items across any price list
            $itemCodes = $customer->itemPrices->pluck('item_code')->unique()->filter()->toArray();
            $globalDiscounts = \App\Models\ItemPrice::whereIn('item_code', $itemCodes)
                ->whereNotNull('discounted_price')
                ->select('item_code', 'discounted_price')
                ->get()
                ->groupBy('item_code');

            // Prepare the list of items with their prices
            return $customer->itemPrices->map(function ($itemPrice) use ($globalDiscounts) {
                // Use list's discount if present, otherwise look for ANY available discount
                $discount = $itemPrice->discounted_price;
                if ($discount === null && isset($globalDiscounts[$itemPrice->item_code])) {
                    $discount = $globalDiscounts[$itemPrice->item_code]->first()->discounted_price;
                }

                return [
                    'inventory_item_id' => $itemPrice->item?->inventory_item_id,
                    'item_code' => $itemPrice->item_code ?: $itemPrice->item?->item_code,
                    'item_description' => $itemPrice->item_description ?: $itemPrice->item?->item_description,
                    'item_uom_code' => $itemPrice->uom,
                    'item_price' => $itemPrice->list_price,
                    'discounted_price' => $discount,
                ];
            });
        });

        // If no items are found, return a 404 response
        if (empty($items)) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer not found or no items found for this customer.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Items retrieved successfully.',
            'data' => $items,
        ], 200);
    }

    /**
     * Search for customers by customer_id, contact_number, and customer_name using LIKE.
     */
    public function searchCustomer(Request $request): JsonResponse
    {
        // Validate the request to ensure 'searchTerm' is provided
        $validated = $request->validate([
            'searchTerm' => 'required|string',
        ]);

        // Extract the search term
        $searchTerm = $validated['searchTerm'];
        $user = Auth::user();

        // Generate a cache key based on the search term and user
        $cacheKey = 'search_customers_'.md5($searchTerm.'_'.$user->id);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $customers = Cache::remember($cacheKey, $cacheTime, function () use ($searchTerm, $user) {
            // Query customers using the search term
            $query = Customer::query();
            
            // Apply role-based filtering
            if ($user->role !== 'admin') {
                // Role-based filtering for non-admin users
                if ($user->role === 'supply-chain') {
                    // Supply-chain users see customers from their Oracle organizations
                    if ($user->isOracleMapped()) {
                        $userOrgs = $user->getOracleOrganizations();
                        if (!empty($userOrgs)) {
                            $query->whereIn('oracle_ou_id', $userOrgs);
                        } else {
                            $query->where('customer_id', null);
                        }
                    }
                } elseif ($user->role === 'user') {
                    // Salesperson users see customers whose normalized salesperson key
                    // matches their normalized Oracle user name (see normalizeSalespersonKey()).
                    $query->where('salesperson_key', normalizeSalespersonKey($user->name));
                } else {
                    // Other roles get no access by default
                    $query->where('customer_id', null);
                }
            }

            // Apply search filters
            $query->where(function ($q) use ($searchTerm) {
                $q->where('customer_id', 'like', '%'.$searchTerm.'%')
                    ->orWhere('customer_number', 'like', '%'.$searchTerm.'%')
                    ->orWhere('customer_name', 'like', '%'.$searchTerm.'%');
            });
            
            return $query->get();
        });

        // Personalised ordering — frequently/recently used customers come first.
        $rankedNums = \App\Services\UserActivityRanker::recentCustomerNumbers($user->id);
        $customers  = collect($customers)->map(function ($c) use ($rankedNums) {
            $key = (string) ($c->customer_number ?? $c->customer_id ?? '');
            $idx = array_search($key, array_map('strval', $rankedNums), true);
            $c->is_recent   = $idx !== false;
            $c->recent_rank = $idx !== false ? ($idx + 1) : null;
            return $c;
        });
        $customers = \App\Services\UserActivityRanker::sortByRanked(
            $customers,
            $rankedNums,
            fn ($c) => $c->customer_number ?? $c->customer_id ?? ''
        );

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customers retrieved successfully.',
            'data' => $customers,
        ], 200);
    }

    /**
     * Create a new customer.
     */
    public function createCustomer(Request $request): JsonResponse
    {
        // Validate the incoming request to ensure the required fields are provided
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_number' => 'required|string|max:255',
            'customer_id' => 'required|string|max:255|unique:customers,customer_id',
        ]);

        // Use the authenticated user's name as the salesperson
        $salesperson = auth()->user()->name;

        // Create the customer with the validated data
        $customer = Customer::create([
            'customer_name' => $validated['customer_name'],
            'customer_number' => $validated['customer_number'],
            'customer_id' => $validated['customer_id'],
            'customer_site_id' => null,
            'salesperson' => $salesperson,
            'salesperson_key' => normalizeSalespersonKey($salesperson),
            'creation_date' => now(),
            'price_list_id' => "7010",
            'price_list_name' => "Karachi - Corporate",
        ]);

        // Return a success response with the created customer details
        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    /**
     * Search for products for a specific customer based on a search term.
     */
    public function searchCustomerProducts(Request $request): JsonResponse
    {
        // Read customer_id explicitly from the JSON request BODY, not from
        // the query string. Validation still runs through $request->validate
        // (which inspects all input sources), but the value used in the
        // controller comes from $request->input() — which for a POST with
        // application/json content type returns the body field.
        $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'searchTerm'  => 'nullable|string',
        ]);

        $bodyCustomerId = $request->input('customer_id');
        $bodySearchTerm = (string) $request->input('searchTerm', '');

        // Endpoint-identification log. Every product-search endpoint stamps a
        // unique tag here so we can tell from the logs which one the mobile
        // hit for any given search-after-customer-select flow.
        \Log::info('ProductSearch [POST /api/customers/search/products] hit', [
            'endpoint'    => 'POST /api/customers/search/products',
            'controller'  => 'CustomerController@searchCustomerProducts',
            'user_id'     => Auth::id(),
            'user_role'   => Auth::user()?->role,
            'raw_input'   => $request->all(),
            'searchTerm'  => $bodySearchTerm,
            'customer_id' => $bodyCustomerId,
            'server_now'  => now()->toDateTimeString(),
        ]);

        // Mirror what $validated used to hold so downstream references stay
        // intact without touching the rest of the closure.
        $validated = [
            'customer_id' => $bodyCustomerId,
            'searchTerm'  => $bodySearchTerm,
        ];

        $searchTerm = $bodySearchTerm;
        $terms = !empty($searchTerm) ? explode(' ', $searchTerm) : [];

        $user = Auth::user();

        // Caching is disabled for this endpoint. Stale caches were the root
        // cause of wrong-list prices being served to the cart — a search
        // result built for one customer would be served to another after a
        // customer switch on the same user account. Computing fresh against
        // MySQL on every call guarantees the response only ever carries the
        // current customer's allocated price-list rows.
        $items = (function () use ($terms, $validated, $searchTerm) {
            $user = Auth::user();

            // Build query with role-based access control
            $query = Customer::where('customer_id', $validated['customer_id']);

            // Apply role-based filtering
            if ($user->role !== 'admin') {
                // Role-based filtering for non-admin users
                if ($user->role === 'supply-chain') {
                    // Supply-chain users see customers from their Oracle organizations
                    if ($user->isOracleMapped()) {
                        $userOrgs = $user->getOracleOrganizations();
                        if (!empty($userOrgs)) {
                            $query->whereIn('oracle_ou_id', $userOrgs);
                        } else {
                            $query->where('customer_id', null);
                        }
                    }
                } elseif ($user->role === 'user') {
                    // Salesperson users see customers whose normalized salesperson key
                    // matches their normalized Oracle user name (see normalizeSalespersonKey()).
                    $query->where('salesperson_key', normalizeSalespersonKey($user->name));
                } else {
                    // Other roles get no access by default
                    $query->where('customer_id', null);
                }
            }

            // Retrieve the customer
            $customer = $query->first();

            // Check if the customer exists
            if (! $customer) {
                return null; // Return null to indicate customer not found
            }

            // If no search term, return customer's itemPrices as before.
            // Constrain the eager load to currently-active rows so historical
            // name-variant duplicates (end-dated months ago but still keyed
            // by the same price_list_id) don't surface as duplicate lines.
            if (empty($searchTerm)) {
                $today = now()->format('Y-m-d');
                $customer->load(['itemPrices' => function ($q) use ($today) {
                    $q->whereNotNull('list_price')
                      ->where(function ($sq) use ($today) {
                          $sq->whereNull('start_date_active')
                             ->orWhere('start_date_active', '<=', $today);
                      })
                      ->where(function ($sq) use ($today) {
                          $sq->whereNull('end_date_active')
                             ->orWhere('end_date_active', '>=', $today);
                      });
                }, 'itemPrices.item']);

                $itemCodes = $customer->itemPrices->pluck('item_code')->unique()->filter()->toArray();
                $globalDiscounts = \App\Models\ItemPrice::whereIn('item_code', $itemCodes)
                    ->whereNotNull('discounted_price')
                    ->select('item_code', 'discounted_price')
                    ->get()
                    ->groupBy('item_code');

                return $customer->itemPrices
                    ->map(function ($itemPrice) use ($globalDiscounts) {
                        $discount = $itemPrice->discounted_price;
                        if ($discount === null && isset($globalDiscounts[$itemPrice->item_code])) {
                            $discount = $globalDiscounts[$itemPrice->item_code]->first()->discounted_price;
                        }

                        return [
                            'inventory_item_id' => $itemPrice->item?->inventory_item_id,
                            'item_code' => $itemPrice->item_code ?: ($itemPrice->item?->item_code),
                            'item_description' => $itemPrice->item_description ?: ($itemPrice->item?->item_description),
                            'item_uom_code' => $itemPrice->uom,
                            'item_price' => $itemPrice->list_price,
                            'discounted_price' => $discount,
                            'price_list_id' => $itemPrice->price_list_id,
                            'price_list_name' => $itemPrice->price_list_name,
                        ];
                    })
                    ->values()
                    ->all();
            }

            // Search ALL items (not just customer's price list)
            $itemsQuery = \App\Models\Item::query();

            // Apply search terms to item_code and item_description
            if (!empty($terms)) {
                $itemsQuery->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->where(function ($subQ) use ($term) {
                            $subQ->where('item_description', 'like', '%' . trim($term) . '%')
                                 ->orWhere('item_code', 'like', '%' . trim($term) . '%');
                        });
                    }
                });
            }

            // Get the items
            $items = $itemsQuery->limit(50)->get();

            // For each item, try to get the prices from customer's price list
            // Only return items that have a price
            $allProductCodes = $items->pluck('item_code')->toArray();
            $globalDiscounts = \App\Models\ItemPrice::whereIn('item_code', $allProductCodes)
                ->whereNotNull('discounted_price')
                ->select('item_code', 'discounted_price')
                ->get()
                ->groupBy('item_code');

            // STRICT customer-scoped lookup: return ONLY rows from the
            // customer's exact allocated price_list_id. Previous versions
            // had name-variant LIKE matching and a city-wide fallback that
            // could silently surface a different tier's price (e.g.
            // Karachi-Corporate for a Karachi-Wholesale customer), which
            // is exactly how the Contact Adhesive QG-262 line on order
            // 20262139 ended up displayed at 640 instead of 615.16. With
            // the fallbacks removed, an item with no row in the customer's
            // own price list simply doesn't appear — the mobile cannot
            // accidentally render a price the customer isn't entitled to.
            $listId = trim((string) $customer->price_list_id);
            if ($listId === '') {
                return []; // customer has no allocated price list → return nothing
            }

            // Only currently-active rows. Without this filter, historical
            // name-variant duplicates (e.g. "Karachi - Wholesale" vs
            // "Karachi-Wholesale" both under price_list_id 7011) end-dated
            // months ago still surface — the mobile then sees two rows for
            // the same item_code/UOM with different prices. `today` is a
            // Y-m-d string so it compares against midnight-truncated dates
            // the same way OracleItemPrice::scopeActive does.
            $today = now()->format('Y-m-d');
            $primaryPrices = \App\Models\ItemPrice::whereIn('item_code', $allProductCodes)
                ->where('price_list_id', $listId)
                ->whereNotNull('list_price')
                ->where(function ($q) use ($today) {
                    $q->whereNull('start_date_active')
                      ->orWhere('start_date_active', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('end_date_active')
                      ->orWhere('end_date_active', '>=', $today);
                })
                ->get()
                ->groupBy('item_code');

            return $items->flatMap(function ($item) use ($globalDiscounts, $primaryPrices) {
                $itemPrices = $primaryPrices->get($item->item_code) ?? collect();

                // If the item has zero priced rows after every fallback, drop
                // it from the response entirely — null-priced lines can't be
                // ordered, so surfacing them would confuse the cart UX.
                if ($itemPrices->isEmpty()) {
                    return collect();
                }

                // Map each price record to a response object. Skip any row
                // whose list_price is null/empty so the response only carries
                // truly orderable lines.
                return $itemPrices
                    ->filter(fn ($p) => $p->list_price !== null && $p->list_price !== '')
                    ->map(function ($itemPrice) use ($item, $globalDiscounts) {
                        $discount = $itemPrice->discounted_price;
                        if ($discount === null && isset($globalDiscounts[$item->item_code])) {
                            $discount = $globalDiscounts[$item->item_code]->first()->discounted_price;
                        }

                        return [
                            'inventory_item_id' => $item->inventory_item_id,
                            'item_code' => $item->item_code,
                            'item_description' => $item->item_description,
                            'item_uom_code' => $itemPrice->uom,
                            'item_price' => $itemPrice->list_price,
                            'discounted_price' => $discount,
                            'price_list_id' => $itemPrice->price_list_id,
                            'price_list_name' => $itemPrice->price_list_name,
                        ];
                    });
            })
            ->values() // Re-index array
            ->all();
        })();

        // Check if customer was found
        if ($items === null) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer not found.',
            ], 404);
        }

        // Return the filtered items
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully.',
            'data' => $items,
        ], 200);
    }

    /**
     * Trigger the customer sync command via API.
     */
    public function syncCustomers(): JsonResponse
    {
        try {
            // Log the start of the sync
            Log::info('Customer sync triggered via API (Background) by user: ' . Auth::id());

            // Get the path to PHP and artisan
            $phpBinary = PHP_BINARY;
            $artisan = base_path('artisan');

            // Chain customers + item prices in the same background job so the
            // mobile app only has to call one endpoint to refresh both. Using
            // the `-clear` wrappers (routes/console.php) so the API response
            // cache is flushed after each sync — otherwise mobile keeps
            // returning the previous prices for up to 60 minutes from the
            // Cache::remember() block in ProductController.
            $customersCmd = "{$phpBinary} {$artisan} sync:oracle-customers-clear";
            $pricesCmd    = "{$phpBinary} {$artisan} sync:oracle-items-price-clear";

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: run customers first, then prices, in one detached shell
                pclose(popen("start /B cmd /C \"{$customersCmd} && {$pricesCmd}\" > NUL 2>&1", "r"));
            } else {
                exec("({$customersCmd} && {$pricesCmd}) > /dev/null 2>&1 &");
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Customers + item prices sync has been triggered in the background. This may take a few minutes to complete.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error triggering customer sync API: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while triggering sync: ' . $e->getMessage(),
            ], 500);
        }
    }
}