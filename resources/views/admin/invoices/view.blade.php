<x-app-layout>
    <x-toast />
    @php $canAttachBuilty = auth()->user()?->isAdmin() || auth()->user()?->isInvoiceManager(); @endphp
    <div x-data="attachBuiltyModal()">
    @php
        $filterStatus    = $filterStatus    ?? null;
        $filterWhatsapp  = $filterWhatsapp  ?? null;
        $filterCustomer  = $filterCustomer  ?? null;
        $customerOptions = $customerOptions ?? collect();
    @endphp
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Invoices') }}
                </h2>

                <div class="flex-1 max-w-sm mx-4">
                    <form action="{{ route('invoices.view') }}" method="GET" class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none transition-colors group-focus-within:text-primary-500 text-gray-400">
                            <i class="fas fa-search text-sm"></i>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-all shadow-sm hover:border-gray-400 dark:hover:border-gray-600"
                               placeholder="Search customer, code, invoice #, or phone…">
                        @if(request('search'))
                            <a href="{{ route('invoices.view') }}" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        @endif
                    </form>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-secondary-button type="button" onclick="document.getElementById('exportModal').classList.remove('hidden')">
                        <i class="fas fa-file-export mr-2"></i>Export Data
                    </x-secondary-button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    {{-- Filter card --}}
                    <div class="mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <form method="GET" action="{{ route('invoices.view') }}" class="flex flex-wrap items-end gap-3">
                            @if(request('search'))
                                <input type="hidden" name="search" value="{{ request('search') }}">
                            @endif
                            <div class="flex flex-col">
                                <label for="inv-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                                <input type="date" name="from" id="inv-from"
                                       value="{{ $filterFrom ?? '' }}"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                            </div>
                            <div class="flex flex-col">
                                <label for="inv-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                                <input type="date" name="to" id="inv-to"
                                       value="{{ $filterTo ?? '' }}"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                            </div>
                            <div class="flex flex-col">
                                <label for="inv-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select name="status" id="inv-status"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <option value="">All</option>
                                    <option value="completed"  @selected($filterStatus === 'completed')>Completed</option>
                                    <option value="processing" @selected($filterStatus === 'processing')>Processing</option>
                                    <option value="failed"     @selected($filterStatus === 'failed')>Failed</option>
                                    <option value="pending"    @selected($filterStatus === 'pending')>Pending</option>
                                </select>
                            </div>
                            <div class="flex flex-col min-w-[14rem]">
                                <label for="inv-customer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Customer</label>
                                <input list="inv-customer-list" name="customer" id="inv-customer"
                                       value="{{ $filterCustomer }}"
                                       placeholder="All customers"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                <datalist id="inv-customer-list">
                                    @foreach($customerOptions as $opt)
                                        <option value="{{ $opt->customer_code }}">{{ $opt->customer_name }} ({{ $opt->customer_code }})</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="flex flex-col">
                                <label for="inv-whatsapp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp</label>
                                <select name="whatsapp" id="inv-whatsapp"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <option value="">All</option>
                                    <option value="sent"    @selected($filterWhatsapp === 'sent')>Sent</option>
                                    <option value="failed"  @selected($filterWhatsapp === 'failed')>Failed</option>
                                    <option value="pending" @selected($filterWhatsapp === 'pending')>Not Sent</option>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="inv-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                                <input type="text" name="search" id="inv-search"
                                       value="{{ request('search') }}"
                                       placeholder="customer / code / # / phone"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="py-2 px-4 inline-flex justify-center items-center gap-x-1 text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 text-white shadow-sm">
                                    <i class="fas fa-filter mr-1"></i>Apply
                                </button>
                                <a href="{{ route('invoices.view', ['from' => now()->toDateString(), 'to' => now()->toDateString()]) }}"
                                   class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Today
                                </a>
                                <a href="{{ route('invoices.view') }}"
                                   class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    {{-- Summary cards --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-primary-50 dark:bg-primary-900/20 p-4 rounded-lg border border-primary-100 dark:border-primary-800">
                            <div class="text-primary-800 dark:text-primary-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Total Invoices</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->total ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800">
                            <div class="text-green-800 dark:text-green-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Completed</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->completed ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800">
                            <div class="text-yellow-800 dark:text-yellow-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Processing</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->processing ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-100 dark:border-red-800">
                            <div class="text-red-800 dark:text-red-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Failed</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->failed ?? 0 }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Flat invoices table --}}
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Invoice Details</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">WhatsApp</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Uploaded</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($invoicesPage as $invoice)
                                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $invoice->customer_name }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">Code: {{ $invoice->customer_code }}</div>
                                                @if($invoice->customer_phone)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->customer_phone }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 align-top max-w-xs">
                                            @php
                                                // invoice_number may hold a comma-separated list of numbers
                                                // pulled from a multi-invoice PDF. Split, trim, and show
                                                // the first few as compact chips with a "+N more" tail so
                                                // the column never balloons vertically.
                                                $numbers = collect(explode(',', (string) $invoice->invoice_number))
                                                    ->map(fn($n) => trim($n))
                                                    ->filter()
                                                    ->values();
                                                $preview = $numbers->take(4);
                                                $extra   = max(0, $numbers->count() - $preview->count());
                                            @endphp
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($preview as $num)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary-50 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200 border border-primary-100 dark:border-primary-800">
                                                        {{ $num }}
                                                    </span>
                                                @empty
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                                @endforelse
                                                @if($extra > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600"
                                                          title="{{ $numbers->slice(4)->implode(', ') }}">
                                                        +{{ $extra }} more
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-tighter break-all">
                                                {{ $invoice->original_filename }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            @if(!is_null($invoice->total_amount))
                                                {{ number_format((float) $invoice->total_amount, 2) }}
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500 font-normal">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @switch($invoice->processing_status)
                                                @case('completed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>Completed
                                                    </span>
                                                    @break
                                                @case('processing')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
                                                        <i class="fas fa-spinner fa-spin mr-1"></i>Processing
                                                    </span>
                                                    @break
                                                @case('failed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>Failed
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600">
                                                        <i class="fas fa-clock mr-1"></i>Pending
                                                    </span>
                                            @endswitch
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($invoice->whatsapp_status === 'sent')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800">
                                                    <i class="fas fa-check mr-1"></i>Sent {{ optional($invoice->whatsapp_sent_at)->diffForHumans() }}
                                                </span>
                                            @elseif($invoice->whatsapp_status === 'failed')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800" title="{{ $invoice->whatsapp_error }}">
                                                    <i class="fas fa-times mr-1"></i>Failed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600">
                                                    <i class="fas fa-minus mr-1"></i>Not Sent
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                            <div>{{ optional($invoice->uploaded_at)->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">by {{ $invoice->uploader->name ?? 'Unknown' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('invoices.show', $invoice->id) }}"
                                                   class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                   title="View Invoice Details">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                                @if($invoice->processing_status === 'completed' && $invoice->pdf_path)
                                                    <a href="{{ route('invoices.download', $invoice->id) }}"
                                                       class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                       title="Download PDF">
                                                        <i class="fas fa-download mr-1"></i>PDF
                                                    </a>
                                                @endif
                                                <a href="{{ route('invoices.customer', $invoice->customer_code) }}"
                                                   class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                   title="View Customer Invoices">
                                                    <i class="fas fa-user mr-1"></i>Customer
                                                </a>
                                                @if($canAttachBuilty)
                                                    <button type="button"
                                                            @click="openFor({{ $invoice->id }}, @js($invoice->invoice_number))"
                                                            style="background-color: #ca8a04; color: #ffffff;"
                                                            onmouseover="this.style.backgroundColor='#a16207'"
                                                            onmouseout="this.style.backgroundColor='#ca8a04'"
                                                            class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                            title="Attach an existing bilty to this invoice">
                                                        <i class="fas fa-truck mr-1" style="color: #ffffff;"></i>Attach Bilty
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-file-pdf text-4xl mb-3 text-gray-400 dark:text-gray-500"></i>
                                            <div class="text-base">No invoices match the current filters.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $invoicesPage->links() }}
                    </div>
                </div>
            </div>
        </div>

        @if($canAttachBuilty)
            @include('admin.invoices.partials.attach-builty-modal')
        @endif
    </div>

    {{-- Export modal — same as send page so view-only users can still export. --}}
    <div id="exportModal" class="hidden fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="document.getElementById('exportModal').classList.add('hidden')"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form action="{{ route('invoices.export') }}" method="GET">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">Export Invoices</h3>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start date</label>
                                <input type="date" name="start_date" value="{{ $filterFrom ?? '' }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">End date</label>
                                <input type="date" name="end_date" value="{{ $filterTo ?? '' }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Export</button>
                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="document.getElementById('exportModal').classList.add('hidden')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
