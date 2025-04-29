<x-layout :pageTitle="$pageTitle">
    <div class="text-center mb-8">
        <h2 class="text-2xl dark:text-gray-300 font-semibold mb-2">Something Awesome is Cooking...</h2>
        <p class="text-gray-600 dark:text-gray-300">Your dashboard is loading, please wait a moment!</p>
    </div>

    <!-- Skeleton Loader for Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
        <!-- Orders Summary -->
        <div class="bg-gray-300 dark:bg-gray-700 animate-pulse h-64 rounded-lg p-6">
            <div class="h-24 bg-gray-400 dark:bg-gray-600 rounded-lg mb-4"></div>
            <div class="h-12 bg-gray-400 dark:bg-gray-600 rounded-lg mb-2"></div>
            <div class="h-12 bg-gray-400 dark:bg-gray-600 rounded-lg"></div>
        </div>
        <!-- Recent Orders -->
        <div class="bg-gray-300 dark:bg-gray-700 animate-pulse h-64 rounded-lg p-6">
            <div class="h-24 bg-gray-400 dark:bg-gray-600 rounded-lg mb-4"></div>
            <div class="h-12 bg-gray-400 dark:bg-gray-600 rounded-lg mb-2"></div>
            <div class="h-12 bg-gray-400 dark:bg-gray-600 rounded-lg mb-2"></div>
        </div>
        <!-- Charts -->
        <div class="bg-gray-300 dark:bg-gray-700 animate-pulse h-64 rounded-lg p-6">
            <div class="h-24 bg-gray-400 dark:bg-gray-600 rounded-lg mb-4"></div>
            <div class="h-12 bg-gray-400 dark:bg-gray-600 rounded-lg mb-2"></div>
            <div class="h-12 bg-gray-400 dark:bg-gray-600 rounded-lg"></div>
        </div>
    </div>

    <!-- Skeleton Loader for Detailed Orders -->
    <div class="bg-gray-300 dark:bg-gray-700 animate-pulse rounded-lg p-6 mb-8">
        <div class="h-8 bg-gray-400 dark:bg-gray-600 rounded-lg mb-4 w-1/4"></div>
        <div class="h-40 bg-gray-400 dark:bg-gray-600 rounded-lg"></div>
    </div>

    <!-- Skeleton Loader for Recent Orders Table -->
    <div class="bg-gray-300 dark:bg-gray-700 animate-pulse rounded-lg p-6">
        <div class="h-8 bg-gray-400 dark:bg-gray-600 rounded-lg mb-4 w-1/6"></div>
        <div class="grid grid-cols-1 gap-4">
            <div class="bg-gray-400 dark:bg-gray-600 h-12 rounded-lg"></div>
            <div class="bg-gray-400 dark:bg-gray-600 h-12 rounded-lg"></div>
            <div class="bg-gray-400 dark:bg-gray-600 h-12 rounded-lg"></div>
            <div class="bg-gray-400 dark:bg-gray-600 h-12 rounded-lg"></div>
        </div>
    </div>
</x-layout>
