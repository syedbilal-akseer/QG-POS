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
        $customers = Customer::paginate(10);

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
                    throw new \Exception("Item with ID {$itemData['inventory_item_id']} does not exist.");
                }

                // Find the price for the item from the customer's price list
                $itemPrice = $customer->itemPrices()
                    ->where('item_id', $itemData['inventory_item_id'])
                    ->first();

                // Check if the item price was found
                if (!$itemPrice) {
                    throw new \Exception("Price not found for item ID {$itemData['inventory_item_id']}");
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
            'data' => [$order],
        ], 200);
    }
}
