<div class=" mx-auto mt-2">
    <!-- Go Back Button -->
    <div class="mb-6">
        <x-secondary-button onclick="window.history.back();"
            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-neutral-700 hover:bg-gray-300 dark:hover:bg-neutral-600 transition duration-150 ease-in-out rounded-lg">
            {{ __('Go Back') }}
        </x-secondary-button>
    </div>
    <!-- Main Container -->
    <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">Day Tour Plan Details</h2>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Day</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $dayTourPlan->day }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Date</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $dayTourPlan->date }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">From Location</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $dayTourPlan->from_location }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">To Location</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $dayTourPlan->to_location }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Night Stay</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                    {{ $dayTourPlan->is_night_stay ? 'Yes' : 'No' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Key Tasks</dt>
                <dd class="mt-2 text-lg text-gray-900 dark:text-gray-100">
                    @if (!empty($dayTourPlan->key_tasks))
                        <ul class="list-disc list-inside space-y-2">
                            @foreach ($dayTourPlan->key_tasks as $task)
                                <li class="text-gray-800 dark:text-gray-200">{{ $task }}</li>
                            @endforeach
                        </ul>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">No tasks defined</span>
                    @endif
                </dd>
            </div>
        </dl> 

    </div>
</div>
