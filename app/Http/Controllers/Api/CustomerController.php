<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

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
                // Salesperson users see customers where salesperson matches their Oracle user name
                $salespersonName = $user->name ?: $user->name;
                $query->where('salesperson', $salespersonName);
            } else {
                // Other roles get no access by default
                $query->where('customer_id', null);
            }
            
            return $query->paginate(10);
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customers retrieved successfully',
            'data' => $customers->items(),
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

        // Build query with role-based access control
        $query = Customer::with('itemPrices.item')->where('customer_id', $customerId);
        
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
                // Salesperson users see customers where salesperson matches their Oracle user name
                $salespersonName = $user->name ?: $user->name;
                $query->where('salesperson', $salespersonName);
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

        // Convert customer to array and exclude item_prices
        $customerArray = $customer->toArray();
        unset($customerArray['item_prices']);

        // Prepare the list of products with their prices
        $products = [];
        if ($customer->itemPrices->isNotEmpty()) {
            $products = $customer->itemPrices->map(function ($itemPrice) {
                // Debug: Check if item relationship is loaded
                $hasItem = $itemPrice->item !== null;
                $itemCode = $itemPrice->item_code;
                $inventoryItemId = $hasItem ? $itemPrice->item->inventory_item_id : null;

                \Log::info('ItemPrice relationship debug', [
                    'item_code_from_price' => $itemCode,
                    'has_item_relation' => $hasItem,
                    'inventory_item_id' => $inventoryItemId,
                ]);

                return [
                    'inventory_item_id' => $inventoryItemId,
                    'item_code' => $itemPrice->item_code ?: $itemPrice->item?->item_code,
                    'item_description' => $itemPrice->item_description ?: $itemPrice->item?->item_description,
                    'item_uom_code' => $itemPrice->uom,
                    'item_price' => $itemPrice->list_price,
                ];
            })->toArray();
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
        $cacheKey = 'customer_products_'.$validated['customer_id'].'_user_'.$user->id;
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            $user = Auth::user();
            
            // Build query with role-based access control
            $query = Customer::with('itemPrices.item')->where('customer_id', $validated['customer_id']);
            
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
                // Salesperson users see customers where salesperson matches their Oracle user name
                $salespersonName = $user->name ?: $user->name;
                $query->where('salesperson', $salespersonName);
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

            // Prepare the list of items with their prices
            return $customer->itemPrices->map(function ($itemPrice) {
                return [
                    'inventory_item_id' => $itemPrice->item?->inventory_item_id,
                    'item_code' => $itemPrice->item_code ?: $itemPrice->item?->item_code,
                    'item_description' => $itemPrice->item_description ?: $itemPrice->item?->item_description,
                    'item_uom_code' => $itemPrice->uom,
                    'item_price' => $itemPrice->list_price,
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
                    // Salesperson users see customers where salesperson matches their Oracle user name
                    $salespersonName = $user->name ?: $user->name;
                    $query->where('salesperson', $salespersonName);
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

        // Return the results in JSON format
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
        // Validate the request to ensure 'customer_id' and 'searchTerm' are provided
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'searchTerm' => 'nullable|string',
        ]);

        // Extract the search term and break it into individual words
        $searchTerm = $validated['searchTerm'] ?? '';
        $terms = !empty($searchTerm) ? explode(' ', $searchTerm) : [];

        $user = Auth::user();
        // Generate a cache key based on customer ID, search term, and user
        $cacheKey = 'customer_'.$validated['customer_id'].'_search_'.md5($searchTerm).'_user_'.$user->id;
        $cacheTime = 60; // Cache for 60 minutes

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($terms, $validated, $searchTerm) {
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
                    // Salesperson users see customers where salesperson matches their Oracle user name
                    $salespersonName = $user->name ?: $user->name;
                    $query->where('salesperson', $salespersonName);
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

            // If no search term, return customer's itemPrices as before
            if (empty($searchTerm)) {
                $customer->load(['itemPrices.item']);

                return $customer->itemPrices
                    ->map(function ($itemPrice) {
                        return [
                            'inventory_item_id' => $itemPrice->item?->inventory_item_id,
                            'item_code' => $itemPrice->item_code ?: ($itemPrice->item?->item_code),
                            'item_description' => $itemPrice->item_description ?: ($itemPrice->item?->item_description),
                            'item_uom_code' => $itemPrice->uom,
                            'item_price' => $itemPrice->list_price
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
            return $items->flatMap(function ($item) use ($customer) {
                // Try to find the prices for this customer's price list
                // Match by price_list_id OR price_list_name (some records may have null price_list_id)
                $itemPrices = \App\Models\ItemPrice::where('item_code', $item->item_code)
                    ->where(function ($q) use ($customer) {
                        $q->where('price_list_id', $customer->price_list_id)
                          ->orWhere('price_list_name', $customer->price_list_name);
                    })
                    ->get();

                // Map each price record to a response object
                return $itemPrices->map(function ($itemPrice) use ($item) {
                    return [
                        'inventory_item_id' => $item->inventory_item_id,
                        'item_code' => $item->item_code,
                        'item_description' => $item->item_description,
                        'item_uom_code' => $itemPrice->uom,
                        'item_price' => $itemPrice->list_price
                    ];
                });
            })
            ->values() // Re-index array
            ->all();
        });

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
}