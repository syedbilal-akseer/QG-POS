<div x-cloak x-data="{ showSuccess: false, showError: false, message: '', timeout: 5000 }" aria-live="assertive"
     class="fixed top-[67px] right-1 flex flex-col space-y-4 z-50"
     @toast-success.window="showSuccess = true; message = $event.detail; setTimeout(() => { showSuccess = false; }, timeout);"
     @toast-error.window="showError = true; message = $event.detail; setTimeout(() => { showError = false; }, timeout);"
     x-init="
         @if (session('success'))
             showSuccess = true;
             message = '{{ session('success') }}';
             setTimeout(() => { showSuccess = false; }, timeout);
         @endif
         @if (session('error'))
             showError = true;
             message = '{{ session('error') }}';
             setTimeout(() => { showError = false; }, timeout);
         @endif
     ">

    <div x-show="showSuccess || showError" x-transition:enter="transform ease-out duration-300 transition"
         x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
         x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-800 dark:ring-gray-700">

        <!-- Success Toast -->
        <div x-show="showSuccess" class="p-4 flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-green-400 dark:text-green-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-3 flex-1 pt-0.5">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Success</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="message"></p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <button type="button" @click="showSuccess = false"
                        class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300 dark:focus:ring-indigo-600">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Error Toast -->
        <div x-show="showError" class="p-4 flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-red-500 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div class="ml-3 flex-1 pt-0.5">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Error</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="message"></p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <button type="button" @click="showError = false"
                        class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300 dark:focus:ring-indigo-600">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>
