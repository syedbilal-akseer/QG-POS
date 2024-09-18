<?php

namespace App\Http\Controllers\Api;

use App\Models\Item;
use App\Models\Order;
use App\Models\Customer;
use App\Models\ItemPrice;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ResourceController extends Controller
{
    /*
     * Retrieve all products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function products()
    {
        $cacheKey = 'products_page_' . request()->input('page', 1);
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $products = Cache::remember($cacheKey, $cacheTime, function () {
            return Item::paginate(10);
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully',
            'data' => $products->items(),
            'pagination' => [
                'total' => $products->total(),
                'count' => $products->count(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'total_pages' => $products->lastPage(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Retrieve a specific product's details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProduct(Request $request)
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

        if (!$product) {
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
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchProduct(Request $request)
    {
        // Validate the request to ensure 'searchTerm' is provided
        $validated = $request->validate([
            'searchTerm' => 'required|string',
        ]);

        // Extract the search term and break it into individual words
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm);

        // Generate a cache key based on the search term
        $cacheKey = 'search_products_' . md5($searchTerm);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $products = Cache::remember($cacheKey, $cacheTime, function () use ($terms) {
            // Query products using the search terms and load itemPrice relationship
            return Item::with('itemPrice')
                ->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        $query->where('inventory_item_id', 'like', '%' . $term . '%')
                            ->orWhere('item_code', 'like', '%' . $term . '%')
                            ->orWhere('item_description', 'like', '%' . $term . '%');
                    }
                })
                ->get();
        });

        // Map the results to the desired format
        $mappedProducts = $products->map(function ($item) {
            $itemPrice = $item->itemPrice;

            return [
                'inventory_item_id' => $item->inventory_item_id,
                'item_code' => $item->item_code,
                'item_description' => $item->item_description,
                'primary_uom_code' => $item->primary_uom_code,
                'secondary_uom_code' => $item->secondary_uom_code,
                'major_category' => $item->major_category,
                'minor_category' => $item->minor_category,
                'sub_minor_category' => $item->sub_minor_category,
                // 'item_uom_code' => optional($itemPrice)->uom,
                // 'item_price' => optional($itemPrice)->list_price,
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

    /*
     * Retrieve all customers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customers()
    {
        $cacheKey = 'customers_page_' . request()->input('page', 1);
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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomer(Request $request)
    {
        // Validate the request to ensure 'customer_id' is provided
        $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        // Extract the customer ID from the request
        $customerId = $request->customer_id;

        // Generate a cache key based on the customer ID
        $cacheKey = 'customer_details_' . $customerId;
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

    /*
     * Retrieve the customer's products with their prices.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerProducts(Request $request)
    {
        // Validate the request to ensure 'customer_id' is provided and exists
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        $cacheKey = 'customer_products_' . $validated['customer_id'];
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            // Retrieve the customer with their item prices
            $customer = Customer::with('itemPrices.item')
                ->where('customer_id', $validated['customer_id'])
                ->first();

            // If the customer was not found, return an empty array
            if (!$customer) {
                return [];
            }

            // Check if the customer has a price list
            if (!$customer->itemPrices->isNotEmpty()) {
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
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchCustomer(Request $request)
    {
        // Validate the request to ensure 'searchTerm' is provided
        $validated = $request->validate([
            'searchTerm' => 'required|string',
        ]);

        // Extract the search term
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm);

        // Generate a cache key based on the search term
        $cacheKey = 'search_customers_' . md5($searchTerm);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $customers = Cache::remember($cacheKey, $cacheTime, function () use ($terms) {
            // Query customers using the search term
            return Customer::where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->where('customer_id', 'like', '%' . $term . '%')
                        ->orWhere('customer_number', 'like', '%' . $term . '%')
                        ->orWhere('customer_name', 'like', '%' . $term . '%');
                }
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
     * Search for products for a specific customer based on a search term.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchCustomerProducts(Request $request)
    {
        // Validate the request to ensure 'customer_id' and 'searchTerm' are provided
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'searchTerm' => 'required|string',
        ]);

        // Extract the search term and break it into individual words
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm);

        // Generate a cache key based on customer ID and search term
        $cacheKey = 'customer_' . $validated['customer_id'] . '_search_' . md5($validated['searchTerm']);
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $items = Cache::remember($cacheKey, $cacheTime, function () use ($terms, $validated) {
            // Retrieve the customer and their price list IDs
            $customer = Customer::where('customer_id', $validated['customer_id'])
                ->with('itemPrices')
                ->first();

            // Check if the customer exists
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Customer not found.',
                ], 404);
            }

            // Search for items in the customer's price list that match the search term
            return ItemPrice::where('price_list_id', $customer->price_list_id)
                ->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        $query->where('item_code', 'like', '%' . $term . '%')
                            ->orWhere('item_description', 'like', '%' . $term . '%');
                    }
                })
                ->with('item') // Eager load the related Item
                ->get()
                ->map(function ($itemPrice) {
                    return [
                        'inventory_item_id' => $itemPrice->item_id,
                        'item_code' => $itemPrice->item->item_code,
                        'item_description' => $itemPrice->item->item_description,
                        'item_uom_code' => $itemPrice->uom,
                        'item_price' => $itemPrice->list_price,
                    ];
                });
        });

        // Return the filtered items
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully.',
            'data' => $items,
        ], 200);
    }

    /**
     * Place an order for a customer.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function placeOrder(Request $request)
    {
        // Validate the request to ensure 'customer_id' and 'items' are provided
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'items' => 'required|array',
            'items.*.inventory_item_id' => 'required|exists:items,inventory_item_id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Retrieve the customer
        $customer = Customer::with('itemPrices:price_list_id,item_id,list_price')->where('customer_id', $validated['customer_id'])->first();

        // Check if the customer has a price list
        if (!$customer->itemPrices->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Customer does not have an associated price list.',
            ], 400);
        }

        // Initialize the total order amount
        $totalAmount = 0;

        // Use the transaction to create the order and its items, and return the order
        $order = DB::transaction(function () use ($customer, $validated, &$totalAmount) {
            // Create a new order for the customer
            $order = $customer->orders()->create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
            ]);

            // Loop through the items in the request and create OrderItems
            foreach ($validated['items'] as $itemData) {
                // Check if the item exists in the items table
                $item = Item::where('inventory_item_id', $itemData['inventory_item_id'])->first();
                if (!$item) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'errors' => [
                            'message' => "Item with ID {$itemData['inventory_item_id']} does not exist.",
                        ],
                    ], 400);
                }

                // Find the price for the item from the customer's price list
                $itemPrice = $customer->itemPrices()
                    ->where('item_id', $itemData['inventory_item_id'])
                    ->first();

                // Check if the item price was found
                if (!$itemPrice) {
                    // Handle the case where no matching price was found
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'errors' => [
                            'message' => "Price not found for item ID: {$item['inventory_item_id']}",
                        ],
                    ], 400);
                }

                // Calculate the subtotal for this item
                $subtotal = $itemPrice->list_price * $itemData['quantity'];

                // Add to the total order amount
                $totalAmount += $subtotal;

                // Create the OrderItem
                $order->orderItems()->create([
                    'inventory_item_id' => $itemData['inventory_item_id'],
                    'uom' => $itemPrice->uom,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemPrice->list_price,
                ]);
            }

            // Update the total amount for the order
            $order->update(['total_amount' => $totalAmount]);

            // Return the order to make it accessible outside the transaction
            return $order;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Order placed successfully.',
            'data' => [$order->load(['customer', 'orderItems.item.itemPrice'])],
        ], 200);
    }

    /**
     * Retrieve the order history for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderHistory(Request $request)
    {
        // Get the currently authenticated user
        $user = $request->user();

        // Check if the user exists
        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'User is not authenticated.',
            ], 401);
        }

        $cacheKey = 'user_order_history_' . $user->id;
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $orders = Cache::remember($cacheKey, $cacheTime, function () use ($user) {
            // Retrieve the user's orders with related order items, customers, and items
            return $user->orders()
                ->with([
                    'customer:id,customer_id,customer_name',
                    'salesperson:id,name',
                ])
                ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'total_amount', 'created_at', 'updated_at')
                ->get()
                ->map(function ($order) {
                    // Transform the order data
                    return [
                        'order_number' => $order->order_number,
                        'customer_id' => $order->customer_id,
                        'customer_name' => $order->customer->customer_name ?? null,
                        'user_id' => $order->user_id,
                        'salesperson_name' => $order->salesperson->name ?? null,
                        'order_status' => $order->order_status->name(),
                        'total_amount' => $order->total_amount,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];
                });
        });

        // Return the order history
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Order history retrieved successfully.',
            'data' => $orders,
        ], 200);
    }

    /**
     * Retrieve the order details for a specific order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderDetails(Request $request)
    {
        // Validate the request to ensure 'order_number' is provided and exists
        $validated = $request->validate([
            'order_number' => 'required|exists:orders,order_number',
        ]);

        $cacheKey = 'order_details_' . $validated['order_number'];
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $order = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            // Retrieve the order with related order items, customers, and items
            return Order::with([
                'customer:id,customer_id,customer_name,contact_number',
                'salesperson:id,name',
                'orderItems:id,order_id,inventory_item_id,uom,quantity,price',
                'orderItems.item:id,inventory_item_id,item_code,item_description',
                'orderItems.item.itemPrice:id,item_id,list_price,uom',
            ])
                ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'total_amount', 'created_at', 'updated_at')
                ->where('order_number', $validated['order_number'])
                ->first();
        });

        // Check if the order exists
        if (!$order) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Order not found.',
            ], 404);
        }

        // Transform the order data
        $orderDetails = [
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer->customer_name ?? null,
            'contact_number' => $order->customer->contact_number ?? null,
            'user_id' => $order->user_id,
            'salesperson_name' => $order->salesperson->name ?? null,
            'order_status' => $order->order_status->name(),
            'total_amount' => $order->total_amount,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'order_items' => $order->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'uom' => $item->uom,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'item' => [
                        'inventory_item_id' => $item->item->inventory_item_id,
                        'item_code' => $item->item->item_code,
                        'item_description' => $item->item->item_description,
                        'item_price' => $item->item->itemPrice->list_price ?? null,
                        'item_uom' => $item->item->itemPrice->uom ?? null,
                    ],
                ];
            }),
        ];

        // Return the order details
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Order details retrieved successfully.',
            'data' => $orderDetails,
        ], 200);
    }

    /**
     * Search for orders by order_number, customer_id, or order_status using LIKE and map results.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchOrder(Request $request)
    {
        // Validate the request to ensure 'searchTerm' is provided
        $validated = $request->validate([
            'searchTerm' => 'required|string',
        ]);

        // Extract the search term and break it into individual words
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm); // Split the search term by spaces

        // Get the ID of the authenticated user
        $userId = Auth::id();

        // Generate a cache key based on the search term and user ID
        $cacheKey = 'search_orders_' . md5($searchTerm . $userId);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $orders = Cache::remember($cacheKey, $cacheTime, function () use ($terms, $userId) {
            // Query orders using the search term, filter by user ID, and load related customer and order items
            return Order::with([
                'customer:id,customer_id,customer_name',
                'salesperson:id,name',
                'orderItems:id,order_id,inventory_item_id',
                'orderItems.item:inventory_item_id,item_description,item_code'
            ])
                ->where('user_id', $userId) // Filter orders by authenticated user's ID
                ->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        $term = trim($term); // Trim whitespace from terms
                        $query->where('order_number', 'like', '%' . $term . '%')
                            ->orWhere('customer_id', 'like', '%' . $term . '%')
                            ->orWhere('order_status', 'like', '%' . $term . '%')
                            ->orWhereHas('customer', function ($q) use ($term) {
                                $q->where('customer_name', 'like', '%' . $term . '%')
                                    ->orWhere('customer_number', 'like', '%' . $term . '%')
                                    ->orWhere('customer_id', 'like', '%' . $term . '%')
                                    ->orWhere('city', 'like', '%' . $term . '%')
                                    ->orWhere('area', 'like', '%' . $term . '%')
                                    ->orWhere('contact_number', 'like', '%' . $term . '%')
                                    ->orWhere('email_address', 'like', '%' . $term . '%');
                            })
                            ->orWhereHas('orderItems', function ($q) use ($term) {
                                $q->whereHas('item', function ($q) use ($term) {
                                    $q->where('item_description', 'like', '%' . $term . '%')
                                        ->orWhere('item_code', 'like', '%' . $term . '%');
                                });
                            });
                    }
                })
                ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'total_amount', 'created_at', 'updated_at')
                ->get();
        });

        // Map the results to the desired format
        $mappedOrders = $orders->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer->customer_name ?? null,
                'user_id' => $order->user_id,
                'salesperson_name' => $order->salesperson->name ?? null,
                'order_status' => $order->order_status->name(),
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        // Return the results in JSON format
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Orders retrieved successfully.',
            'data' => $mappedOrders,
        ], 200);
    }
}
