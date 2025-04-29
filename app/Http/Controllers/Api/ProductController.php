<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        // Get the current page from the request (for pagination)
        $page = request()->input('page', 1);
        $perPage = 10; // Number of items per page
    
        // Calculate the offset for pagination
        $offset = ($page - 1) * $perPage;
    
        // Query to get items with their item prices
        $products = DB::table('items')
            ->leftJoin('item_prices', 'items.inventory_item_id', '=', 'item_prices.item_id')
            ->select(
                'items.id', // Explicitly select the id from the items table
                'items.inventory_item_id',
                'items.item_code',
                'items.item_description',
                'items.primary_uom_code',
                'items.secondary_uom_code',
                'items.major_category',
                'items.minor_category',
                'items.sub_minor_category',
                'items.created_at',
                'items.updated_at',
                'item_prices.price_list_id',
                'item_prices.price_list_name',
                'item_prices.uom',
                'item_prices.list_price',
                'item_prices.start_date_active',
                // 'item_prices.end_date_active'
            )  // Select the necessary columns from both tables
            ->orderBy('items.id')  // Order by the items table id
            ->offset($offset)  // Apply offset for pagination
            ->limit($perPage)  // Limit the results to $perPage
            ->get();  // Get the results as a collection
    
        // For pagination info
        $totalProducts = DB::table('items')->count();  // Get the total number of items
        $totalPages = ceil($totalProducts / $perPage);  // Calculate total pages
    
        // Return the products and pagination info
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully',
            'data' => $products,
            'pagination' => [
                'total' => $totalProducts,
                'count' => count($products),
                'per_page' => $perPage,
                'current_page' => (int)$page,
                'total_pages' => $totalPages,
                'next_page_url' => $page < $totalPages,
                'prev_page_url' => $page > 1,
            ],
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
        ]);

        // Extract the search term and break it into individual words
        $searchTerm = $validated['searchTerm'];
        $terms = explode(' ', $searchTerm);

        // Generate a cache key based on the search term
        $cacheKey = 'search_products_' . md5($searchTerm);
        $cacheTime = 60; // Cache time in minutes

        // Attempt to retrieve data from cache
        $products = Cache::remember($cacheKey, $cacheTime, function () use ($terms, $searchTerm) {
            // Query products using the search terms and load itemPrice relationship
            // return Item::with('itemPrice')
            //     ->where(function ($query) use ($terms) {
            //         foreach ($terms as $term) {
            //             $query->where('item_code', 'like', '%'.$term.'%')
            //                 ->orWhere('item_description', 'like', '%'.$term.'%');
            //         }
            //     })
            //     ->get();

            return Item::with('itemPrices')
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
                ->get();
        });

        // Map the results to the desired format
        $mappedProducts = $products->flatMap(function ($item) {
            return $item->itemPrices->map(function ($price) use ($item) {
                return [
                    'id' => $item->id, // Adding the id of the item (matches 'items.id')
                    'inventory_item_id' => $item->inventory_item_id,
                    'item_code' => $item->item_code,
                    'item_description' => $item->item_description,
                    'primary_uom_code' => $item->primary_uom_code,
                    'secondary_uom_code' => $item->secondary_uom_code,
                    'major_category' => $item->major_category,
                    'minor_category' => $item->minor_category,
                    'sub_minor_category' => $item->sub_minor_category,
                    'created_at' => $item->created_at, // Include 'created_at'
                    'updated_at' => $item->updated_at, // Include 'updated_at'
                    'price_list_id' => $price->price_list_id, // Adding price list id
                    'price_list_name' => $price->price_list_name,
                    'uom' => $price->uom,
                    'list_price' => $price->list_price, // Matching 'list_price' from item_prices
                    'start_date_active' => $price->start_date_active,
                    'end_date_active' => $price->end_date_active,
                ];
            });
        });


        // Return the results in JSON format
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products retrieved successfully.',
            'data' => $mappedProducts,
        ], 200);
    }
}
