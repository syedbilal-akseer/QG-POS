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
use App\Enums\OrderStatusEnum;

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
            'transporter:id,value,description',
            'orderItems:id,order_id,inventory_item_id,uom,quantity,price,cap_price,discount,sub_total',
            'orderItems.item:id,inventory_item_id,item_code,item_description',
        ])
            ->select('id', 'order_number', 'customer_id', 'user_id', 'transporter_id', 'order_status', 'remarks', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
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
                'transporter_id' => $order->transporter_id,
                'transporter_name' => $order->transporter->description ?? null,
                'transporter_value' => $order->transporter->value ?? null,
                'remarks' => $order->remarks,
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
                        'cap_price' => $item->cap_price,
                        'discount' => $item->discount,
                        'sub_total' => $item->sub_total,
                        'item' => [
                            'inventory_item_id' => $item->item->inventory_item_id,
                            'item_code' => $item->item->item_code,
                            'item_description' => $item->item->item_description,
                            // Return the historical price charged on this order line,
                            // NOT the current list_price. The Item::itemPrice relation
                            // is a belongsTo on item_code which non-deterministically
                            // picks one of many ItemPrice rows (the item appears once
                            // per price-list × UOM combination) — and even when it
                            // picks "a" row, that row reflects the current SC-pushed
                            // price, not what the customer was actually charged. That
                            // mismatch breaks the mobile's math because
                            // item.item_price × qty - discount no longer equals
                            // sub_total. Falling back to $item->price (the order
                            // line's snapshot) keeps every displayed number internally
                            // consistent: price × qty - discount = sub_total.
                            'item_price' => $item->price,
                            // Return the UOM that was actually saved on this order line.
                            'item_uom' => $item->uom,
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
        // Validate the request to ensure 'customer_id' and 'items' are provided.
        // items.*.price is OPTIONAL — when sent, the server stores it as the
        // line's unit price so the Order Summary screen the user just saw
        // matches /orders/history and /orders/details exactly. Without it
        // (older mobile builds) the server keeps falling back to the current
        // ItemPrice.list_price, which is what was always happening.
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'items' => 'required|array',
            'items.*.inventory_item_id' => 'required|exists:items,inventory_item_id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.uom' => 'nullable|string|max:50',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.cap_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'transporter_id' => 'nullable|exists:transporters,id',
            'remarks' => 'nullable|string|max:1000',
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
        $paidLinesForPromo = []; // captured during loop, fed to PromotionalSchemeService

        try {
            // Use the transaction to create the order and its items, and return the order
            $order = DB::transaction(function () use ($customer, $validated, &$subTotal, &$totalItemDiscount, &$paidLinesForPromo) {
            // Create a new order for the customer
            $order = $customer->orders()->create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'transporter_id' => $validated['transporter_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Loop through the items in the request and create OrderItems
            foreach ($validated['items'] as $idx => $itemData) {
                // Check if the item exists in the items table
                $item = Item::where('inventory_item_id', $itemData['inventory_item_id'])->first();
                if (! $item) {
                    throw new \Exception("Item with ID {$itemData['inventory_item_id']} does not exist.");
                }

                // Validated array only contains declared rule fields. Read the
                // raw request item too in case mobile sends UOM under a key
                // we didn't list (e.g. "unit", "selectedUom").
                $rawItem = request()->input("items.{$idx}", []);

                // Find the price for the item from the customer's price list
                // Use a more robust lookup that handles variations in price list names (spaces vs no spaces)
                // and ensures item_code is trimmed.
                $itemCode = trim($item->item_code);
                $listId = trim($customer->price_list_id);
                $listName = trim($customer->price_list_name);

                // Create a series of names to search for
                $flexibleName = str_replace(' - ', '-', $listName);
                $flexibleNameAlt = str_replace('-', ' - ', $listName);
                $searchNames = [
                    $listName,
                    $flexibleName,
                    $flexibleNameAlt,
                    str_replace('-', ' ', $listName), // Karachi Wholesale
                    str_replace(' - ', ' ', $listName), // Karachi Wholesale
                ];

                // Accept UOM under multiple key names — mobile clients
                // historically use different conventions.
                $requestedUom = $itemData['uom']
                    ?? ($rawItem['uom']             ?? null)
                    ?? ($rawItem['unit']            ?? null)
                    ?? ($rawItem['unit_of_measure'] ?? null)
                    ?? ($rawItem['unitOfMeasure']   ?? null)
                    ?? ($rawItem['selectedUom']     ?? null)
                    ?? ($rawItem['selected_uom']    ?? null)
                    ?? null;
                if (is_string($requestedUom)) {
                    $requestedUom = trim($requestedUom) ?: null;
                }

                // Active-date predicate reused by all three lookups below so
                // end-dated / not-yet-active name-variant duplicates can
                // never be picked as the priced row (which then lands on the
                // Oracle order line). Without this, the primary lookup could
                // silently return a stale row like the historical
                // "Karachi-Wholesale" duplicate (end-dated months ago) and
                // book the line at the wrong price.
                $today = now()->format('Y-m-d');
                $activeDateFilter = function ($q) use ($today) {
                    $q->where(function ($sq) use ($today) {
                        $sq->whereNull('start_date_active')
                           ->orWhere('start_date_active', '<=', $today);
                    })->where(function ($sq) use ($today) {
                        $sq->whereNull('end_date_active')
                           ->orWhere('end_date_active', '>=', $today);
                    });
                };

                $itemPrice = \App\Models\ItemPrice::where('item_code', $itemCode)
                    ->where(function ($q) use ($listId, $searchNames) {
                        $q->where('price_list_id', $listId);
                        foreach ($searchNames as $name) {
                            $q->orWhere('price_list_name', 'LIKE', '%' . $name . '%');
                        }
                    })
                    ->when($requestedUom, fn($q) => $q->where('uom', $requestedUom))
                    ->tap($activeDateFilter)
                    ->first();

                \Log::info('OrderPlace - Primary Lookup', [
                    'item_code' => $itemCode,
                    'price_list_id' => $listId,
                    'price_list_name' => $listName,
                    'requested_uom' => $requestedUom,
                    'found' => (bool)$itemPrice
                ]);

                // Fallback: If not found by item_code, try inventory_item_id
                if (!$itemPrice) {
                    \Log::info('OrderPlace - Attempting Fallback by inventory_item_id', ['id' => $item->inventory_item_id]);
                    $itemPrice = \App\Models\ItemPrice::where('item_id', $item->inventory_item_id)
                        ->where(function ($q) use ($listId, $searchNames) {
                            $q->where('price_list_id', $listId);
                            foreach ($searchNames as $name) {
                                $q->orWhere('price_list_name', 'LIKE', '%' . $name . '%');
                            }
                        })
                        ->when($requestedUom, fn($q) => $q->where('uom', $requestedUom))
                        ->tap($activeDateFilter)
                        ->first();
                }

                // If STILL not found, implement a City-Wide Fallback
                if (!$itemPrice) {
                    \Log::info('OrderPlace - Attempting City-Wide Fallback', ['city' => $listName]);

                    // Extract city prefix (e.g., "Karachi" or "Lahore")
                    $cityPrefix = explode('-', $listName)[0] ?? explode(' ', $listName)[0] ?? null;
                    $cityPrefix = trim($cityPrefix);

                    if ($cityPrefix) {
                        $itemPrice = \App\Models\ItemPrice::where(function($q) use ($itemCode, $item) {
                                $q->where('item_code', $itemCode)
                                  ->orWhere('item_id', $item->inventory_item_id);
                            })
                            ->where('price_list_name', 'LIKE', $cityPrefix . '%')
                            ->whereNotNull('list_price')
                            ->when($requestedUom, fn($q) => $q->where('uom', $requestedUom))
                            ->tap($activeDateFilter)
                            ->orderBy('list_price', 'desc') // Pick highest available if multiple fallbacks exist
                            ->first();

                        if ($itemPrice) {
                            \Log::info('OrderPlace - Found City Fallback Price', [
                                'original_list' => $listName,
                                'found_in_list' => $itemPrice->price_list_name,
                                'price' => $itemPrice->list_price
                            ]);
                        }
                    }
                }

                // If REALLY not found after all fallbacks, log diagnostics
                if (!$itemPrice) {
                    $availablePrices = \App\Models\ItemPrice::where('item_code', $itemCode)
                        ->orWhere('item_id', $item->inventory_item_id)
                        ->get(['price_list_id', 'price_list_name', 'list_price']);

                    \Log::error('OrderPlace - Price NOT Found. Available price lists for this item:', [
                        'item_code' => $itemCode,
                        'available_options' => $availablePrices->toArray()
                    ]);

                    throw new \Exception("Price not found for item: {$itemCode} in price list {$listId}/{$listName}. Available lists for this item: " . $availablePrices->pluck('price_list_name')->implode(', '));
                }

                \Log::info('OrderPlace - Price Found', [
                    'item' => $itemCode,
                    'price' => $itemPrice->list_price,
                    'price_list' => $itemPrice->price_list_name,
                    'uom_requested' => $requestedUom,
                    'uom_saved' => $requestedUom ?? $itemPrice->uom,
                ]);

                $hasCapPrice  = isset($itemData['cap_price']) && $itemData['cap_price'] !== null;
                $hasClientPrice = isset($itemData['price']) && $itemData['price'] !== null;

                // Use the price the mobile cart was showing (when sent) so the
                // Order Summary screen the user just confirmed matches what
                // /orders/history and /orders/details return. Falls back to
                // the server's current ItemPrice.list_price when the client
                // didn't send a price — preserves the previous behaviour for
                // older mobile builds.
                $originalPrice  = $hasClientPrice ? (float) $itemData['price'] : $itemPrice->list_price;
                $effectivePrice = $hasCapPrice ? $itemData['cap_price'] : $originalPrice;

                // Calculate the subtotal for this item
                $itemSubtotal = $effectivePrice * $itemData['quantity'];

                // Add to the total sub_total
                $subTotal += $itemSubtotal;

                // Calculate the discount for this item
                // If cap_price is sent, discount won't apply
                $itemDiscount = $hasCapPrice ? 0 : ($itemData['discount'] ?? 0);

                // Ensure the item discount doesn't exceed the item subtotal
                if ($itemDiscount > $itemSubtotal) {
                    $itemDiscount = $itemSubtotal;
                }

                // Add to the total item discount
                $totalItemDiscount += $itemDiscount;

                // Create the OrderItem
                $order->orderItems()->create([
                    'inventory_item_id' => $itemData['inventory_item_id'],
                    'uom' => $requestedUom ?? $itemPrice->uom,
                    'quantity' => $itemData['quantity'],
                    'price' => $originalPrice,
                    'cap_price' => $hasCapPrice ? $itemData['cap_price'] : null,
                    'sub_total' => $itemSubtotal - $itemDiscount,
                    'discount' => $itemDiscount,
                ]);

                // Remember this paid line so the promotional-scheme service can
                // decide whether it triggers any "Buy N get M free" rule.
                $paidLinesForPromo[] = [
                    'item_code' => $itemCode,
                    'quantity'  => (int) $itemData['quantity'],
                    'uom'       => $requestedUom ?? $itemPrice->uom,
                ];
            }

            // Apply promotional schemes (Buy N get M free). Free items are added
            // as order_items with is_free=true / price=0, so they don't affect
            // subtotal or discount math below.
            try {
                app(\App\Services\PromotionalSchemeService::class)->applyToOrder($order, $paidLinesForPromo);
            } catch (\Throwable $e) {
                \Log::warning('Promo scheme application failed (order still placed)', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
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

            // Push notification to supply-chain team. Done OUTSIDE the transaction
            // so a notification failure can never roll back the order.
            app(\App\Services\OrderReceiptNotifier::class)->orderCreated($order);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order placed successfully.',
                // Don't eager-load orderItems.item.itemPrice — it returns the
                // current list_price (an arbitrary one when multiple rows exist
                // for the same item_code) instead of the price actually charged.
                // The order line's own `price`/`sub_total` columns are the
                // source of truth and are already present on $order->orderItems.
                'data' => [$order->load(['customer:customer_id,customer_name,customer_number', 'salesperson:id,name', 'orderItems.item:id,inventory_item_id,item_code,item_description'])],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Failed to place order.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Rebook (clone) an existing order as a brand-new order for the same
     * customer. The new order goes through the same placement pipeline as
     * /place-order — current ItemPrice lookups, customer price-list mapping,
     * promotional-scheme application, OracleOrderSync notifications, etc. —
     * so the rebooked order reflects today's prices, not the snapshot from
     * the source order. Promo-added free lines on the source are skipped
     * (the scheme service re-evaluates them on the new order).
     */
    public function orderRebook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|exists:orders,order_number',
        ]);

        $source = Order::with([
                'customer:id,customer_id',
                'orderItems:id,order_id,inventory_item_id,uom,quantity,discount,cap_price,is_free',
            ])
            ->where('order_number', $validated['order_number'])
            ->first();

        if (! $source) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Source order not found.',
            ], 404);
        }
        if (! $source->customer) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Source order has no customer attached.',
            ], 400);
        }

        // Skip promo-added free lines — the PromotionalSchemeService re-runs
        // on the new order and will add fresh free items if the rules still
        // apply at today's quantities. Carrying the old freebies forward
        // would either double-apply them or stamp prices the scheme didn't
        // intend.
        $items = $source->orderItems
            ->reject(fn ($l) => (bool) ($l->is_free ?? false))
            ->map(fn ($l) => [
                'inventory_item_id' => $l->inventory_item_id,
                'quantity'          => (float) $l->quantity,
                'uom'               => $l->uom,
                'discount'          => (float) ($l->discount ?? 0),
                'cap_price'         => $l->cap_price !== null ? (float) $l->cap_price : null,
            ])
            ->values()
            ->all();

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Source order has no rebookable items.',
            ], 400);
        }

        $payload = [
            'customer_id'    => $source->customer->customer_id,
            'items'          => $items,
            'transporter_id' => $source->transporter_id,
            'remarks'        => $source->remarks,
        ];

        // Build a fresh Request carrying the rebook payload and swap it in
        // as the bound app('request') for the duration of the placement
        // call. orderPlace reads UOM fallbacks via the global request()
        // helper, so swapping is necessary — passing the new request as the
        // argument alone is not enough. The original request is restored in
        // the finally block even if placement throws.
        $newRequest = Request::create('/api/orders/place-order', 'POST', $payload);
        $newRequest->headers->set('Accept', 'application/json');
        $newRequest->setUserResolver(fn () => $request->user());

        $originalRequest = app('request');
        app()->instance('request', $newRequest);
        try {
            $response = $this->orderPlace($newRequest);
        } finally {
            app()->instance('request', $originalRequest);
        }

        // Tag the response so the mobile knows this was a rebook (e.g. to
        // surface "Rebooked from #20261500" on the confirmation screen).
        $payload = $response->getData(true);
        $payload['rebooked_from'] = $source->order_number;
        return response()->json($payload, $response->getStatusCode());
    }

    /**
     * Retrieve the order history for the authenticated user.
     */
    public function orderHistory(Request $request): JsonResponse
    {
        // Get the currently authenticated user
        $user = $request->user();

        // Validate the incoming request filters + pagination params.
        //   range:    optional named shortcut (today | yesterday | last_7_days |
        //             last_30_days | this_month | last_month). When sent it
        //             OVERRIDES start_date/end_date so the mobile can stop
        //             computing dates client-side and let the server build
        //             the correct PKT day boundaries.
        //   page:     1-based page index (default 1).
        //   per_page: rows per page, capped at 100 (default 10).
        $validated = $request->validate([
            'start_date'     => 'nullable|date_format:d/m/Y',
            'end_date'       => 'nullable|date_format:d/m/Y',
            'customer_id'    => 'nullable|exists:customers,customer_id',
            'salesperson_id' => 'nullable|exists:users,id',
            'range'          => 'nullable|string|in:today,yesterday,last_7_days,last_30_days,this_month,last_month',
            'page'           => 'nullable|integer|min:1',
            'per_page'       => 'nullable|integer|min:1|max:100',
            'status'         => 'nullable|string',
        ]);

        if (! $user) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'User is not authenticated.',
            ], 401);
        }

        // Resolve the named range into explicit start_date / end_date strings
        // (d/m/Y) so applyFilters() can keep its single date-parsing path.
        // Server uses the app timezone (Asia/Karachi) so "today" / "yesterday"
        // are PKT-correct regardless of the user's device clock or DST.
        $tz = config('app.timezone', 'Asia/Karachi');
        $rangeKey = $validated['range'] ?? null;
        if ($rangeKey !== null) {
            $now = now($tz);
            [$rangeStart, $rangeEnd] = match ($rangeKey) {
                'today'        => [$now->copy()->startOfDay(),       $now->copy()->endOfDay()],
                'yesterday'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                'last_7_days'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
                'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
                'this_month'   => [$now->copy()->startOfMonth(),     $now->copy()->endOfDay()],
                'last_month'   => [$now->copy()->subMonthNoOverflow()->startOfMonth(),
                                    $now->copy()->subMonthNoOverflow()->endOfMonth()],
            };
            $validated['start_date'] = $rangeStart->format('d/m/Y');
            $validated['end_date']   = $rangeEnd->format('d/m/Y');
        }

        $page    = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        // Log every incoming /orders/history call so we can see exactly which
        // filters the mobile is sending (or NOT sending) when the response
        // looks wrong. Includes the raw request payload, the validated
        // subset that actually drives the query, and the auth context so we
        // can correlate with the user / role-based restriction below.
        \Log::info('OrderHistory - Request received', [
            'user_id'    => $user->id,
            'user_name'  => $user->name,
            'user_role'  => $user->role,
            'is_admin'   => $user->isAdmin(),
            'raw_input'  => $request->all(),
            'validated'  => $validated,
            'has_start'  => !empty($validated['start_date']),
            'has_end'    => !empty($validated['end_date']),
            'has_cust'   => !empty($validated['customer_id']),
            'has_sp'     => !empty($validated['salesperson_id']),
            'range'      => $rangeKey,
            'page'       => $page,
            'per_page'   => $perPage,
            'server_now' => now()->toDateTimeString(),
            'server_tz'  => $tz,
        ]);

        // Apply filters via the shared action (date range + customer + salesperson
        // + role-based restriction). UTC-aware date comparison lives there.
        $orderExportAction = new OrderExportAction;
        $ordersQuery = $orderExportAction->applyFilters($validated);

        // Optional status filter — mobile sends the OrderStatus enum *name*
        // (e.g. "pending", "synced"). Filtering happens on the underlying
        // column so we don't have to materialise + filter in PHP.
        if (! empty($validated['status'])) {
            $ordersQuery->where('order_status', $validated['status']);
        }

        // Eager-loads, columns, ordering. NOTE: no manual limit() — paginate()
        // applies the per_page cap and gives us COUNT(*) for the totals.
        $ordersQuery->with([
                'customer:id,customer_id,customer_name,area,city',
                'salesperson:id,name',
            ])
            ->select('id', 'order_number', 'customer_id', 'user_id', 'order_status', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
            ->latest();

        // Cache key — includes page + per_page so each page caches separately.
        // Bumped to _v4 when customer_city was added to the row shape so
        // in-flight v3 cached payloads (without the field) don't leak.
        $cacheKey = sprintf(
            'user_order_history_v4_%s_p%d_pp%d_%s',
            $user->id,
            $page,
            $perPage,
            md5(json_encode($validated)),
        );
        $cacheTime = 60;

        $cached = Cache::remember($cacheKey, $cacheTime, function () use ($ordersQuery, $page, $perPage, $tz) {
            $paginator = $ordersQuery->paginate(perPage: $perPage, page: $page);

            $items = collect($paginator->items())->map(function ($order) use ($tz) {
                return [
                    'order_number'    => $order->order_number,
                    'customer_id'     => $order->customer_id,
                    'customer_name'   => $order->customer->customer_name ?? null,
                    'customer_area'   => $order->customer->area ?? null,
                    'customer_city'   => $order->customer->city ?? null,
                    'user_id'         => $order->user_id,
                    'salesperson_name'=> $order->salesperson->name ?? null,
                    'order_status'    => $order->order_status->name(),
                    'sub_total'       => $order->sub_total,
                    'discount'        => $order->discount,
                    'total_amount'    => $order->total_amount,
                    'created_at'      => $order->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
                    'updated_at'      => $order->updated_at?->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
                ];
            })->values();

            return [
                'items'      => $items,
                'pagination' => [
                    'current_page'   => $paginator->currentPage(),
                    'per_page'       => $paginator->perPage(),
                    'total'          => $paginator->total(),
                    'last_page'      => $paginator->lastPage(),
                    'from'           => $paginator->firstItem(),
                    'to'             => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ];
        });

        return response()->json([
            'success'    => true,
            'status'     => 200,
            'message'    => 'Order history retrieved successfully.',
            'data'       => $cached['items'],
            'pagination' => $cached['pagination'],
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

        // Cache-key suffix `_v2` invalidates older cached payloads that were
        // built before the Asia/Karachi-formatted timestamps were added.
        // _v6 invalidates payloads cached by the short-lived v5 shape that
        // changed several field types from decimal-string to float and added
        // gross_total / item_discount / order_level_discount. Those type
        // changes broke the mobile parser, so the response is back to the
        // original v4 shape — only customer_number / customer_site_id and
        // the historical item_price fix are kept.
        // Bumped to v7 when customer_city was added to the payload so
        // in-flight v6 cached rows (without the field) don't leak.
        $cacheKey = 'order_details_v7_' . $validated['order_number'];
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $order = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            return Order::with([
                'customer:id,customer_id,customer_number,customer_site_id,customer_name,contact_number,area,city',
                'salesperson:id,name',
                'transporter:id,value,description',
                'orderItems:id,order_id,inventory_item_id,uom,quantity,price,cap_price,discount,sub_total',
                'orderItems.item:id,inventory_item_id,item_code,item_description',
            ])
                ->select('id', 'order_number', 'customer_id', 'user_id', 'transporter_id', 'order_status', 'remarks', 'sub_total', 'discount', 'total_amount', 'created_at', 'updated_at')
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

        // Send timestamps in Asia/Karachi as plain "Y-m-d H:i:s" instead of
        // the default Carbon JSON serialization (UTC ISO 8601 with microseconds
        // and a "Z" suffix). Matches the format used by orderHistory.
        $tz = config('app.timezone', 'Asia/Karachi');

        // Transform the order data
        $orderDetails = [
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'customer_number'  => $order->customer->customer_number  ?? null,
            'customer_site_id' => $order->customer->customer_site_id ?? null,
            'customer_name' => $order->customer->customer_name ?? null,
            'customer_area' => $order->customer->area ?? null,
            'customer_city' => $order->customer->city ?? null,
            'contact_number' => $order->customer->contact_number ?? null,
            'user_id' => $order->user_id,
            'salesperson_name' => $order->salesperson->name ?? null,
            'transporter_id' => $order->transporter_id,
            'transporter_name' => $order->transporter->description ?? null,
            'transporter_value' => $order->transporter->value ?? null,
            'remarks' => $order->remarks,
            'order_status' => $order->order_status->name(),
            'sub_total' => $order->sub_total,
            'discount' => $order->discount,
            'total_amount' => $order->total_amount,
            'created_at' => $order->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
            'updated_at' => $order->updated_at?->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
            'order_items' => $order->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'uom' => $item->uom,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'cap_price' => $item->cap_price,
                    'discount' => $item->discount,
                    'sub_total' => $item->sub_total,
                    'item' => [
                        'inventory_item_id' => $item->item->inventory_item_id,
                        'item_code' => $item->item->item_code,
                        'item_description' => $item->item->item_description,
                        // Use the historical price stored on this order line, not
                        // the current ItemPrice.list_price. The Item::itemPrice
                        // relation returns an arbitrary current row (one item has
                        // many ItemPrice rows — one per price-list × UOM combo),
                        // which makes item.item_price × quantity − discount drift
                        // away from sub_total and breaks the mobile's order math.
                        'item_price' => $item->price,
                        'item_uom'   => $item->uom,
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
        $orders = $orderExportAction->applyFilters($validated)->get();

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

    /**
     * Cancel orders based on given order number.
     */
    public function cancelOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string',
            'reason'       => 'required|string|min:3|max:1000',
        ]);

        $order = Order::withTrashed()->where('order_number', $validated['order_number'])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Order not found.',
            ], 404);
        }

        if ($order->order_status == OrderStatusEnum::CANCELED) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Order is already cancelled.',
            ], 400);
        }

        if ($order->oracle_at !== null) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'This order has already been pushed to Oracle and cannot be cancelled here.',
            ], 400);
        }

        $userId = auth()->id();

        DB::transaction(function () use ($order, $validated, $userId) {
            // Cancellation is now state-on-the-row (order_status = CANCELED +
            // cancellation_reason / cancelled_at / cancelled_by). We do NOT
            // soft-delete the order — otherwise it disappears from the default
            // listing and the salesperson sees a misleading "still pending"
            // state instead of an explicit cancelled badge.
            $order->fill([
                'order_status'        => OrderStatusEnum::CANCELED,
                'cancellation_reason' => $validated['reason'],
                'cancelled_at'        => now(),
                'cancelled_by'        => $userId,
            ])->save();
        });

        // Notify everyone affected (salesperson + supply-chain + admin in same OU).
        try {
            app(\App\Services\OrderReceiptNotifier::class)->orderCancelled($order, $validated['reason']);
        } catch (\Throwable $e) {
            \Log::warning('orderCancelled notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Order has been cancelled.',
            'data'    => [
                'order_number'        => $order->order_number,
                'cancellation_reason' => $order->cancellation_reason,
                'cancelled_at'        => $order->cancelled_at,
            ],
        ], 200);
    }
    
    
}
