<x-app-layout>
    <x-toast />
    @php
        $filterStatus = $filterStatus ?? null;
        $filterWhatsapp = $filterWhatsapp ?? null;
    @endphp
    <div x-data="attachBuiltyModal()" class="py-6">
        <div class="w-full max-w-none ">
            <!-- Header Moved to Body -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Send Invoices') }}
                </h2>

                <!-- Customer Search Bar — submits in a new tab so the user keeps
                     their current accordion / filter state in the original tab. -->
                <div class="flex-1 max-w-sm mx-4">
                    <form action="{{ route('invoices.index') }}" method="GET" target="_blank" rel="noopener"
                        class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none transition-colors group-focus-within:text-primary-500 text-gray-400">
                            <i class="fas fa-search text-sm"></i>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm transition-all shadow-sm hover:border-gray-400 dark:hover:border-gray-600"
                            placeholder="Search customer, code, invoice #, or phone…">
                        @if (request('search'))
                            <a href="{{ route('invoices.index') }}" target="_self"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        @endif
                    </form>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Template fixed to Urdu -->
                    <input type="hidden" id="whatsapp_template" value="invoice_urdu">

                    <button type="button" onclick="sendAllUnsentInvoices()" id="sendAllBtn"
                        class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-bold rounded-lg border border-transparent bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-all shadow-sm active:scale-[0.98]">
                        <i class="fas fa-paper-plane mr-2"></i><span class="text-white">Send All Messages</span>
                    </button>
                    <x-secondary-button type="button"
                        onclick="document.getElementById('exportModal').classList.remove('hidden')">
                        <i class="fas fa-file-export mr-2"></i>Export Data
                    </x-secondary-button>
                    <a href="{{ route('invoices.upload') }}"
                        class="py-2 px-4 inline-flex justify-center items-center text-white gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                        <i class="fas fa-upload mr-2"></i>Upload New PDF
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-0 text-gray-900 dark:text-gray-100">

                    <!-- Toast handles notifications now -->

                    @if (!empty($diskFiles))
                        <!-- Disk File Explorer Section -->
                        <div
                            class="mb-8 overflow-hidden rounded-2xl border border-blue-200 dark:border-blue-900 overflow-hidden shadow-sm">
                            <div
                                class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/5 dark:to-indigo-900/5 border-b border-blue-200 dark:border-blue-900 flex items-center">
                                <i class="fas fa-search-location text-blue-600 dark:text-blue-400 mr-3"></i>
                                <h3
                                    class="text-sm font-bold text-blue-900 dark:text-blue-300 uppercase tracking-widest">
                                    Storage Explorer matches for "{{ request('search') }}"
                                </h3>
                            </div>
                            <div class="bg-white dark:bg-gray-800 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                File Name</th>
                                            <th
                                                class="px-6 py-3 text-left text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                Size</th>
                                            <th
                                                class="px-6 py-3 text-left text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                Modified</th>
                                            <th
                                                class="px-6 py-3 text-right text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                Action</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                        @foreach ($diskFiles as $file)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors">
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-200">
                                                    <div class="flex items-center">
                                                        <i class="far fa-file-pdf text-red-500 mr-2"></i>
                                                        {{ $file['name'] }}
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                                    {{ number_format($file['size'] / 1024, 2) }} KB
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                                    {{ date('M d, Y H:i', $file['time']) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    @php
                                                        // Create a direct path for the explorer
                                                        $fileNameWithCustomer = request('search') . '/' . $file['name'];
                                                    @endphp
                                                    <a href="{{ route('invoices.preview-file', ['path' => $file['path']]) }}"
                                                        target="_blank"
                                                        class="inline-flex items-center px-3 py-1 text-xs text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 font-bold uppercase tracking-wider transition-colors">
                                                        <i class="fas fa-external-link-alt mr-1"></i> Preview
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Queue Status Section (Dynamically shown) -->
                    <div id="queueStatusSection"
                        class="mb-6 hidden bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                            <div class="flex-1 w-full">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-semibold text-blue-800 dark:text-blue-300">WhatsApp
                                        Sending Queue</span>
                                    <span id="queuePercent"
                                        class="text-xs font-bold text-blue-700 dark:text-blue-400">0%</span>
                                </div>
                                <div class="w-full bg-blue-200 dark:bg-blue-800 rounded-full h-2.5">
                                    <div id="queueBar"
                                        class="bg-blue-600 h-2.5 rounded-full transition-all duration-500"
                                        style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between mt-2 text-[10px] uppercase tracking-wider font-bold">
                                    <span id="queuePending" class="text-gray-500">Pending: 0</span>
                                    <span id="queueProcessing" class="text-yellow-600">Processing: 0</span>
                                    <span id="queueSent" class="text-green-600">Sent: 0</span>
                                    <span id="queueFailed" class="text-red-600">Failed: 0</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="location.reload()"
                                    class="p-2 text-blue-600 hover:bg-blue-100 rounded-full transition-colors"
                                    title="Refresh Page">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div id="queueFailures"
                            class="mt-3 text-xs border-t border-blue-100 dark:border-blue-800 pt-2 hidden">
                            <span class="font-bold text-red-600 block mb-1">Recent Failures:</span>
                            <div id="failureList" class="space-y-1"></div>
                        </div>
                    </div>

                    {{-- Date range filter — defaults to today's uploads. Color tokens
                         match the WhatsApp Queue Status section and the Export modal
                         below so the filter card blends with the surrounding theme
                         in both light and dark modes. --}}
                    <div
                        class="mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <form method="GET" action="{{ route('invoices.index') }}"
                            class="flex flex-wrap items-end gap-3">
                            @if (request('search'))
                                <input type="hidden" name="search" value="{{ request('search') }}">
                            @endif
                            <div class="flex flex-col">
                                <label for="inv-from"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                                <input type="date" name="from" id="inv-from" value="{{ $filterFrom ?? '' }}"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                            </div>
                            <div class="flex flex-col">
                                <label for="inv-to"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                                <input type="date" name="to" id="inv-to" value="{{ $filterTo ?? '' }}"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="py-2 px-4 inline-flex justify-center items-center gap-x-1 text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 text-white shadow-sm">
                                    <i class="fas fa-filter mr-1"></i>Apply
                                </button>
                                <a href="{{ route('invoices.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()] + (request('search') ? ['search' => request('search')] : [])) }}"
                                    class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Today
                                </a>
                                <a href="{{ route('invoices.index') }}"
                                    class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Reset
                                </a>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 ml-auto">
                                @if ($filterFrom && $filterTo && $filterFrom === $filterTo)
                                    Showing uploads from <strong
                                        class="text-gray-700 dark:text-gray-200">{{ \Carbon\Carbon::parse($filterFrom)->format('d M Y') }}</strong>
                                @elseif($filterFrom || $filterTo)
                                    Showing
                                    <strong
                                        class="text-gray-700 dark:text-gray-200">{{ $filterFrom ? \Carbon\Carbon::parse($filterFrom)->format('d M Y') : 'beginning' }}</strong>
                                    to
                                    <strong
                                        class="text-gray-700 dark:text-gray-200">{{ $filterTo ? \Carbon\Carbon::parse($filterTo)->format('d M Y') : 'today' }}</strong>
                                @else
                                    Showing <strong class="text-gray-700 dark:text-gray-200">all uploads</strong>
                                @endif
                            </div>
                        </form>
                    </div>

                    <!-- Summary Cards — counts cover ALL filtered invoices, not just
                         what's on the current page of date groups. -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                        <div
                            class="bg-primary-50 dark:bg-primary-900/20 p-4 rounded-lg border border-primary-100 dark:border-primary-800">
                            <div class="text-primary-800 dark:text-primary-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Total Invoices</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->total ?? 0 }}</p>
                            </div>
                        </div>
                        <div
                            class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800">
                            <div class="text-green-800 dark:text-green-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Completed</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->completed ?? 0 }}</p>
                            </div>
                        </div>
                        <div
                            class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800">
                            <div class="text-yellow-800 dark:text-yellow-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Processing</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->processing ?? 0 }}</p>
                            </div>
                        </div>
                        <div
                            class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-100 dark:border-red-800">
                            <div class="text-red-800 dark:text-red-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Failed</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->failed ?? 0 }}</p>
                            </div>
                        </div>
                        {{-- Unsent / Pending — clicking the card body toggles
                             the quick filter via ?unsent_only=1. --}}
                        <div
                            class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800 flex flex-col justify-between">
                            <div class="text-orange-800 dark:text-orange-300">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Unsent / Pending</h3>
                                <p class="text-2xl font-bold mt-1">{{ $stats->unsent ?? 0 }}</p>
                            </div>
                            @if (($stats->unsent ?? 0) > 0)
                                @if ($unsentOnly ?? false)
                                    <a href="{{ route('invoices.index', request()->except(['unsent_only', 'page'])) }}"
                                        class="mt-2 text-[11px] font-semibold text-orange-700 dark:text-orange-200 hover:underline">
                                        <i class="fas fa-times mr-1"></i>Clear filter
                                    </a>
                                @else
                                    <a href="{{ route('invoices.index', array_merge(request()->except('page'), ['unsent_only' => 1])) }}"
                                        class="mt-2 text-[11px] font-semibold text-orange-700 dark:text-orange-200 hover:underline">
                                        <i class="fas fa-paper-plane mr-1"></i>Show only unsent
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($unsentOnly ?? false)
                        <div
                            class="mb-4 px-4 py-3 rounded-md bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 text-sm text-orange-800 dark:text-orange-200 flex items-center justify-between">
                            <div>
                                <i class="fas fa-paper-plane mr-2"></i>
                                <strong>Unsent / Pending filter active</strong> — showing only completed invoices that
                                have NOT been sent on WhatsApp.
                            </div>
                            <a href="{{ route('invoices.index', request()->except(['unsent_only', 'page'])) }}"
                                class="text-xs font-semibold hover:underline">
                                Show all invoices
                            </a>
                        </div>
                    @endif

                    <!-- Invoices Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider w-64">
                                        Customer
                                    </th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Invoice Details
                                    </th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        WhatsApp
                                    </th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Uploaded
                                    </th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            @php
                                // Group all of this page's invoices by upload date. The
// controller paginates DATES (not invoices), so every invoice
// for a visible date is loaded — `$invoices` is a plain
// Collection here, not a paginator.
$groupedByDate = $invoices->groupBy(function ($i) {
    return optional($i->uploaded_at)->format('Y-m-d') ?? '__no_date__';
});

$groupedInvoices = $groupedByDate->map(function ($items) {
    return $items->groupBy('uploaded_by');
});
$today = now()->format('Y-m-d');
$yesterday = now()->subDay()->format('Y-m-d');
                            @endphp

                            @forelse($groupedInvoices as $groupDate => $groupRows)
                                @foreach ($groupRows as $uploadedBy => $groupRowsByUploader)
                                    @php
                                        // Per-day status breakdown + list of completed-but-unsent
                                        // invoice ids so the "Send" button on this accordion can
                                        // POST only this day's pending messages.
$statusCounts = [
    'completed' => 0,
    'processing' => 0,
    'failed' => 0,
    'pending' => 0,
];
$whatsappSent = 0;
$whatsappFailed = 0;
$unsentIds = [];
foreach ($groupRowsByUploader as $row) {
    $s = $row->processing_status ?: 'pending';
    if (!isset($statusCounts[$s])) {
        $statusCounts[$s] = 0;
    }
    $statusCounts[$s]++;

    if (($row->whatsapp_status ?? null) === 'sent') {
        $whatsappSent++;
    }
    if (($row->whatsapp_status ?? null) === 'failed') {
        $whatsappFailed++;
    }

    if (
        $s === 'completed' &&
        !empty($row->pdf_path) &&
        ($row->whatsapp_status ?? null) !== 'sent'
                                            ) {
                                                $unsentIds[] = $row->id;
                                            }
                                        }
                                    @endphp
                                    {{-- Each date group is its own <tbody> so Alpine's `open` state
                                        is naturally scoped to that group — the header `<tr>` and
                                        its detail `<tr>`s share the same scope as siblings. All
                                        groups start collapsed; click a date header to expand. --}}
                                    <tbody x-data="{ open: false }"
                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        {{-- Accordion header — use the same gray-800/gray-100
                                            pair the rest of the app uses for heading text so
                                            the date label keeps strong contrast in both light
                                            and dark themes. --}}
                                        <tr
                                            class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                            <td colspan="6"
                                                class="px-6 py-3 text-xs font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider">
                                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                                    {{-- Left half: chevron + date label + counts. Clicking this part toggles the accordion. --}}
                                                    <div @click="open = !open"
                                                        class="flex items-center cursor-pointer flex-1 min-w-0">
                                                        {{-- Inline transform style — avoids relying on the Tailwind
                                                            `rotate-90` utility being present in the compiled CSS. --}}
                                                        <svg :style="'transform: rotate(' + (open ? 90 : 0) +
                                                        'deg); transition: transform 0.2s ease;'"
                                                            class="w-3.5 h-3.5 mr-2 text-primary-600 dark:text-primary-400 shrink-0"
                                                            xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2.5" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                        <i
                                                            class="far fa-calendar-alt mr-2 text-primary-600 dark:text-primary-400 shrink-0"></i>
                                                        <span class="mr-3 shrink-0">
                                                            @if ($groupDate === '__no_date__')
                                                                No upload date
                                                            @elseif($groupDate === $today)
                                                                Today &middot;
                                                                {{ \Carbon\Carbon::parse($groupDate)->format('d M Y') }}
                                                            @elseif($groupDate === $yesterday)
                                                                Yesterday &middot;
                                                                {{ \Carbon\Carbon::parse($groupDate)->format('d M Y') }}
                                                            @else
                                                                {{ \Carbon\Carbon::parse($groupDate)->format('l, d M Y') }}
                                                            @endif
                                                            by ({{ $row?->uploader?->name ?? 'Unknown' }})
                                                        </span>
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-600 text-white normal-case tracking-normal shrink-0">
                                                            {{ $groupRows->count() }}
                                                            {{ Str::plural('invoice', $groupRows->count()) }}
                                                        </span>

                                                        {{-- Per-day status breakdown. Inline `style="background-color"`
                                                            bypasses any purged Tailwind color class so the badges
                                                            always render the right shade regardless of theme. --}}
                                                        <div
                                                            class="flex items-center gap-1.5 ml-3 normal-case tracking-normal">
                                                            @if ($statusCounts['completed'] > 0)
                                                                <span
                                                                    style="background-color: #16a34a; color: #ffffff;"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold">
                                                                    <i
                                                                        class="fas fa-check-circle mr-1"></i>{{ $statusCounts['completed'] }}
                                                                    Completed
                                                                </span>
                                                            @endif
                                                            @if ($statusCounts['processing'] > 0)
                                                                <span
                                                                    style="background-color: #eab308; color: #ffffff;"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold">
                                                                    <i
                                                                        class="fas fa-spinner mr-1"></i>{{ $statusCounts['processing'] }}
                                                                    Processing
                                                                </span>
                                                            @endif
                                                            @if ($statusCounts['failed'] > 0)
                                                                <span
                                                                    style="background-color: #dc2626; color: #ffffff;"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold">
                                                                    <i
                                                                        class="fas fa-exclamation-circle mr-1"></i>{{ $statusCounts['failed'] }}
                                                                    Failed
                                                                </span>
                                                            @endif
                                                            @if ($statusCounts['pending'] > 0)
                                                                <span
                                                                    style="background-color: #6b7280; color: #ffffff;"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold">
                                                                    <i
                                                                        class="fas fa-clock mr-1"></i>{{ $statusCounts['pending'] }}
                                                                    Pending
                                                                </span>
                                                            @endif
                                                            @if ($whatsappSent > 0)
                                                                <span
                                                                    style="background-color: #059669; color: #ffffff;"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium">
                                                                    <i
                                                                        class="fab fa-whatsapp mr-1"></i>{{ $whatsappSent }}
                                                                    Sent
                                                                </span>
                                                            @endif
                                                            @if ($whatsappFailed > 0)
                                                                <span
                                                                    style="background-color: #b91c1c; color: #ffffff;"
                                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium">
                                                                    <i
                                                                        class="fab fa-whatsapp mr-1"></i>{{ $whatsappFailed }}
                                                                    Failed
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- Right half: per-day "Send" button + collapse hint. --}}
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        @if (!empty($unsentIds))
                                                            <button type="button"
                                                                onclick="sendDayInvoices(this, @js($unsentIds), @js($groupDate))"
                                                                style="background-color: #16a34a; color: #ffffff;"
                                                                onmouseover="this.style.backgroundColor='#15803d'"
                                                                onmouseout="this.style.backgroundColor='#16a34a'"
                                                                class="py-1 px-3 inline-flex items-center gap-x-1 text-xs font-bold rounded-md border border-transparent shadow-sm normal-case tracking-normal"
                                                                title="Send WhatsApp invoices for this day only">
                                                                <i class="fas fa-paper-plane mr-1"></i>Send
                                                                {{ count($unsentIds) }}
                                                            </button>
                                                        @endif
                                                        <span @click="open = !open"
                                                            class="text-[10px] font-medium text-gray-600 dark:text-gray-300 normal-case tracking-normal cursor-pointer">
                                                            <span x-show="!open">Click to expand</span>
                                                            <span x-show="open" x-cloak>Click to collapse</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- Detail rows for this date — visible only when the group is open. --}}
                                        @foreach ($groupRowsByUploader as $invoice)
                                            {{-- @dd($invoice->customer_code) --}}
                                            <tr x-show="open" x-cloak
                                                class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
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
                                                                {{ $invoice->customer_phone ?: 'No Phone' }}
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
                                                <!-- <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('invoices.show', $invoice->id) }}"
                                                    class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded bg-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                    title="View Invoice Details">
                                                        <i class="fas fa-eye mr-1"></i>View
                                                    </a>
                                                    @if ($invoice->processing_status === 'completed' && $invoice->pdf_path)
<a href="{{ route('invoices.download', $invoice->id) }}"
                                                        class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                        title="Download PDF">
                                                            <i class="fas fa-download mr-1"></i>PDF
                                                        </a>
                                                        <button onclick="sendWhatsApp({{ $invoice->id }}, '{{ $invoice->customer_phone }}')"
                                                        class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                        title="Send WhatsApp Message">
                                                            <i class="fas fa-paper-plane mr-1"></i>Send Message
                                                        </button>
@endif
                                                    <a href="{{ route('invoices.customer', $invoice->customer_code) }}"
                                                    class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                                    title="View Customer Invoices">
                                                        <i class="fas fa-user mr-1"></i>Customer
                                                    </a>
                                                    <button type="button"
                                                            @click="openFor({{ $invoice->id }}, @js($invoice->invoice_number))"
                                                            style="background-color: #ca8a04; color: #ffffff;"
                                                            onmouseover="this.style.backgroundColor='#a16207'"
                                                            onmouseout="this.style.backgroundColor='#ca8a04'"
                                                            class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                            title="Attach an existing builty to this invoice">
                                                        <i class="fas fa-truck mr-1" style="color: #ffffff;"></i>Attach Builty
                                                    </button>
                                                    {{-- Delete is hidden once the invoice has been sent on
                                                        WhatsApp — that PDF is now in customers' hands so
                                                        removing the server-side row would create a
                                                        traceability gap. Only unsent rows can be deleted. --}}
                                                    @if (($invoice->whatsapp_status ?? null) !== 'sent')
<form action="{{ route('invoices.destroy', $invoice->id) }}"
                                                            method="POST"
                                                            class="inline-block"
                                                            onsubmit="return confirm('Are you sure you want to delete this invoice?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                                    title="Delete Invoice">
                                                                <i class="fas fa-trash mr-1"></i>Delete
                                                            </button>
                                                        </form>
@endif
                                                </div>
                                            </td> -->
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

                                                                    <button
                                                                        onclick="sendWhatsApp({{ $invoice->id }}, '{{ $invoice->customer_phone }}')"
                                                                        class="flex items-center w-full px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                                                        <i
                                                                            class="fab fa-whatsapp w-5 text-green-600"></i>
                                                                        Send WhatsApp
                                                                    </button>
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
                                        @endforeach
                                    </tbody>
                                @endforeach
                                @empty
                                    <tbody class="bg-white dark:bg-gray-800">
                                        <tr>
                                            <td colspan="6"
                                                class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center py-6">
                                                    <i
                                                        class="fas fa-file-pdf text-4xl mb-4 text-gray-400 dark:text-gray-500"></i>
                                                    <p class="text-lg mb-2">
                                                        @if ($filterFrom || $filterTo)
                                                            No invoices found for the selected date range.
                                                        @else
                                                            No invoices uploaded yet
                                                        @endif
                                                    </p>
                                                    <a href="{{ route('invoices.upload') }}"
                                                        class="mt-2 inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                        Upload Your First Invoice
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforelse
                            </table>
                        </div>

                        <!-- Pagination — pages through date groups, not individual invoices.
                             Each page shows up to 30 days of uploads; every invoice for a
                             visible day is rendered when the day is expanded. -->
                        @if ($datesPage->hasPages())
                            <div class="mt-6">
                                {{ $datesPage->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @include('admin.invoices.partials.attach-builty-modal')
        </div>

        <!-- Export Modal -->
        <div id="exportModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                    onclick="document.getElementById('exportModal').classList.add('hidden')"></div>

                <!-- Modal panel -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form action="{{ route('invoices.export') }}" method="GET">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                                        id="modal-title">
                                        Export Invoices
                                    </h3>
                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start
                                                Date</label>
                                            <input type="date" name="start_date"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">End
                                                Date</label>
                                            <input type="date" name="end_date"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Download Excel
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="document.getElementById('exportModal').classList.add('hidden')">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Progress Modal -->
        <div id="progressModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                                    id="modal-title">
                                    Sending WhatsApp Invoices
                                </h3>
                                <div class="mt-4">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2" id="progressStatus">
                                        Preparing to send...
                                    </p>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                        <div id="progressBar" class="bg-green-600 h-2.5 rounded-full" style="width: 0%">
                                        </div>
                                    </div>
                                    <div id="progressDetail"
                                        class="mt-4 text-xs text-gray-500 dark:text-gray-400 max-h-40 overflow-y-auto">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" id="closeProgressBtn"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm hidden"
                            onclick="location.reload()">
                            Close & Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let statusPollingInterval = null;

            document.addEventListener('DOMContentLoaded', function() {
                // Check status immediately on load
                checkQueueStatus();
                // Start polling if needed
                startStatusPolling();
            });

            function checkQueueStatus() {
                fetch('{{ route('invoices.whatsapp-status') }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateQueueUI(data.stats, data.recent_failures);
                        }
                    })
                    .catch(error => console.error('Status check failed:', error));
            }

            function updateQueueUI(stats, failures) {
                const section = document.getElementById('queueStatusSection');
                const total = parseInt(stats.total) || 0;
                const pending = parseInt(stats.pending) || 0;
                const processing = parseInt(stats.processing) || 0;
                const sent = parseInt(stats.sent) || 0;
                const failed = parseInt(stats.failed) || 0;

                const active = pending > 0 || processing > 0;

                if (total > 0 && (active || failed > 0)) {
                    section.classList.remove('hidden');

                    document.getElementById('queuePending').innerText = `Pending: ${pending}`;
                    document.getElementById('queueProcessing').innerText = `Processing: ${processing}`;
                    document.getElementById('queueSent').innerText = `Sent: ${sent}`;
                    document.getElementById('queueFailed').innerText = `Failed: ${failed}`;

                    const completedCount = sent + failed;
                    const percent = total > 0 ? Math.round((completedCount / total) * 100) : 0;
                    document.getElementById('queueBar').style.width = `${percent}%`;
                    document.getElementById('queuePercent').innerText = `${percent}%`;

                    // Show failures if any
                    const failureSection = document.getElementById('queueFailures');
                    const failureList = document.getElementById('failureList');
                    if (failures && failures.length > 0) {
                        failureSection.classList.remove('hidden');
                        failureList.innerHTML = failures.map(f =>
                            `<div class="flex justify-between text-red-500 bg-red-50 dark:bg-red-900/20 p-1 rounded">
                            <span>${f.customer_name}</span>
                            <span class="italic truncate ml-2 max-w-[200px]" title="${f.whatsapp_error}">${f.whatsapp_error}</span>
                        </div>`
                        ).join('');
                    } else {
                        failureSection.classList.add('hidden');
                    }

                    // If no active jobs but we have results, maybe stop polling after a while
                    if (!active) {
                        stopStatusPolling();
                    } else {
                        startStatusPolling();
                    }
                } else {
                    section.classList.add('hidden');
                    stopStatusPolling();
                }
            }

            function startStatusPolling() {
                if (!statusPollingInterval) {
                    statusPollingInterval = setInterval(checkQueueStatus, 5000); // Every 5 seconds
                }
            }

            function stopStatusPolling() {
                if (statusPollingInterval) {
                    clearInterval(statusPollingInterval);
                    statusPollingInterval = null;
                }
            }

            function showToastSuccess(msg) {
                window.dispatchEvent(new CustomEvent('toast-success', {
                    detail: msg
                }));
            }

            function showToastError(msg) {
                window.dispatchEvent(new CustomEvent('toast-error', {
                    detail: msg
                }));
            }

            function editPhoneNumber(id, currentPhone) {
                const newPhone = prompt("Enter new phone number (e.g. 923321234567):", currentPhone || "");
                if (newPhone !== null) {
                    fetch(`{{ url('app/admin/invoices') }}/${id}/update-phone`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                phone: newPhone
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById(`phone-${id}`).innerText = data.phone;
                                showToastSuccess('Phone number updated successfully');
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToastError('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToastError('An error occurred while updating the phone number');
                        });
                }
            }

            function sendWhatsApp(id, phone) {
                if (!phone) {
                    showToastError('Please set a phone number first');
                    return;
                }

                const template = 'invoice_urdu';

                if (!confirm(`Send Urdu message to ${phone}?`)) return;

                const btn = event.currentTarget;
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Queueing...';

                fetch(`{{ url('app/admin/invoices') }}/${id}/send-whatsapp`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            phone: phone,
                            template: template
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToastSuccess('Message queued successfully!');
                            checkQueueStatus();
                            startStatusPolling();
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showToastError('Error: ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = originalContent;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToastError('An error occurred while queuing the message');
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    });
            }

            // Per-day accordion send — POSTs only the supplied invoice IDs to the
            // bulk-send endpoint. The endpoint already accepts an optional
            // invoice_ids array; without it the legacy "send everything" behavior
            // kicks in.
            function sendDayInvoices(btn, invoiceIds, groupDate) {
                if (!Array.isArray(invoiceIds) || invoiceIds.length === 0) {
                    showToastError('Nothing to send for this day.');
                    return;
                }
                if (!confirm(`Send ${invoiceIds.length} unsent invoice(s) for ${groupDate}?`)) return;

                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending…';

                fetch('{{ route('invoices.bulk-send') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            template: 'invoice_urdu',
                            invoice_ids: invoiceIds
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToastSuccess(data.message);
                            checkQueueStatus();
                            startStatusPolling();
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showToastError('Error: ' + (data.message ?? 'unknown'));
                            btn.disabled = false;
                            btn.innerHTML = originalContent;
                        }
                    })
                    .catch(err => {
                        console.error('Per-day send error:', err);
                        showToastError('Could not initiate send for this day.');
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    });
            }

            function sendAllUnsentInvoices() {
                const template = 'invoice_urdu';

                if (!confirm(`This will send Urdu messages to all unsent invoices. Continue?`)) return;

                const btn = document.getElementById('sendAllBtn');
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Starting...';

                fetch('{{ route('invoices.bulk-send') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            template: template
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToastSuccess(data.message);
                            checkQueueStatus();
                            startStatusPolling();
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showToastError('Error: ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = originalContent;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToastError('An error occurred while initiating bulk send');
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    });
            }

            function toggleInvoiceList(id) {

                // Close other open dropdowns
                document.querySelectorAll('[id^="invoice-list-"]').forEach(el => {
                    if (el.id !== 'invoice-list-' + id) {
                        el.classList.add('hidden');
                    }
                });

                document
                    .getElementById('invoice-list-' + id)
                    .classList.toggle('hidden');
            }

            // Close when clicking outside
            document.addEventListener('click', function(e) {

                if (!e.target.closest('.relative')) {
                    document.querySelectorAll('[id^="invoice-list-"]').forEach(el => {
                        el.classList.add('hidden');
                    });
                }

            });
        </script>
    </x-app-layout>
