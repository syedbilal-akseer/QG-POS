<?php

namespace App\Http\Controllers\Api;

use App\Actions\OrderExportAction;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class OrderController extends Controller
{

    /**
     * Retrieve all orders.
     */
    public function orders(): JsonResponse
    {
        // Retrieve all orders with related data, applying pagination or filtering if needed
        $orders = Order::with([
            'customer:id,customer_id,customer_name,contact_number',
            'salesperson:id,name',
            'orderItems:id,order_id,inventory_item_id,uom,quantity,price,discount,sub_total',
            'orderItems.item:id,inventory_item_id,item_code,item_description',
            'orderItems.item.itemPrices:id,item_id,list_price,uom',
        ])
            ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')  // Order by created_at in descending order
            ->get();

        // Check if there are any orders
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No orders found.',
            ], 404);
        }

        // Transform the order data
        $orderList = $orders->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer->customer_name ?? null,
                'contact_number' => $order->customer->contact_number ?? null,
                'user_id' => $order->user_id,
                'salesperson_name' => $order->salesperson->name ?? null,
                'order_status' => $order->order_status->name(),
                'sub_total' => $order->sub_total,
                'discount' => $order->discount,
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
                        'discount' => $item->discount,
                        'sub_total' => $item->sub_total,
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
        });

        // Return the list of orders
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Orders retrieved successfully.',
            'data' => $orderList,
        ], 200);
    }


    /**
     * Place an order for a customer.
     */
    public function orderPlace(Request $request): JsonResponse
    {
        // Validate the request to ensure 'customer_id' and 'items' are provided
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'items' => 'required|array',
            'items.*.inventory_item_id' => 'required|exists:items,inventory_item_id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        // Retrieve the customer
        $customer = Customer::with('itemPrices:price_list_id,item_id,list_price')->where('customer_id', $validated['customer_id'])->first();

        // Check if the customer has a price list
        if (! $customer->itemPrices->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Customer does not have an associated price list.',
            ], 400);
        }

        // Initialize sub_total, discount, and total order amount
        $subTotal = 0;
        $totalItemDiscount = 0; // Total discount from order items

        // Use the transaction to create the order and its items, and return the order
        $order = DB::transaction(function () use ($customer, $validated, &$subTotal, &$totalItemDiscount) {
            // Create a new order for the customer
            $order = $customer->orders()->create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
            ]);

            // Loop through the items in the request and create OrderItems
            foreach ($validated['items'] as $itemData) {
                // Check if the item exists in the items table
                $item = Item::where('inventory_item_id', $itemData['inventory_item_id'])->first();
                if (! $item) {
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
                if (! $itemPrice) {
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
                $itemSubtotal = $itemPrice->list_price * $itemData['quantity'];

                // Add to the total sub_total
                $subTotal += $itemSubtotal;

                // Calculate the discount for this item
                $itemDiscount = $itemData['discount'] ?? 0;

                // Ensure the item discount doesn't exceed the item subtotal
                if ($itemDiscount > $itemSubtotal) {
                    $itemDiscount = $itemSubtotal;
                }

                // Add to the total item discount
                $totalItemDiscount += $itemDiscount;

                // Create the OrderItem
                $order->orderItems()->create([
                    'inventory_item_id' => $itemData['inventory_item_id'],
                    'uom' => $itemPrice->uom,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemPrice->list_price,
                    'sub_total' => $itemSubtotal - $itemDiscount,
                    'discount' => $itemDiscount,
                ]);
            }

            // Apply the order-level discount if provided
            $orderDiscount = $validated['discount'] ?? 0;

            // Total discount = sum of order item discounts + order-level discount
            $totalDiscount = $totalItemDiscount + $orderDiscount;

            // Ensure total discount doesn't exceed the subtotal
            if ($totalDiscount > $subTotal) {
                $totalDiscount = $subTotal;
            }

            // Calculate the total amount (subtotal minus all discounts)
            $totalAmount = $subTotal - $totalDiscount;

            // Ensure totalAmount is not negative
            if ($totalAmount < 0) {
                $totalAmount = 0;
            }

            // Update the order with sub_total, discount, and total_amount
            $order->update([
                'sub_total' => $subTotal,
                'discount' => $totalDiscount,
                'total_amount' => $totalAmount,
            ]);

            // Return the order to make it accessible outside the transaction
            return $order;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Order placed successfully.',
            'data' => [$order->load(['customer:customer_id,customer_name,customer_number', 'salesperson:id,name', 'orderItems.item.itemPrice'])],
        ], 200);
    }

    /**
     * Retrieve the order history for the authenticated user.
     */
    public function orderHistory(Request $request): JsonResponse
    {
        // Get the currently authenticated user
        $user = $request->user();
    
        // Validate the incoming request filters
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:d/m/Y',
            'end_date' => 'nullable|date_format:d/m/Y',
            'customer_id' => 'nullable|exists:customers,customer_id',
            'salesperson_id' => 'nullable|exists:users,id',
        ]);
    
        if (! $user) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'User is not authenticated.',
            ], 401);
        }
    
        // Apply filters via your custom action
        $orderExportAction = new OrderExportAction;
        $ordersQuery = $orderExportAction->applyFilters($validated);
    
        // Add relationships, selections, and limit after filtering
        $ordersQuery->with([
            'customer:id,customer_id,customer_name',
            'salesperson:id,name',
        ])
        ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
        ->latest()
        ->limit(10);
    
        // Cache the filtered and eager-loaded query result
        $cacheKey = 'user_order_history_' . $user->id . '_' . md5(json_encode($validated));
        $cacheTime = 60;
    
        $orders = Cache::remember($cacheKey, $cacheTime, function () use ($ordersQuery) {
            return $ordersQuery->get()->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer->customer_name ?? null,
                    'user_id' => $order->user_id,
                    'salesperson_name' => $order->salesperson->name ?? null,
                    'order_status' => $order->order_status->name(),
                    'sub_total' => $order->sub_total,
                    'discount' => $order->discount,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            });
        });
    
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Order history retrieved successfully.',
            'data' => $orders,
        ], 200);
    }
    

    /**
     * Retrieve the order details for a specific order.
     */
    public function orderDetails(Request $request): JsonResponse
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
                'orderItems:id,order_id,inventory_item_id,uom,quantity,price,discount,sub_total',
                'orderItems.item:id,inventory_item_id,item_code,item_description',
                'orderItems.item.itemPrices:id,item_id,list_price,uom',
            ])
                ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
                ->where('order_number', $validated['order_number'])
                ->first();
        });

        // Check if the order exists
        if (! $order) {
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
            'sub_total' => $order->sub_total,
            'discount' => $order->discount,
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
                    'discount' => $item->discount,
                    'sub_total' => $item->sub_total,
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
     */
    public function orderSearch(Request $request): JsonResponse
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
                'orderItems.item:inventory_item_id,item_description,item_code',
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
                ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
                ->latest()
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
                'sub_total' => $order->sub_total,
                'discount' => $order->discount,
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

    /**
     * Filter orders based on given criteria.
     */
    public function orderFilter(Request $request): JsonResponse
    {
        // Validate the incoming request filters
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:d/m/Y',
            'end_date' => 'nullable|date_format:d/m/Y',
            'customer_id' => 'nullable|exists:customers,customer_id',
            'salesperson_id' => 'nullable|exists:users,id',
        ]);

        // Instantiate the action class to apply filters
        $orderExportAction = new OrderExportAction;

        // Apply filters to the order query using the validated filters
        $orders = $orderExportAction->applyFilters($validated);

        // Transform the orders data to match the API response format
        $orderData = $orders->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->customer_name ?? null,
                'salesperson_name' => $order->salesperson->name ?? null,
                'order_status' => $order->order_status->name(),
                'discount' => $order->discount,
                'sub_total' => $order->sub_total,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
            ];
        });

        // Return the filtered orders in JSON response
        return response()->json([
            'success' => true,             // Indicate the request was successful
            'status' => 200,               // HTTP status code
            'message' => 'Orders filtered successfully.', // Success message
            'data' => $orderData,          // The filtered orders data
        ], 200);
    }

    /**
     * Export orders based on given filters.
     */
    public function orderExport(Request $request): JsonResponse
    {
        // Validate the incoming request filters
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:d/m/Y',
            'end_date' => 'nullable|date_format:d/m/Y',
            'customer_id' => 'nullable|exists:customers,id',
            'salesperson_id' => 'nullable|exists:users,id',
            'format' => 'nullable|in:csv,excel,pdf',
        ]);

        // Instantiate the action class that handles the export process
        $exportAction = new OrderExportAction;

        // Get the format from the request or default to 'csv'
        $format = $validated['format'] ?? 'csv';

        // Call the handle method on the action class and pass the validated filters
        $fileUrl = $exportAction->handle($request->only([
            'start_date',
            'end_date',
            'customer_id',
            'salesperson_id',
        ]), $format);

        // Return a JSON response with the file URL
        return response()->json([
            'success' => true,
            'status' => 200,
            'file_url' => $fileUrl,
        ]);
    }
}
