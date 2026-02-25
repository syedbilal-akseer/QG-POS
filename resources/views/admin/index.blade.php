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
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Total Amount:</span>
                            <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">Rs {{ number_format($stats['receipts']['total_amount'], 2) }}</span>
                        </div>
                    </div>
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

        @if(!$permissions['show_orders'] && !$permissions['show_products'] && !$permissions['show_price_lists'] && !$permissions['show_receipts'] && !$permissions['show_visits'] && !$permissions['show_customers'])
        <!-- Other users who don't have any sections -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
            <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4" style="padding-top:20px">Welcome to the Dashboard</h3>
            <p class="text-gray-600 dark:text-gray-400" style="padding-bottom:20px">Use the navigation menu to access your available features.</p>
        </div>
        @endif
    </div>
</x-layout>
