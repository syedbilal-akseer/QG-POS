<x-app-layout>
    <x-toast />
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Documents') }}
                </h2>

                <form action="{{ route('documents.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Search customer</label>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Code or name…"
                               class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                    </div>
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Document type</label>
                        <select name="type"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            <option value="">All types</option>
                            <option value="invoices" @selected($typeFilter === 'invoices')>Invoices only</option>
                            <option value="builties" @selected($typeFilter === 'builties')>Builties only</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 text-white shadow-sm">
                        Apply
                    </button>
                    @if($search || $typeFilter)
                        <a href="{{ route('documents.index') }}"
                           class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            {{-- Totals --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Customers</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totals['customers']) }}</div>
                    <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">with at least one document</div>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-blue-700 dark:text-blue-300">Invoices</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totals['invoices']) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-yellow-700 dark:text-yellow-300">Builties</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totals['builties']) }}</div>
                </div>
            </div>

            {{-- Customer folders — each one navigates to its own page
                 (documents.customer) rather than expanding in place, so the
                 whole browser behaves like a real file explorer: back/forward,
                 bookmarkable URLs, breadcrumbs at every level. --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($customersPage as $row)
                        <a href="{{ route('documents.customer', $row->customer_code) }}"
                           class="flex items-center justify-between px-6 py-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center min-w-0 flex-1">
                                <i class="fas fa-folder text-amber-500 dark:text-amber-400 mr-3 text-lg shrink-0"></i>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ $row->customer_name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Code: {{ $row->customer_code }}
                                        @if($row->customer_number)
                                            · #{{ $row->customer_number }}
                                        @endif
                                        @if($row->last_at)
                                            · last upload {{ $row->last_at->diffForHumans() }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($row->invoice_count > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        <i class="far fa-file-pdf mr-1"></i>{{ $row->invoice_count }} invoice{{ $row->invoice_count === 1 ? '' : 's' }}
                                    </span>
                                @endif
                                @if($row->builty_count > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
                                        <i class="fas fa-truck mr-1"></i>{{ $row->builty_count }} builty
                                    </span>
                                @endif
                                <i class="fas fa-chevron-right text-gray-300 dark:text-gray-600 ml-1"></i>
                            </div>
                        </a>
                    @empty
                        <div class="p-10 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-folder-open text-4xl mb-3 text-gray-400 dark:text-gray-500"></i>
                            <div class="text-base">No customers with documents found.</div>
                        </div>
                    @endforelse
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $customersPage->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
