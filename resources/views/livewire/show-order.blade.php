@php
    // `use` is not allowed inside @php blocks (Blade compiles them into
    // function bodies); reference OrderStatusEnum by FQN throughout.
    $statusColors = [
        'pending'    => ['bg' => '#fef3c7', 'fg' => '#92400e'],
        'processing' => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
        'completed'  => ['bg' => '#d1fae5', 'fg' => '#065f46'],
        'canceled'   => ['bg' => '#fee2e2', 'fg' => '#991b1b'],
        'synced'     => ['bg' => '#d1fae5', 'fg' => '#065f46'],
        'entered'    => ['bg' => '#d1fae5', 'fg' => '#065f46'],
    ];
    $statusValue = $order->order_status instanceof \App\Enums\OrderStatusEnum
        ? $order->order_status->value
        : (string) $order->order_status;
    $statusColor = $statusColors[$statusValue] ?? ['bg' => '#e5e7eb', 'fg' => '#374151'];
    $isPushed    = $order->oracle_at !== null;
    $isCancelled = $order->order_status instanceof \App\Enums\OrderStatusEnum
        && $order->order_status === \App\Enums\OrderStatusEnum::CANCELED;
    $canPush     = !$isCancelled && !$isPushed && !auth()->user()->isSalesHead();
@endphp

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <a href="{{ route('orders.all') }}"
                   class="text-sm text-gray-600 dark:text-gray-300 hover:underline whitespace-nowrap">
                    <i class="fas fa-arrow-left mr-1"></i>Back to orders
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Order #{{ $order->order_number }}
                </h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
                      style="background:{{ $statusColor['bg'] }};color:{{ $statusColor['fg'] }};">
                    {{ $order->order_status instanceof \App\Enums\OrderStatusEnum ? $order->order_status->name() : ucfirst($statusValue) }}
                </span>
                @if($isPushed)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-green-100 text-green-800 border border-green-200">
                        <i class="fas fa-check-circle mr-1"></i>Pushed to Oracle
                    </span>
                @endif
            </div>
        </div>

        {{-- Cancellation banner --}}
        @if ($isCancelled)
            <div class="flex items-start gap-3 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mt-0.5"></i>
                <div class="flex-1">
                    <div class="text-xs font-bold uppercase tracking-wider text-red-600 dark:text-red-400">Order Cancelled</div>
                    <p class="text-sm text-red-900 dark:text-red-100 mt-0.5">{{ $order->cancellation_reason ?: 'No reason provided.' }}</p>
                    <p class="text-xs text-red-700 dark:text-red-300 mt-1">
                        @if ($order->cancelledBy)
                            Cancelled by <span class="font-semibold">{{ $order->cancelledBy->name }}</span>
                        @else
                            Cancelled
                        @endif
                        @if ($order->cancelled_at)
                            on {{ $order->cancelled_at->format('M d, Y, g:i a') }}
                        @endif
                    </p>
                </div>
            </div>
        @endif

        {{-- Top summary cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Customer</div>
                <div class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $order->customer?->customer_name ?? '—' }}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Code: {{ $order->customer?->customer_id ?? '—' }}</div>
            </div>
            @if(auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr() || auth()->user()->isScmLhr() || auth()->user()->isSalesHead())
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Salesperson</div>
                    <div class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $order->salesperson?->name ?? '—' }}</div>
                </div>
            @endif
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Order Date</div>
                <div class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">{{ optional($order->created_at)->format('M d, Y') }}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ optional($order->created_at)->format('g:i a') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Pushed to Oracle</div>
                @if($isPushed)
                    <div class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">{{ optional($order->oracle_at)->format('M d, Y g:i a') }}</div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">by {{ $order->pushedBy?->name ?? '—' }}</div>
                @else
                    <div class="mt-1 text-sm text-gray-400 italic">Not yet pushed</div>
                @endif
            </div>
        </div>

        {{-- Transporter + remarks --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @if ($order->transporter)
                <div class="flex items-start gap-3 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <i class="fas fa-truck text-blue-600 dark:text-blue-400 mt-1"></i>
                    <div>
                        <div class="text-xs uppercase tracking-wide font-medium text-blue-600 dark:text-blue-400">Transporter</div>
                        <div class="text-base font-semibold text-blue-900 dark:text-blue-100">
                            {{ $order->transporter->description }}
                            <span class="ml-2 text-sm font-normal text-blue-700 dark:text-blue-300">({{ $order->transporter->value }})</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-3 px-4 py-3 bg-gray-50 dark:bg-neutral-900 border border-gray-200 dark:border-neutral-700 rounded-lg">
                    <i class="fas fa-truck text-gray-400 mt-1"></i>
                    <div>
                        <div class="text-xs uppercase tracking-wide font-medium text-gray-500 dark:text-gray-400">Transporter</div>
                        <div class="text-sm text-gray-400 italic">No transporter selected</div>
                    </div>
                </div>
            @endif

            @if ($order->remarks)
                <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <i class="fas fa-comment-dots text-amber-600 dark:text-amber-400 mt-1"></i>
                    <div>
                        <div class="text-xs uppercase tracking-wide font-medium text-amber-600 dark:text-amber-400">Remarks</div>
                        <p class="text-sm text-amber-900 dark:text-amber-100">{{ $order->remarks }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Order Items table — full page, includes warehouse selector --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Order Items ({{ $order->orderItems->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">#</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Item Code</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Description</th>
                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Qty</th>
                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">UOM</th>
                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Price</th>
                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Cap Price</th>
                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Discount</th>
                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Subtotal</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider min-w-[12rem]">Warehouse</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @forelse($order->orderItems as $i => $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-3 py-2 text-xs font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $item->item?->item_code ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-200">{{ $item->item?->item_description ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-center text-gray-900 dark:text-gray-100">{{ $item->quantity }}</td>
                                <td class="px-3 py-2 text-xs text-center text-gray-600 dark:text-gray-400">{{ $item->uom }}</td>
                                <td class="px-3 py-2 text-xs text-right text-gray-900 dark:text-gray-100">{{ number_format((float) $item->price, 2) }}</td>
                                <td class="px-3 py-2 text-xs text-right">
                                    @if($item->cap_price)
                                        <span class="text-blue-600 dark:text-blue-400 font-semibold">{{ number_format((float) $item->cap_price, 2) }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-right text-gray-600 dark:text-gray-400">{{ number_format((float) $item->discount, 2) }}</td>
                                <td class="px-3 py-2 text-xs text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $item->sub_total, 2) }}</td>
                                <td class="px-3 py-2 text-xs">
                                    <select wire:model.defer="orderItemWarehouses.{{ $i }}"
                                            @disabled($isPushed || $isCancelled || auth()->user()->isSalesHead())
                                            class="text-[11px] py-1 px-2 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 w-full disabled:opacity-50 disabled:cursor-not-allowed">
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh['value'] }}" {{ ($orderItemWarehouses[$i] ?? '') == $wh['value'] ? 'selected' : '' }}>
                                                {{ $wh['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No line items on this order.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($order->orderItems->isNotEmpty())
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="8" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Subtotal</td>
                                <td class="px-3 py-2 text-right text-sm font-bold text-gray-900 dark:text-gray-100">Rs {{ number_format((float) $order->sub_total, 2) }}</td>
                                <td></td>
                            </tr>
                            @if($order->discount > 0)
                                <tr>
                                    <td colspan="8" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Discount</td>
                                    <td class="px-3 py-2 text-right text-sm font-bold text-red-600 dark:text-red-400">- Rs {{ number_format((float) $order->discount, 2) }}</td>
                                    <td></td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="8" class="px-3 py-2 text-right text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-100">Total</td>
                                <td class="px-3 py-2 text-right text-base font-bold text-gray-900 dark:text-gray-100">Rs {{ number_format((float) ($order->total_amount ?? ($order->sub_total - $order->discount)), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Action bar — mirrors the modal's footer conditions exactly. --}}
        <div class="flex justify-end items-center gap-3 pt-2">
            @if ($isCancelled)
                <span class="text-sm text-red-600 dark:text-red-400 italic">
                    Order cancelled — push to Oracle is disabled.
                </span>
            @elseif ($isPushed)
                <button type="button" disabled
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold bg-gray-300 dark:bg-gray-600 text-gray-500 cursor-not-allowed">
                    <i class="fas fa-check-circle mr-2"></i>Already Entered to Oracle
                </button>
            @elseif (auth()->user()->isSalesHead())
                <span class="text-sm text-gray-500 dark:text-gray-400 italic">
                    View-only role — push to Oracle is disabled.
                </span>
            @else
                <button type="button" wire:click="enterOrderToOracle" wire:loading.attr="disabled" wire:target="enterOrderToOracle"
                        style="background-color:#ea580c;color:#fff;"
                        onmouseover="if(!this.disabled) this.style.backgroundColor='#c2410c'"
                        onmouseout="if(!this.disabled) this.style.backgroundColor='#ea580c'"
                        class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="enterOrderToOracle">
                        <i class="fas fa-cloud-upload-alt mr-2" style="color:#fff;"></i>Enter to Oracle
                    </span>
                    <span wire:loading wire:target="enterOrderToOracle" class="flex items-center">
                        <svg class="animate-spin mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sending to Oracle…
                    </span>
                </button>
            @endif
        </div>

        @error('orderItemWarehouses.*')
            <div class="text-right text-xs text-red-600 dark:text-red-400">{{ $message }}</div>
        @enderror
    </div>
</div>
