<div>
    {{ $this->table }}

    @if ($order)
        <x-modal name="order_detail" :show="true" focusable maxWidth="4xl">
            <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-lg">
                <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Order Details #{{ $order->order_number }}</h2>
                    <button x-on:click="$dispatch('close'); @this.closeDetailModal()"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition duration-150 ease-in-out">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
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
                    <div class="overflow-x-auto mt-4">
                        <table class="min-w-full border border-gray-200 dark:border-neutral-700 rounded-md">
                            <thead class="bg-gray-50 dark:bg-neutral-900">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Item Code</th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Item</th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Quantity</th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        UOM</th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Price</th>
                                    <th
                                        class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Subtotal</th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white dark:bg-neutral-800 divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($order->orderItems as $orderItem)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->item->item_code }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->item->item_description }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->quantity }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $orderItem->uom }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                            Rs{{ number_format($orderItem->price, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                            Rs{{ number_format($orderItem->quantity * $orderItem->price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Order Totals -->
                    <div class="flex justify-end mt-6">
                        <div class="text-right">
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Total:
                                Rs{{ number_format($order->orderItems->sum(fn($item) => $item->quantity * $item->price), 2) }}
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
                <x-primary-button
                    class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                    {{ __('Fetch') }}
                </x-primary-button>
            </div>
        </x-modal>
    @endif



</div>
