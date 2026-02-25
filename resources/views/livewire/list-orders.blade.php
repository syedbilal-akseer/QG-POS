@push('title')
    {{ $pageTitle ?? 'Default Title' }}
@endpush
<div>
    <div class="mb-10">
        @livewire(App\Livewire\Widgets\OrderStatsOverview::class)
    </div>

    {{ $this->table }}

    @if ($order)
        <x-modal name="order_detail" :show="true" focusable maxWidth="4xl">
            <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-lg">
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

                <div class="mt-6 space-y-6">
                    <!-- Order Information -->
                    <div class="flex items-start">
                        <!-- Customer Information -->
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer:</h3>
                            <p class="text-lg text-gray-900 dark:text-gray-100">{{ $order->customer->customer_name }}
                            </p>
                        </div>

                        <!-- Status and Date Information -->
                        <div class="flex items-end ml-6">
                            <div x-data x-show="{{ auth()->user()->isAdmin() }}" class="text-left me-4">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Sale Person:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100">{{ $order->salesperson->name }}
                                </p>
                            </div>
                            <div class="text-left me-4">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100">{{ $order->order_status->name() }}
                                </p>
                            </div>
                            <div class="text-left">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Order Date:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100">
                                    {{ $order->created_at->format('F j, Y, g:i a') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items Table -->
                    <div class="mt-4">
                        <table class="min-w-full border border-gray-200 dark:border-neutral-700 rounded-md">
                            <thead class="bg-gray-50 dark:bg-neutral-900">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Item Code
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Item
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Quantity
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        UOM
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Price
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Discount
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Subtotal
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Warehouse
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white dark:bg-neutral-800 divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($order->orderItems as $index => $orderItem)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->item->item_code }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->item->item_description }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->quantity }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $orderItem->uom }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                            Rs{{ number_format($orderItem->price, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                            Rs{{ number_format($orderItem->discount, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                            Rs{{ number_format($orderItem->sub_total, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            <div wire:loading.class="opacity-50 pointer-events-none"
                                                wire:target="enterOrderToOracle">
                                                <x-select name="orderItemWarehouses.{{ $index }}"
                                                    wire:model.defer="orderItemWarehouses.{{ $index }}"
                                                    class="mt-1 !w-[100px]"
                                                    :disabled="$order->oracle_at ? true : false" :options="$warehouses" />
                                            </div>
                                            @error('orderItemWarehouses.' . $index)
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
                class="flex justify-end items-center gap-x-4 py-4 px-6 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
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
        <x-modal name="order_sync_details" :show="true" maxWidth="4xl">
            <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-lg">
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
                            <div x-data x-show="{{ auth()->user()->isAdmin() }}" class="text-left me-4">
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
