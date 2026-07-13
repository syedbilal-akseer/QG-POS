<div class=" mx-auto mt-2">
    <div class="mb-6 flex justify-between items-center">
        <!-- Go Back Button on the Left -->
        <x-secondary-button onclick="window.history.back();"
            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-neutral-700 hover:bg-gray-300 dark:hover:bg-neutral-600 transition duration-150 ease-in-out rounded-lg">
            {{ __('Go Back') }}
        </x-secondary-button>

        <!-- Tour Plan Status on the Right -->
        <div class="flex items-center">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 me-2">{{ __('Status') }}</p>
            <span class="text-lg font-medium {{ $this->tourPlan->status === 'completed' ? 'text-green-800 bg-green-100' : 'text-yellow-800 bg-yellow-100' }} rounded-md px-2 py-1">
                {{ ucfirst($this->tourPlan->status) }}
            </span>
        </div>
    </div>


    <!-- Main Container -->
    {{ $this->table }}
</div>
