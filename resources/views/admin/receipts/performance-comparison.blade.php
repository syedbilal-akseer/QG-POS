@push('title')
    Mobile Receipt Performance Comparison
@endpush

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        .select2-container--default .select2-selection--single {
            height: 2.5rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 2.375rem;
        }
        .dark .select2-container--default .select2-selection--single {
            background-color: #374151;
            border-color: #4b5563;
            color: #e5e7eb;
        }
        .dark .select2-dropdown {
            background-color: #374151;
            border-color: #4b5563;
        }
        .dark .select2-container--default .select2-results__option--selected {
            background-color: #4b5563;
        }
        .dark .select2-container--default .select2-results__option--highlighted {
            background-color: #6b7280;
        }
    </style>
@endpush

<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <a href="{{ route('admin.receipts.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-3">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Receipts
                </a>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mobile Receipt Performance Comparison</h1>
            </div>
            <form method="GET" action="{{ route('admin.receipts.performance_comparison.export') }}" class="flex items-center gap-2">
                <input type="hidden" name="from_month" value="{{ $fromMonth }}">
                <input type="hidden" name="to_month" value="{{ $toMonth }}">
                <input type="hidden" name="salesperson" value="{{ $salesperson }}">
                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-gray-800">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Excel
                </button>
            </form>
        </div>

        <!-- Filters Card -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-6">
                <form method="GET" action="{{ route('admin.receipts.performance_comparison') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Month</label>
                            <input type="month" name="from_month" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100" value="{{ $fromMonth }}" required>
                        </div>
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Month</label>
                            <input type="month" name="to_month" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100" value="{{ $toMonth }}" required>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Salesperson</label>
                            <select name="salesperson" id="salespersonSelect" class="w-full">
                                <option value="">All Salespeople</option>
                                @foreach($salespeople as $name)
                                    <option value="{{ $name }}" {{ $salesperson == $name ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort By</label>
                            <select name="sort_by" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100">
                                <option value="overall_mobile_percentage" {{ $sortBy === 'overall_mobile_percentage' ? 'selected' : '' }}>Overall Mobile %</option>
                                <option value="total_mobile" {{ $sortBy === 'total_mobile' ? 'selected' : '' }}>Total Mobile Receipts</option>
                                <option value="total_receipts" {{ $sortBy === 'total_receipts' ? 'selected' : '' }}>Total Receipts</option>
                                <option value="salesperson_name" {{ $sortBy === 'salesperson_name' ? 'selected' : '' }}>Salesperson Name</option>
                            </select>
                        </div>
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                            <select name="sort_order" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100">
                                <option value="desc" {{ $sortOrder === 'desc' ? 'selected' : '' }}>Desc</option>
                                <option value="asc" {{ $sortOrder === 'asc' ? 'selected' : '' }}>Asc</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <a href="{{ route('admin.receipts.performance_comparison') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Reset</a>
                        <button type="submit" class="px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-800">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Salesperson</th>
                            @foreach($months as $month)
                                <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ $month['label'] }}</th>
                            @endforeach
                            <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Overall</th>
                        </tr>
                        <tr>
                            @foreach($months as $month)
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">Receipts</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">Mobile</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">%</th>
                            @endforeach
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">Total Receipts</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">Total Mobile</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">Overall %</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($salespersonData as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['salesperson_name'] }}</td>
                                @foreach($months as $month)
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600 dark:text-gray-400">{{ $row['months'][$month['key']]['receipts'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600 dark:text-gray-400">{{ $row['months'][$month['key']]['mobile'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @if($row['months'][$month['key']]['receipts'] > 0)
                                            <span class="{{ $row['months'][$month['key']]['mobile'] / $row['months'][$month['key']]['receipts'] >= 0.7 ? 'text-green-600 dark:text-green-400' : ($row['months'][$month['key']]['mobile'] / $row['months'][$month['key']]['receipts'] >= 0.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                                {{ round(($row['months'][$month['key']]['mobile'] / $row['months'][$month['key']]['receipts']) * 100, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400">0%</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium text-gray-900 dark:text-gray-100">{{ $row['overall']['receipts'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium text-gray-900 dark:text-gray-100">{{ $row['overall']['mobile'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium">
                                    @if($row['overall']['receipts'] > 0)
                                        <span class="{{ $row['overall']['mobile'] / $row['overall']['receipts'] >= 0.7 ? 'text-green-600 dark:text-green-400' : ($row['overall']['mobile'] / $row['overall']['receipts'] >= 0.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                            {{ round(($row['overall']['mobile'] / $row['overall']['receipts']) * 100, 2) }}%
                                        </span>
                                    @else
                                        <span class="text-gray-400">0%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <!-- Totals Row -->
                        <tr class="bg-blue-50 dark:bg-blue-900/30 font-semibold">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">All Salespeople</td>
                            @foreach($months as $month)
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-gray-100">{{ $totals[$month['key']]['receipts'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-gray-100">{{ $totals[$month['key']]['mobile'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                    @if($totals[$month['key']]['receipts'] > 0)
                                        <span class="{{ $totals[$month['key']]['mobile'] / $totals[$month['key']]['receipts'] >= 0.7 ? 'text-green-600 dark:text-green-400' : ($totals[$month['key']]['mobile'] / $totals[$month['key']]['receipts'] >= 0.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                            {{ round(($totals[$month['key']]['mobile'] / $totals[$month['key']]['receipts']) * 100, 2) }}%
                                        </span>
                                    @else
                                        <span class="text-gray-400">0%</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-gray-100">{{ $totals['overall']['receipts'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-gray-100">{{ $totals['overall']['mobile'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                @if($totals['overall']['receipts'] > 0)
                                    <span class="{{ $totals['overall']['mobile'] / $totals['overall']['receipts'] >= 0.7 ? 'text-green-600 dark:text-green-400' : ($totals['overall']['mobile'] / $totals['overall']['receipts'] >= 0.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                                        {{ round(($totals['overall']['mobile'] / $totals['overall']['receipts']) * 100, 2) }}%
                                    </span>
                                @else
                                    <span class="text-gray-400">0%</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#salespersonSelect').select2({
                placeholder: 'Select a salesperson',
                allowClear: true
            });
        });
    </script>
@endpush
