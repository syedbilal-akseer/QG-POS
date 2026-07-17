<x-layout :pageTitle="$pageTitle">
    <div class="container mx-auto px-4 py-8">
        <!-- Welcome Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Dashboard Overview</h2>
            <p class="text-gray-600 dark:text-gray-400">
                Welcome back, {{ $user->name }}!
                @if($user->isCmdKhi())
                    <span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        Karachi Operations
                    </span>
                @elseif($user->isCmdLhr())
                    <span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        Lahore Operations
                    </span>
                @elseif($user->isScmLhr())
                    <span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        Lahore SCM
                    </span>
                @elseif($user->isAdmin())
                    <span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        Administrator
                    </span>
                @endif
            </p>
        </div>

        <!-- Orders Section -->
        @if($permissions['show_orders'])
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                @if($user->isSalesPerson() || $user->isHOD() || $user->isManager())
                    My Orders
                @else
                    Orders
                @endif
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Total Orders</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['orders']['total']) }}</p>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Pending Orders</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['orders']['pending']) }}</p>
                        </div>
                        <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Synced to Oracle</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['orders']['synced']) }}</p>
                        </div>
                        <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Customers Section -->
        @if($permissions['show_customers'])
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                @if($user->isSalesPerson() || $user->isHOD() || $user->isManager())
                    My Customers
                @else
                    Customers
                @endif
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Total Customers</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['customers']['total']) }}</p>
                        </div>
                        <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">With Recent Orders</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['customers']['with_orders']) }}</p>
                        </div>
                        <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Orders in last 6 months</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Products Section -->
        @if($permissions['show_products'])
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                Products
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Total Products</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['products']['total']) }}</p>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Active Products</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['products']['active']) }}</p>
                        </div>
                        <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Price Lists Section -->
        @if($permissions['show_price_lists'])
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Price Lists
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Total Items</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['price_lists']['total']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Corporate</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['price_lists']['corporate']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Trade</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($stats['price_lists']['trade']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Wholesaler</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['price_lists']['wholesaler']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Changed</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($stats['price_lists']['changed']) }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Receipts Section -->
        @if($permissions['show_receipts'])
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                </svg>
                Receipts
                @if($user->isCmdKhi())
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">(Karachi)</span>
                @elseif($user->isCmdLhr() || $user->isScmLhr())
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">(Lahore)</span>
                @endif
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Total Receipts</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['receipts']['total']) }}</p>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Pending:</span>
                            <span class="text-sm font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format($stats['receipts']['pending']) }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Pushed to Oracle</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['receipts']['pushed']) }}</p>
                        </div>
                        <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    @if(!$user->isCmdKhi() && !$user->isCmdLhr())
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Total Amount:</span>
                            <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">Rs {{ number_format($stats['receipts']['total_amount'], 2) }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Customer Visits Section -->
        @if($permissions['show_visits'])
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                @if($user->isSalesPerson() || $user->isHOD() || $user->isManager())
                    My Visits
                @else
                    Customer Visits
                @endif
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Total Visits</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['visits']['total']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Today's Visits</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['visits']['today']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Completed</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['visits']['completed']) }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- ────────────── Top Salespeople per Location (role-gated) ────────────── --}}
        {{-- Each location (Karachi / Lahore) gets one row with Orders and Receipts
             cards side by side. Cards the user can't access are skipped; a row
             without any visible card is hidden entirely. --}}
        @if($dashAccess['any'] ?? false)
            {{-- Leaderboard filters — date range + single-salesperson dropdown.
                 Defaults to the current month. Submits as GET so the URL can be
                 bookmarked / shared. Styled to match the Orders-page filter row. --}}
            <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                 x-data="{
                     spOpen: false,
                     spSearch: '',
                     spLabel: @js(
                         $leaderboardFilters['salesperson_id']
                             ? ($leaderboardFilters['salesperson_options'][$leaderboardFilters['salesperson_id']] ?? 'All salespeople')
                             : 'All salespeople'
                     )
                 }">
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col">
                        <label for="dash-from" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">From</label>
                        <input type="date" name="from" id="dash-from"
                               value="{{ $leaderboardFilters['from'] ?? '' }}"
                               class="rounded border-gray-300 dark:bg-neutral-700 dark:border-neutral-600 dark:text-gray-100 text-sm">
                    </div>
                    <div class="flex flex-col">
                        <label for="dash-to" class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">To</label>
                        <input type="date" name="to" id="dash-to"
                               value="{{ $leaderboardFilters['to'] ?? '' }}"
                               class="rounded border-gray-300 dark:bg-neutral-700 dark:border-neutral-600 dark:text-gray-100 text-sm">
                    </div>
                    <div class="flex flex-col flex-1 min-w-[240px] relative">
                        <label class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Salesperson</label>
                        {{-- Hidden field is what actually submits; the Alpine combobox just steers it. --}}
                        <input type="hidden" name="salesperson_id" :value="$refs.spInput?.dataset.value || ''"
                               value="{{ $leaderboardFilters['salesperson_id'] ?? '' }}" x-ref="spHidden">
                        <button type="button" @click="spOpen = !spOpen; if (spOpen) $nextTick(() => $refs.spSearchInput.focus())"
                                class="text-left rounded border border-gray-300 dark:border-neutral-600 dark:bg-neutral-700 dark:text-gray-100 text-sm px-3 py-1.5 flex justify-between items-center">
                            <span x-text="spLabel" class="truncate"></span>
                            <svg class="w-4 h-4 text-gray-400 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="spOpen" @click.outside="spOpen = false" x-cloak
                             class="absolute z-20 top-[calc(100%+4px)] left-0 right-0 bg-white dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700 rounded shadow-lg max-h-72 overflow-hidden flex flex-col">
                            <input type="text" x-model="spSearch" x-ref="spSearchInput" placeholder="Search…"
                                   class="border-0 border-b border-gray-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-100 text-sm px-3 py-2 focus:ring-0">
                            <div class="overflow-y-auto">
                                <button type="button"
                                        @click="$refs.spHidden.value = ''; spLabel = 'All salespeople'; spOpen = false;"
                                        class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700 text-gray-700 dark:text-gray-200">
                                    All salespeople
                                </button>
                                @foreach($leaderboardFilters['salesperson_options'] ?? [] as $id => $name)
                                    <button type="button"
                                            x-show="spSearch === '' || @js(strtolower($name)).includes(spSearch.toLowerCase())"
                                            @click="$refs.spHidden.value = {{ $id }}; spLabel = @js($name); spOpen = false;"
                                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700 {{ ($leaderboardFilters['salesperson_id'] ?? null) === $id ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium' : 'text-gray-700 dark:text-gray-200' }}">
                                        {{ $name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded">
                            Apply
                        </button>
                        <a href="{{ route('dashboard') }}"
                           class="bg-gray-100 hover:bg-gray-200 dark:bg-neutral-700 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-200 text-sm font-medium px-4 py-1.5 rounded">
                            Reset
                        </a>
                    </div>
                </form>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Showing
                    <strong>{{ \Carbon\Carbon::parse($leaderboardFilters['from'] ?? now()->startOfMonth())->format('d M Y') }}</strong>
                    to
                    <strong>{{ \Carbon\Carbon::parse($leaderboardFilters['to'] ?? now()->endOfMonth())->format('d M Y') }}</strong>
                    @if($leaderboardFilters['salesperson_id'] ?? null)
                        @php
                            $spName = $leaderboardFilters['salesperson_options'][$leaderboardFilters['salesperson_id']] ?? null;
                        @endphp
                        @if($spName)
                            — salesperson: <strong>{{ $spName }}</strong>
                        @endif
                    @endif
                    · all order statuses included
                </div>
            </div>

            @livewire('dashboard.sales-leaderboard', [
                'access'         => $dashAccess,
                'detail'         => $dashAccess['detail'] ?? true,
                'startDate'      => $leaderboardFilters['from'],
                'endDate'        => $leaderboardFilters['to'],
                'salespersonIds' => ($leaderboardFilters['salesperson_id'] ?? null) ? [$leaderboardFilters['salesperson_id']] : [],
            ], key('dashboard-sales-leaderboard-' . $leaderboardFilters['from'] . '-' . $leaderboardFilters['to'] . '-' . ($leaderboardFilters['salesperson_id'] ?? 'all')))
        @endif

        @if(!$permissions['show_orders'] && !$permissions['show_products'] && !$permissions['show_price_lists'] && !$permissions['show_receipts'] && !$permissions['show_visits'] && !$permissions['show_customers'])
        <!-- Other users who don't have any sections -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
            <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4" style="padding-top:20px">Welcome to the Dashboard</h3>
            <p class="text-gray-600 dark:text-gray-400" style="padding-bottom:20px">Use the navigation menu to access your available features.</p>
        </div>
        @endif
    </div>
</x-layout>
