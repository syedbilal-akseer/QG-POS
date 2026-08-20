<div class="w-full space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">POS</span>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Point of Sale</h1>
            @if ($customer)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Selling as <span class="font-bold text-gray-700 dark:text-gray-300">{{ $customer['customer_name'] }}</span>
                    ({{ $customer['customer_number'] }}) — {{ $customer['price_list_name'] ?? 'no price list' }}
                </p>
            @endif
        </div>
    </div>

    @if ($noWalkinAccount)
        <div class="p-6 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
            <x-heroicon-s-exclamation-triangle class="h-6 w-6 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <p class="font-black text-amber-700 dark:text-amber-300">No walk-in account linked to your login</p>
                <p class="text-sm text-amber-600 dark:text-amber-400 mt-1">POS can't price anything until admin assigns your shop's walk-in customer account to your user. Contact your administrator.</p>
            </div>
        </div>
    @else

        @if ($lastOrderNumber)
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 flex items-center gap-3">
                <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-500 shrink-0" />
                <span class="font-black text-emerald-700 dark:text-emerald-300 text-sm">Order #{{ $lastOrderNumber }} placed successfully.</span>
            </div>
        @endif

        @if ($checkoutError)
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm font-bold">
                {{ $checkoutError }}
            </div>
        @endif

        @if (! ($customer['has_price_list'] ?? false))
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm font-bold">
                Your walk-in account has no active price list — items cannot be priced. Contact admin.
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Scan / search --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Scan Barcode</h3>
                    <div class="relative" x-data>
                        <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
                        <input type="text" wire:model="barcodeInput" wire:keydown.enter.prevent="scanBarcode"
                            x-ref="barcodeInput" x-init="$el.focus()"
                            x-on:barcode-scanned.window="$nextTick(() => $refs.barcodeInput.focus())"
                            placeholder="Scan item barcode..."
                            class="w-full h-11 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
                    </div>
                    @if ($scanError)
                        <p class="mt-2 text-xs font-bold text-rose-500">{{ $scanError }}</p>
                    @elseif ($scanInfo)
                        <p class="mt-2 text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ $scanInfo }}</p>
                    @endif
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Or Search Item</h3>
                    <input type="text" wire:model.live.debounce.300ms="itemSearch"
                        placeholder="Item code or description..."
                        class="w-full h-10 px-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:border-primary-500" />

                    @if (count($itemResults))
                        <div class="mt-3 max-h-64 overflow-y-auto space-y-1">
                            @foreach ($itemResults as $item)
                                <button type="button" wire:click="addToCart({{ $item['inventory_item_id'] }})"
                                    class="w-full text-left px-3 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 border border-transparent hover:border-primary-200 dark:hover:border-primary-800">
                                    <p class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $item['item_code'] }}</p>
                                    <p class="text-[10px] text-gray-400 truncate">{{ $item['item_description'] }}</p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Cart --}}
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Cart</h3>
                    <span class="text-[10px] font-black uppercase text-gray-400">{{ count($cart) }} item(s)</span>
                </div>

                @if (empty($cart))
                    <div class="flex flex-col items-center justify-center py-16 opacity-40">
                        <x-heroicon-o-shopping-cart class="h-10 w-10 text-gray-400 mb-3" />
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Scan or search an item to begin</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($cart as $inventoryItemId => $line)
                            <div class="px-6 py-3 flex items-center gap-4">
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-sm text-gray-800 dark:text-gray-200 truncate">{{ $line['item_description'] }}</p>
                                    <p class="text-[10px] text-gray-400 font-mono">{{ $line['item_code'] }} · {{ $line['uom'] }} · Rs {{ number_format($line['unit_price'], 2) }}</p>
                                </div>
                                <input type="number" min="0" step="1"
                                    wire:change="updateQuantity({{ $inventoryItemId }}, $event.target.value)"
                                    value="{{ rtrim(rtrim(number_format($line['quantity'], 3, '.', ''), '0'), '.') ?: '0' }}"
                                    class="w-20 h-9 px-2 text-center bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-bold" />
                                <span class="w-24 text-right font-black text-sm text-gray-800 dark:text-white">Rs {{ number_format($line['line_total'], 2) }}</span>
                                <button wire:click="removeFromCart({{ $inventoryItemId }})" class="text-gray-300 hover:text-rose-500">
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-gray-400">Total</span>
                            <span class="text-xl font-black text-gray-900 dark:text-white">Rs {{ number_format($this->subTotal, 2) }}</span>
                        </div>

                        <input type="text" wire:model="remarks" placeholder="Remarks (optional)"
                            class="w-full h-9 px-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-xs" />

                        <button wire:click="checkout" wire:loading.attr="disabled"
                            class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-sm uppercase tracking-wider transition-colors">
                            Checkout
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
