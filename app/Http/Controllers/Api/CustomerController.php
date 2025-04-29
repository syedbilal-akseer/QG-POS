<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerController extends Controller
{
    /*
     * Retrieve all customers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customers(): JsonResponse
    {
        $cacheKey = 'customers_page_'.request()->input('page', 1);
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $customers = Cache::remember($cacheKey, $cacheTime, function () {
            return Customer::select('customer_id', 'customer_name', 'customer_number')->paginate(10);
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

        // Generate a cache key based on the customer ID
        $cacheKey = 'customer_details_'.$customerId;
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $customer = Cache::remember($cacheKey, $cacheTime, function () use ($customerId) {
            // Retrieve the customer by ID
            return Customer::where('customer_id', $customerId)->first();
        });

        // Return the response
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer retrieved successfully',
            'data' => $customer,
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

        $cacheKey = 'customer_products_'.$validated['customer_id'];
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            // Retrieve the customer with their item prices
            $customer = Customer::with('itemPrices.item')
                ->where('customer_id', $validated['customer_id'])
                ->first();

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
                    'inventory_item_id' => $itemPrice->item_id,
                    'item_code' => $itemPrice->item->item_code,
                    'item_description' => $itemPrice->item->item_description,
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

        // Generate a cache key based on the search term
        $cacheKey = 'search_customers_'.md5($searchTerm);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $customers = Cache::remember($cacheKey, $cacheTime, function () use ($searchTerm) {
            // Query customers using the search term
            return Customer::where(function ($query) use ($searchTerm) {
                $query->where('customer_id', 'like', '%'.$searchTerm.'%')
                    ->orWhere('customer_number', 'like', '%'.$searchTerm.'%')
                    ->orWhere('customer_name', 'like', '%'.$searchTerm.'%');
            })
                ->get();
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
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm);

        // Generate a cache key based on customer ID and search term
        $cacheKey = 'customer_'.$validated['customer_id'].'_search_'.md5($validated['searchTerm']);
        $cacheTime = 60; // Cache for 60 minutes

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($terms, $validated, $searchTerm) {
            // Retrieve the customer along with their item prices using the relation, applying eager loading for the related items
            $customer = Customer::where('customer_id', $validated['customer_id'])->first();

            // Check if the customer exists
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Customer not found.',
                ], 404);
            } 

            $customer->load(['itemPrices.item' => function ($query) use ($terms, $searchTerm) {
                // Apply search term filtering on the related item within the itemPrices relationship
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->where(function ($q) use ($term) {
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
                    "%$searchTerm%",                          // Exact full-term match in either column
                    "%$searchTerm%",
                    "%" . implode('%', $terms) . "%",       // Terms in order
                    "%" . implode('%', $terms) . "%",
                    "%" . implode('%', array_reverse($terms)) . "%",  // Terms in reverse order
                    "%" . implode('%', array_reverse($terms)) . "%"
                ]);
            }]);


            // Transform the retrieved data
            return $customer->itemPrices
                ->filter(function ($itemPrice) {
                    // Filter out item prices that don't have a matching item (based on search terms)
                    return $itemPrice->item;
                })
                ->map(function ($itemPrice) {
                    return [
                        'inventory_item_id' => $itemPrice->item_id,
                        'item_code' => $itemPrice->item->item_code,
                        'item_description' => $itemPrice->item->item_description,
                        'item_uom_code' => $itemPrice->uom,
                        'item_price' => $itemPrice->list_price,
                    ];
                })
                ->values() // Reset the keys to create a numerically indexed array
                ->all(); // Convert to a plain array
        });

        // Return the filtered items
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully.',
            'data' => $items,
        ], 200);
    }
}
