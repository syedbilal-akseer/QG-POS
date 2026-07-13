<x-layout :pageTitle="$pageTitle">
    <div class="container mx-auto px-4 py-8">
        <!-- Error Display -->
        <div class="max-w-2xl mx-auto">
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-8 text-center">
                <svg class="h-16 w-16 text-red-600 dark:text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>

                <h2 class="text-2xl font-bold text-red-900 dark:text-red-100 mb-2">
                    Failed to Load BI Dashboard
                </h2>

                <p class="text-red-700 dark:text-red-300 mb-6">
                    {{ $error }}
                </p>

                <div class="space-y-3">
                    <p class="text-sm text-red-600 dark:text-red-400">
                        Please ensure:
                    </p>
                    <ul class="text-sm text-left text-red-700 dark:text-red-300 space-y-2 inline-block">
                        <li>✓ Power BI credentials are configured correctly in .env</li>
                        <li>✓ POWERBI_WORKSPACE_ID and POWERBI_REPORT_ID are set</li>
                        <li>✓ Azure AD app has Power BI service permissions</li>
                        <li>✓ The report exists and is published to the workspace</li>
                    </ul>
                </div>

                <div class="mt-8 flex gap-4 justify-center">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg transition">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Dashboard
                    </a>

                    <button onclick="window.location.reload()" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Retry
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layout>
