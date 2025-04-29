<div class=" mx-auto mt-2">
    <!-- Go Back Button -->
    <div class="mb-6">
        <x-secondary-button onclick="window.history.back();"
            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-neutral-700 hover:bg-gray-300 dark:hover:bg-neutral-600 transition duration-150 ease-in-out rounded-lg">
            {{ __('Go Back') }}
        </x-secondary-button>
    </div>

    <div class="mb-10">
        @livewire('App\Livewire\Widgets\VisitExpensesStatsOverview', ['visit' => $visit])
    </div>

    <!-- Main Container -->
    {{ $this->table }}
</div>
