@push('title')
    {{ $pageTitle ?? 'Default Title' }}
@endpush
<div>
    <div class="mb-10">
        @livewire(App\Livewire\Widgets\OrderStatsOverview::class)
    </div>

    {{ $this->table }}

    @if ($order)
        <x-modal name="order_detail" :show="true" focusable maxWidth="5xl">
            <div class="sticky top-0 z-20 bg-white dark:bg-gray-800 px-4 md:px-6 pt-4 md:pt-6">
                <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Order Details
                        #{{ $order->order_number }}</h2>
                    <span x-on:click="$dispatch('close')"
                        class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="px-4 md:px-6 pb-4 md:pb-6">
                <div class="mt-6 space-y-6">
                    <!-- Order Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Customer Information -->
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Customer:</h3>
                            <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $order->customer->customer_name }}</p>
                        </div>

                        <!-- Sale Person -->
                        @if(auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr() || auth()->user()->isScmLhr())
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sale Person:</h3>
                            <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $order->salesperson->name }}</p>
                        </div>
                        @endif

                        <!-- Status -->
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status:</h3>
                            <p class="mt-1">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                    {{ $order->order_status->name() }}
                                </span>
                            </p>
                        </div>

                        <!-- Order Date -->
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Order Date:</h3>
                            <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ $order->created_at->format('M d, Y, g:i a') }}
                            </p>
                        </div>
                    </div>

                    <!-- Transporter Information -->
                    @if ($order->transporter)
                        <div class="flex items-center gap-3 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            <div>
                                <span class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wide">Transport Company (Select on Oracle)</span>
                                <p class="text-base font-semibold text-blue-900 dark:text-blue-100">
                                    {{ $order->transporter->description }}
                                    <span class="ml-2 text-sm font-normal text-blue-700 dark:text-blue-300">({{ $order->transporter->value }})</span>
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-neutral-900 border border-gray-200 dark:border-neutral-700 rounded-lg">
                            <svg class="w-5 h-5 text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Transport Company (Select on Oracle)</span>
                                <p class="text-sm text-gray-400 italic">No transporter selected</p>
                            </div>
                        </div>
                    @endif

                    <!-- Remarks -->
                    @if ($order->remarks)
                        <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                            </svg>
                            <div>
                                <span class="text-xs font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wide">Remarks</span>
                                <p class="text-sm text-amber-900 dark:text-amber-100 mt-0.5">{{ $order->remarks }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Order Items Table -->
                    <div class="mt-4 border border-gray-200 dark:border-neutral-700 rounded-lg overflow-hidden">
                        <div style="max-height: 220px; overflow: auto;">
                            <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
                                <colgroup>
                                    <col style="width: 130px;">
                                    <col>
                                    <col style="width: 55px;">
                                    <col style="width: 55px;">
                                    <col style="width: 80px;">
                                    <col style="width: 80px;">
                                    <col style="width: 75px;">
                                    <col style="width: 95px;">
                                    <col style="width: 140px;">
                                </colgroup>
                                <thead class="bg-gray-50 dark:bg-neutral-900 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Item Code</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Description</th>
                                        <th class="px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Qty</th>
                                        <th class="px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">UOM</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Price</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Cap Price</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Discount</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Subtotal</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Warehouse</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-neutral-800 divide-y divide-gray-100 dark:divide-neutral-700">
                                    @foreach ($order->orderItems as $index => $orderItem)
                                        <tr style="height: 40px;">
                                            <td class="px-3 text-[11px] font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                                {{ $orderItem->item->item_code }}
                                            </td>
                                            <td class="px-3 text-[11px] text-gray-700 dark:text-gray-300" style="max-width: 0;">
                                                <div class="truncate" title="{{ $orderItem->item->item_description }}">
                                                    {{ $orderItem->item->item_description }}
                                                </div>
                                            </td>
                                            <td class="px-3 text-[11px] text-center text-gray-900 dark:text-gray-100">
                                                {{ $orderItem->quantity }}
                                            </td>
                                            <td class="px-3 text-[11px] text-center text-gray-600 dark:text-gray-400">
                                                {{ $orderItem->uom }}
                                            </td>
                                            <td class="px-3 text-[11px] text-right text-gray-900 dark:text-gray-100">
                                                {{ number_format($orderItem->price, 2) }}
                                            </td>
                                            <td class="px-3 text-[11px] text-right">
                                                @if($orderItem->cap_price)
                                                    <span class="text-blue-600 dark:text-blue-400 font-bold">
                                                        {{ number_format($orderItem->cap_price, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 text-[11px] text-right text-gray-600 dark:text-gray-400">
                                                {{ number_format($orderItem->discount, 2) }}
                                            </td>
                                            <td class="px-3 text-[11px] text-right font-bold text-gray-900 dark:text-gray-100">
                                                {{ number_format($orderItem->sub_total, 2) }}
                                            </td>
                                            <td class="px-3 text-[11px]">
                                                <select
                                                    wire:model.defer="orderItemWarehouses.{{ $index }}"
                                                    @disabled($order->oracle_at ? true : false)
                                                    class="text-[10px] py-0.5 px-1 rounded border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 w-full disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    @foreach($warehouses as $warehouse)
                                                        <option value="{{ $warehouse['value'] }}" {{ ($orderItemWarehouses[$index] ?? '') == $warehouse['value'] ? 'selected' : '' }}>
                                                            {{ $warehouse['label'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Order Totals including Subtotal and Discount -->
                    <div class="flex justify-end mt-6">
                        <div class="text-right">
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Subtotal: Rs{{ number_format($order->sub_total, 2) }}
                            </p>
                            @if ($order->discount > 0)
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Discount: -Rs{{ number_format($order->discount, 2) }}
                                </p>
                            @endif
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Total: Rs{{ number_format($order->total_amount, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="sticky bottom-0 z-20 flex justify-end items-center gap-x-4 py-4 px-6 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
                <x-secondary-button x-on:click="$dispatch('close');"
                    class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800 transition duration-150 ease-in-out">
                    {{ __('Cancel') }}
                </x-secondary-button>
                @if (!$order->oracle_at)
                    <x-primary-button wire:click="enterOrderToOracle" wire:loading.attr="disabled"
                        wire:target="enterOrderToOracle"
                        class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="enterOrderToOracle">
                            {{ __('Enter to Oracle') }}
                        </span>
                        <span wire:loading wire:target="enterOrderToOracle" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            {{ __('Sending to Oracle...') }}
                        </span>
                    </x-primary-button>
                @else
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Order Already Entered to Oracle') }}
                    </span>
                @endif
            </div>
        </x-modal>
    @endif

    @if ($orderDetails)
        <x-modal name="order_sync_details" :show="true" maxWidth="5xl">
            <div class="p-4 md:p-6">
                <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Order Sync Details - #{{ $orderDetails->order_number }}
                    </h2>
                    <span x-on:click="$dispatch('close')"
                        class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>

                <div class="mt-6 space-y-6">
                    <!-- Order Information -->
                    <div class="flex items-start">
                        <!-- Customer Information -->
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer:</h3>
                            <p class="text-lg text-gray-900 dark:text-gray-100">
                                {{ $orderDetails->customer->customer_name }}
                            </p>
                        </div>

                        <!-- Status and Date Information -->
                        <div class="flex items-end ml-6">
                            <div x-data x-show="{{ auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr() || auth()->user()->isScmLhr() ? 'true' : 'false' }}" class="text-left me-4">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Sale Person:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100">
                                    {{ $orderDetails->salesperson->name }}
                                </p>
                            </div>
                            <div class="text-left me-4">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100">
                                    {{ $orderDetails->order_status->name() }}
                                </p>
                            </div>
                            <div class="text-left">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Order Date:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100">
                                    {{ $orderDetails->created_at->format('F j, Y, g:i a') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sync History Table -->
                    <div class="overflow-x-auto mt-4">
                        <table class="min-w-full border border-gray-200 dark:border-neutral-700 rounded-md">
                            <thead class="bg-gray-50 dark:bg-neutral-900">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Item Code</th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Original Quantity</th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        New Quantity (Oracle)</th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Difference</th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white dark:bg-neutral-800 divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($orderDetails->orderItems as $orderItem)
                                    @foreach ($orderItem->syncHistory as $sync)
                                        <tr>
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                                {{ $orderItem->item->item_code }}</td>
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                                {{ $sync->previous_quantity }}</td>
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                                {{ $sync->new_quantity }}</td>
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                                {{ $sync->new_quantity - $sync->previous_quantity }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                class="flex justify-end items-center gap-x-4 py-4 px-6 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
                <x-secondary-button x-on:click="$dispatch('close');"
                    class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800 transition duration-150 ease-in-out">
                    {{ __('Cancel') }}
                </x-secondary-button>
            </div>
        </x-modal>
    @endif

</div>
