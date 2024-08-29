<div x-cloak x-data="{ showSuccess: false, showError: false, message: '', timeout: 5000 }" class="fixed top-16 right-4 space-y-3 z-50"
    @toast-success.window="showSuccess = true; message = $event.detail; setTimeout(() => { showSuccess = false; }, timeout);"
    @toast-error.window="showError = true; message = $event.detail; setTimeout(() => { showError = false; }, timeout);">

    <!-- Success Toast -->
    <div x-show="showSuccess"
        class="max-w-xs bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-neutral-800 dark:border-neutral-700"
        role="alert" tabindex="-1" aria-labelledby="toast-success-label"
        x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition transform ease-in duration-300"
        x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-2 opacity-0">
        <div class="flex p-4">
            <div class="shrink-0">
                <x-heroicon-o-check-circle class="shrink-0 size-4 text-teal-500 mt-0.5" />
            </div>
            <div class="ms-3">
                <p id="toast-success-label" class="text-sm text-gray-700 dark:text-neutral-400">
                    <span x-text="message"></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Error Toast -->
    <div x-show="showError"
        class="max-w-xs bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-neutral-800 dark:border-neutral-700"
        role="alert" tabindex="-1" aria-labelledby="toast-error-label"
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition transform ease-in duration-300"
        x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-2 opacity-0">
        <div class="flex p-4">
            <div class="shrink-0">
                <x-heroicon-o-x-circle class="shrink-0 size-4 text-red-500 mt-0.5" />
            </div>
            <div class="ms-3">
                <p id="toast-error-label" class="text-sm text-gray-700 dark:text-neutral-400">
                    <span x-text="message"></span>
                </p>
            </div>
        </div>
    </div>
</div>
