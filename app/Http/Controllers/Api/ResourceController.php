<?php

namespace App\Http\Controllers\Api;

use App\Models\Item;
use App\Models\Order;
use App\Models\Customer;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ResourceController extends Controller
{
    public function products()
    {
        $products = Item::paginate(10);

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

    public function customers()
    {
        $customers = Customer::get(['customer_id','customer_name']);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customers retrieved successfully',
            'data' => $customers,
        ], 200);
    }

    public function getCustomer(Request $request)
    {
        // Validate the request to ensure 'id' is provided
        $request->validate([
            'id' => 'required|exists:customers,id',
        ]);

        // Retrieve the customer by ID
        $customer = Customer::find($request->id);

        // Return the response
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer retrieved successfully',
            'data' => $customer,
        ], 200);
    }

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

    public function getCustomerProducts(Request $request)
    {
        // Validate the request to ensure 'customer_id' is provided and exists
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        // Retrieve the customer with their item prices
        $customer = Customer::with('itemPrices.item')
            ->where('customer_id', $validated['customer_id'])
            ->first();

        // If the customer was not found, return a 404 response
        if (!$customer) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer not found.',
            ], 404);
        }

        // Check if the customer has a price list
        if (!$customer->itemPrices->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No items found for this customer.',
            ], 404);
        }

        // Prepare the list of items with their prices
        $items = $customer->itemPrices->map(function ($itemPrice) {
            return [
                'inventory_item_id' => $itemPrice->item_id,
                'item_code' => $itemPrice->item->item_code,
                'item_description' => $itemPrice->item->item_description,
                'primary_uom_code' => $itemPrice->item->primary_uom_code,
                'item_price' => $itemPrice->list_price,
            ];
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Items retrieved successfully.',
            'data' => $items,
        ], 200);
    }
}
