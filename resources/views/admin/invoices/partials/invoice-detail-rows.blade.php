{{-- Detail rows for one (date, uploaded_by) accordion group — fetched lazily
     via InvoiceController::rowsForGroup() when the group is expanded. Markup
     mirrors what used to be inlined directly into index.blade.php's per-date
     loop; kept in its own partial so it can be rendered both up front (never,
     now) and on-demand via AJAX without duplicating this block. --}}
@forelse ($invoices as $invoice)
    <tr class="group-detail-row hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
        <td class="px-3 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10">
                    <div
                        class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 flex items-center justify-center text-gray-700 dark:text-gray-200 font-bold">
                        {{ substr($invoice->customer_code, 0, 2) }}
                    </div>
                </div>
                <div class="ml-4">
                    <div
                        class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $invoice->customer_name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Code: {{ $invoice->customer_code }}
                    </div>
                    @if ($invoice->customer_phone)
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <i
                                class="fas fa-phone mr-1"></i>{{ $invoice->customer_phone }}
                        </div>
                    @endif
                </div>
            </div>
        </td>
        <td class="px-3 py-4 align-top">
            <div class="text-sm text-gray-900 dark:text-gray-100">
                @if ($invoice->invoice_number)
                    @php
                        $numbers = collect(
                            explode(',', (string) $invoice->invoice_number),
                        )
                            ->map(fn($n) => trim($n))
                            ->filter()
                            ->values();
                        $preview = $numbers->take(2);
                        $extra = max(0, $numbers->count() - $preview->count());
                    @endphp
                    <div class="font-medium mb-2">
                        <span class="block mb-1">Invoice</span>

                        <div class="relative inline-block">
                            <div class="flex flex-wrap gap-1 items-center">
                                @forelse($preview as $num)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary-50 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200 border border-primary-100 dark:border-primary-800">
                                        #{{ $num }}
                                    </span>
                                @empty
                                    <span
                                        class="text-gray-500 dark:text-gray-400">—</span>
                                @endforelse

                                @if ($extra > 0)
                                    <button type="button"
                                        onclick="toggleInvoiceList({{ $invoice->id }})"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600">
                                        +{{ $extra }} More
                                    </button>
                                @endif
                            </div>

                            @if ($extra > 0)
                                <div id="invoice-list-{{ $invoice->id }}"
                                    class="hidden absolute left-0 mt-2 z-50 w-72 max-h-56 overflow-y-auto rounded-lg border bg-white dark:bg-gray-800 shadow-xl p-3">

                                    <div
                                        class="text-xs font-semibold text-gray-500 mb-2">
                                        Remaining Invoice Numbers
                                    </div>

                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($numbers->slice(2) as $num)
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-primary-50 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200 border border-primary-100 dark:border-primary-800">
                                                #{{ $num }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                @if ($invoice->total_amount)
                    <div class="text-gray-500 dark:text-gray-400">
                        Amount: {{ number_format($invoice->total_amount, 2) }}
                    </div>
                @endif
                @php
                    $sDate = $invoice->start_date
                        ? $invoice->start_date->format('d-M-Y')
                        : ($invoice->invoice_date
                            ? $invoice->invoice_date->format('d-M-Y')
                            : 'N/A');
                    $eDate = $invoice->end_date
                        ? $invoice->end_date->format('d-M-Y')
                        : $sDate;
                    $dateStr =
                        $sDate === $eDate ? $sDate : $sDate . ' to ' . $eDate;
                @endphp
                <div
                    class="text-xs font-bold text-blue-600 dark:text-blue-400 mt-1">
                    <i class="far fa-calendar-alt mr-1"></i>
                    {{ $dateStr }}
                </div>
                <div class="truncate max-w-[220px]">
                    {{ $invoice->original_filename }}
                </div>
            </div>
        </td>
        <td class="px-3 py-4 whitespace-nowrap">
            {{-- Status badges use solid colors with white text so they
            render consistently against both the light and dark
            backgrounds the page is shown on. --}}
            @switch($invoice->processing_status)
                @case('completed')
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-600 text-white">
                        <i class="fas fa-check-circle mr-1"></i>Completed
                    </span>
                @break

                @case('processing')
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-500 text-white">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Processing
                    </span>
                @break

                @case('failed')
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">
                        <i class="fas fa-exclamation-circle mr-1"></i>Failed
                    </span>
                @break

                @default
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-500 text-white">
                        <i class="fas fa-clock mr-1"></i>Pending
                    </span>
            @endswitch
        </td>
        <td class="px-3 py-4 whitespace-nowrap">
            <div class="flex flex-col space-y-1">
                <div class="flex items-center">
                    <span id="phone-{{ $invoice->id }}"
                        class="text-sm text-gray-900 dark:text-gray-100 mr-2">
                        {{ $invoice->customer_phone ?: ($invoice->live_phone ?? null) ?: 'No Phone' }}
                    </span>
                    <button
                        onclick="editPhoneNumber({{ $invoice->id }}, '{{ $invoice->customer_phone }}')"
                        class="inline-flex items-center px-1.5 py-0.5 border border-blue-200 dark:border-blue-800 rounded text-[10px] font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 dark:bg-blue-900/30"
                        title="Edit Phone Number">
                        <i class="fas fa-edit mr-1"></i>Change
                    </button>
                </div>
                @if ($invoice->whatsapp_status === 'sent')
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-600 text-white">
                        <i class="fas fa-check mr-1"></i>Sent
                        {{ $invoice->whatsapp_sent_at->diffForHumans() }}
                    </span>
                @elseif($invoice->whatsapp_status === 'failed')
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-600 text-white"
                        title="{{ $invoice->whatsapp_error }}">
                        <i class="fas fa-times mr-1"></i>Failed
                    </span>
                @else
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500 text-white">
                        <i class="fas fa-minus mr-1"></i>Not Sent
                    </span>
                @endif
            </div>
        </td>
        <td
            class="px-3 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
            <div>{{ $invoice->uploaded_at->format('M d, Y') }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">by
                {{ $invoice->uploader->name ?? 'Unknown' }}</div>
        </td>
        <td class="px-3 py-2 whitespace-nowrap text-sm">
            <div x-data="{ open: false }"
                class="relative inline-block text-left">

                <button @click="open = !open"
                    class="inline-flex items-center justify-center w-55 h-9 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-ellipsis-v px-3"> Actions</i>
                </button>

                <div x-show="open" @click.away="open = false" x-transition
                    class="absolute right-0 mt-2 w-52 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50">

                    <div class="py-1">

                        <a href="{{ route('invoices.show', $invoice->id) }}"
                            class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-eye w-5 text-blue-600"></i>
                            View Invoice
                        </a>

                        @if ($invoice->processing_status === 'completed' && $invoice->pdf_path)
                            <a href="{{ route('invoices.download', $invoice->id) }}"
                                class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i
                                    class="fas fa-download w-5 text-indigo-600"></i>
                                Download PDF
                            </a>

                            {{-- Only offered when there's an actual number to send to — the
                                invoice's own stored phone, or (falling back) the customer's
                                current contact number from the customers table. --}}
                            @php $effectivePhone = $invoice->customer_phone ?: ($invoice->live_phone ?? null); @endphp
                            @if ($effectivePhone)
                                <button
                                    onclick="sendWhatsApp({{ $invoice->id }}, '{{ $effectivePhone }}')"
                                    class="flex items-center w-full px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i
                                        class="fab fa-whatsapp w-5 text-green-600"></i>
                                    Send WhatsApp
                                </button>
                            @endif
                        @endif

                        <a href="{{ route('invoices.customer', $invoice->customer_code) }}"
                            class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-user w-5 text-purple-600"></i>
                            Customer History
                        </a>

                        <button type="button"
                            @click="open = false; openFor({{ $invoice->id }}, @js($invoice->invoice_number))"
                            class="flex items-center w-full px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-truck w-5 text-yellow-600"></i>
                            Attach Builty
                        </button>

                        @if (($invoice->whatsapp_status ?? null) !== 'sent')
                            <form
                                action="{{ route('invoices.destroy', $invoice->id) }}"
                                method="POST"
                                onsubmit="return confirm('Delete this invoice?')">
                                @csrf
                                @method('DELETE')

                                <button
                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <i class="fas fa-trash w-5"></i>
                                    Delete
                                </button>
                            </form>
                        @endif

                    </div>

                </div>

            </div>
        </td>
    </tr>
@empty
    <tr class="group-detail-row">
        <td colspan="6" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
            No invoices found for this group.
        </td>
    </tr>
@endforelse
