@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        .flatpickr-date {
            height: 2.375rem;
        }
        .dark .flatpickr-calendar {
            background: #1f2937;
            box-shadow: 0 3px 13px rgba(0,0,0,0.4);
        }
        .dark .flatpickr-months, .dark .flatpickr-weekdays { background: #1f2937; }
        .dark .flatpickr-month, .dark .flatpickr-weekday { color: #e5e7eb !important; fill: #e5e7eb; }
        .dark .flatpickr-day { color: #e5e7eb; }
        .dark .flatpickr-day.flatpickr-disabled, .dark .flatpickr-day.prevMonthDay, .dark .flatpickr-day.nextMonthDay { color: #6b7280; }
        .dark .flatpickr-day:hover { background: #374151; }
        .dark .flatpickr-day.selected { background: #4f46e5; border-color: #4f46e5; }
        .dark .flatpickr-current-month input.cur-year { color: #e5e7eb; }
        .dark .flatpickr-prev-month svg, .dark .flatpickr-next-month svg { fill: #e5e7eb; }
    </style>
    <style>
        .select2-container--default .select2-selection--single {
            height: 2.375rem;
            padding: 0.375rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5rem;
            padding-left: 0;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 2.25rem;
        }
        .dark .select2-container--default .select2-selection--single {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
        .dark .select2-container--default .select2-selection--single .select2-selection__rendered,
        .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #e5e7eb !important;
        }
        .dark .select2-dropdown {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
        .dark .select2-container--default .select2-results__option {
            color: #e5e7eb !important;
        }
        .dark .select2-container--default .select2-results__option--selected {
            background-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
        .dark .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: #6366f1 !important;
            color: #fff !important;
        }
        .dark .select2-search--dropdown .select2-search__field,
        .dark .select2-search__field {
            background-color: #1f2937 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
    </style>
@endpush

<x-app-layout>
    <x-toast />
    @php
        $filterFrom = $filterFrom ?? null;
        $filterTo = $filterTo ?? null;
        $filterStatus = $filterStatus ?? null;
        $filterWhatsapp = $filterWhatsapp ?? null;
        $filterCustomer = $filterCustomer ?? null;
        $filterSalesperson = $filterSalesperson ?? null;
        $activeImport = $activeImport ?? null;
        $customerOptions = $customerOptions ?? collect();
        $salespersonOptions = $salespersonOptions ?? collect();
    @endphp
    <div class="py-2">
        <div class="mx-2 px-2 sm:px-6 lg:px-4">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Ledgers') }}
                </h2>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('ledgers.upload') }}"
                       class="py-2 px-4 inline-flex justify-center items-center gap-x-1 text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 text-white shadow-sm">
                        <i class="fas fa-upload mr-1"></i>Ledger Import
                    </a>
                </div>
            </div>

            {{-- Just-imported context banner — appears right after uploading, so the
                 user lands on exactly what they need to review/send next instead of
                 the full unfiltered list. --}}
            @if($activeImport)
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4 py-4 rounded-lg border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-900/20">
                    <div class="flex items-center gap-3 text-sm text-primary-900 dark:text-primary-200">
                        <i class="fas fa-check-circle text-primary-600 dark:text-primary-400 text-lg"></i>
                        <div>
                            <div class="font-semibold">Showing {{ $activeImport->imported_count }} ledger(s) from "{{ $activeImport->original_filename }}"</div>
                            <div class="text-xs opacity-80">
                                {{ $activeImport->customers_found }} found
                                @if($activeImport->duplicate_count) &middot; {{ $activeImport->duplicate_count }} duplicate @endif
                                @if($activeImport->failed_count) &middot; {{ $activeImport->failed_count }} failed to split @endif
                                &middot; select the ones you want and send below
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('ledgers.index') }}" class="text-sm text-primary-700 dark:text-primary-300 hover:underline whitespace-nowrap">
                        View all ledgers &rarr;
                    </a>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="py-2 text-gray-900 dark:text-gray-100">

                    {{-- Filter card --}}
                    <div class="mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <form method="GET" action="{{ route('ledgers.index') }}" class="flex flex-wrap items-end gap-3">
                            @if($activeImport)
                                <input type="hidden" name="import" value="{{ $activeImport->id }}">
                            @endif
                            <div class="flex flex-col">
                                <label for="lg-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period From</label>
                                <div class="relative">
                                    <input type="text" name="from" id="lg-from" autocomplete="off"
                                           value="{{ $filterFrom }}" placeholder="Any"
                                           class="flatpickr-date w-36 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm pr-8">
                                    <i class="fas fa-calendar-alt absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                                </div>
                            </div>
                            <div class="flex flex-col">
                                <label for="lg-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period To</label>
                                <div class="relative">
                                    <input type="text" name="to" id="lg-to" autocomplete="off"
                                           value="{{ $filterTo }}" placeholder="Any"
                                           class="flatpickr-date w-36 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm pr-8">
                                    <i class="fas fa-calendar-alt absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                                </div>
                            </div>
                            <div class="flex flex-col justify-end">
                                <div class="flex gap-1">
                                    <button type="button" onclick="setQuickRange('month')" class="py-1 px-2 text-xs rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">This Month</button>
                                    <button type="button" onclick="setQuickRange('last-month')" class="py-1 px-2 text-xs rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Last Month</button>
                                </div>
                            </div>
                            <div class="flex flex-col min-w-[16rem]">
                                <label for="lg-customer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Customer</label>
                                <select name="customer" id="lg-customer" class="w-full">
                                    <option value="">All customers</option>
                                    @foreach($customerOptions as $opt)
                                        <option value="{{ $opt->customer_code }}" @selected($filterCustomer === $opt->customer_code)>{{ $opt->customer_name }} ({{ $opt->customer_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col min-w-[14rem]">
                                <label for="lg-salesperson" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Salesperson</label>
                                <select name="salesperson" id="lg-salesperson" class="w-full">
                                    <option value="">All</option>
                                    <option value="unmatched" @selected($filterSalesperson === 'unmatched')>Unmatched</option>
                                    @foreach($salespersonOptions as $sp)
                                        <option value="{{ $sp->id }}" @selected((string) $filterSalesperson === (string) $sp->id)>{{ $sp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="lg-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select name="status" id="lg-status"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <option value="">All</option>
                                    <option value="completed" @selected($filterStatus === 'completed')>Completed</option>
                                    <option value="failed" @selected($filterStatus === 'failed')>Failed</option>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="lg-whatsapp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp</label>
                                <select name="whatsapp" id="lg-whatsapp"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                                    <option value="">All</option>
                                    <option value="sent" @selected($filterWhatsapp === 'sent')>Sent</option>
                                    <option value="failed" @selected($filterWhatsapp === 'failed')>Failed</option>
                                    <option value="pending" @selected($filterWhatsapp === 'pending')>Not Sent</option>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="lg-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                                <input type="text" name="search" id="lg-search"
                                       value="{{ request('search') }}"
                                       placeholder="customer / code / phone / salesperson"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="py-2 px-4 inline-flex justify-center items-center gap-x-1 text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 text-white shadow-sm">
                                    <i class="fas fa-filter mr-1"></i>Apply
                                </button>
                                <a href="{{ route('ledgers.index') }}"
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
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Total Ledgers</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->total ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800">
                            <div class="text-green-800 dark:text-green-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Completed</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->completed ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-100 dark:border-red-800">
                            <div class="text-red-800 dark:text-red-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Failed</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->failed ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800">
                            <div class="text-yellow-800 dark:text-yellow-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Not Sent</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->unsent ?? 0 }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Live WhatsApp queue banner — shows while any ledger is pending/processing --}}
                    <div id="whatsappQueueBanner" class="hidden flex flex-wrap items-center gap-3 mb-3 p-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 text-sm text-blue-800 dark:text-blue-200">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span id="whatsappQueueText">Sending…</span>
                    </div>

                    {{-- Bulk-select bar --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            <span id="selectedCount">0</span> selected
                        </div>
                        <button type="button" id="sendSelectedBtn" onclick="sendSelected()" disabled
                                class="py-2 px-4 inline-flex justify-center items-center gap-x-1 text-sm font-medium rounded-lg border border-transparent bg-green-600 hover:bg-green-700 text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-600">
                            <i class="fab fa-whatsapp mr-1"></i>Send Selected via WhatsApp
                        </button>
                    </div>

                    {{-- Ledgers table --}}
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left">
                                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)" class="rounded border-gray-300">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Salesperson</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Period</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Uploaded</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($ledgersPage as $ledger)
                                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <td class="px-4 py-4">
                                            @if($ledger->processing_status === 'completed')
                                                <input type="checkbox" class="ledger-checkbox rounded border-gray-300" value="{{ $ledger->id }}" onclick="updateSelectedCount()">
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="cursor-pointer group" onclick="showCustomerInfo('{{ $ledger->customer_code }}')" title="View customer details">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 group-hover:underline">{{ $ledger->customer_name }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">Code: {{ $ledger->customer_code }}</div>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                                <span id="phone-{{ $ledger->id }}">{{ $ledger->customer_phone ?: 'No phone' }}</span>
                                                <button type="button" onclick="editPhoneNumber({{ $ledger->id }}, '{{ $ledger->customer_phone }}')" class="text-primary-600 hover:text-primary-800" title="Edit phone">
                                                    <i class="fas fa-pen text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @if($ledger->salesperson_id)
                                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $ledger->salesperson->name ?? $ledger->salesperson_name }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600" title="{{ $ledger->salesperson_raw }}">
                                                    <i class="fas fa-question-circle mr-1"></i>{{ $ledger->salesperson_name ?: 'Unmatched' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                            @if($ledger->period_from && $ledger->period_to)
                                                {{ $ledger->period_from->format('d M') }} – {{ $ledger->period_to->format('d M Y') }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">pages {{ $ledger->page_range }}</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @if($ledger->processing_status !== 'completed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800" title="{{ $ledger->notes }}">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Parse Failed
                                                </span>
                                            @elseif($ledger->whatsapp_status === 'sent')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800">
                                                    <i class="fab fa-whatsapp mr-1"></i>Sent {{ optional($ledger->whatsapp_sent_at)->diffForHumans() }}
                                                </span>
                                            @elseif($ledger->whatsapp_status === 'processing')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
                                                    <i class="fas fa-spinner fa-spin mr-1"></i>Sending
                                                </span>
                                            @elseif($ledger->whatsapp_status === 'pending')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                    <i class="fas fa-clock mr-1"></i>Queued
                                                </span>
                                            @elseif($ledger->whatsapp_status === 'failed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800" title="{{ $ledger->whatsapp_error }}">
                                                    <i class="fas fa-times mr-1"></i>Send Failed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600">
                                                    <i class="fas fa-minus mr-1"></i>Not Sent
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                            <div>{{ optional($ledger->uploaded_at)->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">by {{ $ledger->uploader->name ?? 'Unknown' }}</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                @if($ledger->processing_status === 'completed' && $ledger->pdf_path)
                                                    <a href="{{ route('ledgers.download', $ledger->id) }}"
                                                       class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700"
                                                       title="Download PDF">
                                                        <i class="fas fa-download mr-1"></i>PDF
                                                    </a>
                                                    <button type="button"
                                                            onclick="sendSingle({{ $ledger->id }}, '{{ $ledger->customer_phone }}')"
                                                            class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-green-600 hover:bg-green-700"
                                                            title="Send via WhatsApp">
                                                        <i class="fab fa-whatsapp mr-1"></i>Send
                                                    </button>
                                                @endif
                                                <button type="button"
                                                        onclick="deleteLedger({{ $ledger->id }})"
                                                        class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-red-600 hover:bg-red-700"
                                                        title="Delete">
                                                    <i class="fas fa-trash mr-1"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-file-pdf text-4xl mb-3 text-gray-400 dark:text-gray-500"></i>
                                            <div class="text-base">No ledgers match the current filters.</div>
                                            <a href="{{ route('ledgers.upload') }}" class="text-primary-600 hover:underline text-sm">Import a ledger PDF</a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $ledgersPage->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden delete forms — one per row, submitted via JS confirm() --}}
    @foreach($ledgersPage as $ledger)
        <form id="delete-form-{{ $ledger->id }}" action="{{ route('ledgers.destroy', $ledger->id) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    <script>
        function showToastSuccess(msg) {
            window.dispatchEvent(new CustomEvent('toast-success', { detail: msg }));
        }

        function showToastError(msg) {
            window.dispatchEvent(new CustomEvent('toast-error', { detail: msg }));
        }

        // Quick period-range shortcuts — pick the range and auto-apply the filter.
        function setQuickRange(range) {
            const now = new Date();
            let start, end;

            if (range === 'month') {
                start = new Date(now.getFullYear(), now.getMonth(), 1);
                end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            } else if (range === 'last-month') {
                start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                end = new Date(now.getFullYear(), now.getMonth(), 0);
            }

            const fmt = (d) => d.toISOString().slice(0, 10);
            const fromInput = document.getElementById('lg-from');
            const toInput = document.getElementById('lg-to');

            if (fromInput._flatpickr) fromInput._flatpickr.setDate(fmt(start));
            else fromInput.value = fmt(start);

            if (toInput._flatpickr) toInput._flatpickr.setDate(fmt(end));
            else toInput.value = fmt(end);

            fromInput.closest('form').submit();
        }

        // Live WhatsApp send status — polls while anything is pending/processing
        // and auto-refreshes the page once a batch finishes so statuses update
        // without the user having to reload manually.
        let statusPollingInterval = null;
        let queueWasActive = false;

        document.addEventListener('DOMContentLoaded', function() {
            checkQueueStatus();
        });

        function checkQueueStatus() {
            fetch("{{ route('ledgers.whatsapp-status') }}")
                .then(r => r.json())
                .then(data => {
                    if (data.success) updateQueueBanner(data.stats);
                })
                .catch(() => {});
        }

        function updateQueueBanner(stats) {
            const pending = parseInt(stats.pending) || 0;
            const processing = parseInt(stats.processing) || 0;
            const active = pending + processing;
            const banner = document.getElementById('whatsappQueueBanner');

            if (active > 0) {
                queueWasActive = true;
                banner.classList.remove('hidden');
                document.getElementById('whatsappQueueText').innerText =
                    `Sending via WhatsApp… ${processing} in progress, ${pending} queued`;
                startStatusPolling();
            } else {
                banner.classList.add('hidden');
                stopStatusPolling();
                if (queueWasActive) {
                    queueWasActive = false;
                    location.reload();
                }
            }
        }

        function startStatusPolling() {
            if (!statusPollingInterval) {
                statusPollingInterval = setInterval(checkQueueStatus, 4000);
            }
        }

        function stopStatusPolling() {
            if (statusPollingInterval) {
                clearInterval(statusPollingInterval);
                statusPollingInterval = null;
            }
        }

        function toggleAll(source) {
            document.querySelectorAll('.ledger-checkbox').forEach(cb => cb.checked = source.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.ledger-checkbox:checked');
            document.getElementById('selectedCount').innerText = checked.length;
            document.getElementById('sendSelectedBtn').disabled = checked.length === 0;
        }

        function editPhoneNumber(id, currentPhone) {
            const newPhone = prompt("Enter new phone number (e.g. 923321234567):", currentPhone || "");
            if (newPhone === null) return;

            fetch("{{ route('ledgers.update-phone', ['ledger' => '__ID__']) }}".replace('__ID__', id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ phone: newPhone })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`phone-${id}`).innerText = data.phone;
                        showToastSuccess('Phone number updated successfully');
                    } else {
                        showToastError('Error: ' + data.message);
                    }
                })
                .catch(() => showToastError('An error occurred while updating the phone number'));
        }

        function sendSingle(id, phone) {
            if (!phone || phone === '') {
                showToastError('Please set a phone number first');
                return;
            }
            if (!confirm(`Send ledger via WhatsApp to ${phone}?`)) return;

            const btn = event.currentTarget;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Queueing...';

            fetch("{{ route('ledgers.send-whatsapp', ['ledger' => '__ID__']) }}".replace('__ID__', id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ phone: phone })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToastSuccess('Ledger queued for sending!');
                        checkQueueStatus();
                        startStatusPolling();
                    } else {
                        showToastError('Error: ' + data.message);
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                })
                .catch(() => {
                    showToastError('An error occurred while queuing the message');
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        }

        function sendSelected() {
            const ids = Array.from(document.querySelectorAll('.ledger-checkbox:checked')).map(cb => parseInt(cb.value, 10));
            if (ids.length === 0) return;
            if (!confirm(`Send ${ids.length} ledger(s) via WhatsApp?`)) return;

            const btn = document.getElementById('sendSelectedBtn');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending…';

            fetch("{{ route('ledgers.bulk-send') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ ledger_ids: ids })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToastSuccess(data.message);
                        checkQueueStatus();
                        startStatusPolling();
                    } else {
                        showToastError('Error: ' + (data.message ?? 'unknown'));
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                })
                .catch(() => {
                    showToastError('Could not initiate send.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        }

        function deleteLedger(id) {
            if (!confirm('Delete this ledger and its PDF? This cannot be undone.')) return;
            document.getElementById(`delete-form-${id}`).submit();
        }
    </script>

    {{-- Customer details popup — same pattern as the Invoices page, populated
         live via fetch() against LedgerController::customerInfo(). --}}
    <x-modal name="customer_detail" maxWidth="2xl">
        <div class="p-6 bg-white dark:bg-neutral-800">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Customer Details</h2>
                <span onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'customer_detail' }))"
                    class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </span>
            </div>

            <div id="customer-detail-loading" class="mt-6 text-center text-gray-500 dark:text-gray-400 hidden">
                <i class="fas fa-spinner fa-spin mr-2"></i>Loading…
            </div>
            <div id="customer-detail-error" class="mt-6 text-center text-red-600 hidden">
                Customer not found.
            </div>

            <div id="customer-detail-fields" class="mt-4 space-y-4 hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ([
                        'customer_id' => 'Customer ID',
                        'ou_name' => 'OU Name',
                        'ou_id' => 'OU ID',
                        'customer_name' => 'Customer Name',
                        'customer_number' => 'Account Number',
                        'customer_site_id' => 'Customer Site ID',
                        'salesperson' => 'Salesperson',
                        'city' => 'City',
                        'area' => 'Area',
                        'address1' => 'Address',
                        'contact_number' => 'Contact Number',
                        'email_address' => 'Email Address',
                        'nic' => 'NIC',
                        'ntn' => 'NTN',
                        'price_list_id' => 'Price List ID',
                        'price_list_name' => 'Price List Name',
                        'creation_date' => 'Creation Date',
                    ] as $field => $label)
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">{{ $label }}:</label>
                            <span id="cd-{{ $field }}" class="text-gray-900 dark:text-gray-100"></span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
            <x-secondary-button type="button"
                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'customer_detail' }))"
                class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                {{ __('Cancel') }}
            </x-secondary-button>
        </div>
    </x-modal>

    <script>
        const CUSTOMER_DETAIL_FIELDS = [
            'customer_id', 'ou_name', 'ou_id', 'customer_name', 'customer_number',
            'customer_site_id', 'salesperson', 'city', 'area', 'address1',
            'contact_number', 'email_address', 'nic', 'ntn',
            'price_list_id', 'price_list_name', 'creation_date',
        ];

        function showCustomerInfo(code) {
            const loading = document.getElementById('customer-detail-loading');
            const errorBox = document.getElementById('customer-detail-error');
            const fields = document.getElementById('customer-detail-fields');

            errorBox.classList.add('hidden');
            fields.classList.add('hidden');
            loading.classList.remove('hidden');

            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'customer_detail' }));

            fetch(`{{ route('ledgers.customer-info') }}?code=${encodeURIComponent(code)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(r => {
                    if (!r.ok) throw new Error('Not found');
                    return r.json();
                })
                .then(data => {
                    CUSTOMER_DETAIL_FIELDS.forEach(field => {
                        const el = document.getElementById('cd-' + field);
                        if (el) el.textContent = data[field] ?? '—';
                    });
                    loading.classList.add('hidden');
                    fields.classList.remove('hidden');
                })
                .catch(() => {
                    loading.classList.add('hidden');
                    errorBox.classList.remove('hidden');
                });
        }
    </script>
</x-app-layout>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#lg-customer').select2({
                placeholder: 'All customers',
                allowClear: true,
                width: '100%'
            });
            $('#lg-salesperson').select2({
                placeholder: 'All salespeople',
                allowClear: true,
                width: '100%'
            });
        });

        flatpickr('.flatpickr-date', {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            altInputClass: 'flatpickr-date w-36 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm pr-8',
            allowInput: true
        });
    </script>
@endpush
