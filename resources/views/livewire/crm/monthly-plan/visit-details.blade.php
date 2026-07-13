<div class="mx-auto mt-2">
    <div class="mb-6">
        <x-secondary-button onclick="window.history.back();"
            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-neutral-700 hover:bg-gray-300 dark:hover:bg-neutral-600 transition duration-150 ease-in-out rounded-lg">
            {{ __('Go Back') }}
        </x-secondary-button>
    </div>

    <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">Visit Details</h2>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Monthly Visit Report ID</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->monthly_visit_report_id }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Day Tour Plan ID</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->day_tour_plan_id }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Customer Name</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->customer_name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Area</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->area }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Contact Person</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->contact_person }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Contact Number</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->contact_no }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Outlet Type</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->outlet_type }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Shop Category</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->shop_category }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Visit Details</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->visit_details }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Findings of the Day</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->findings_of_the_day }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Status</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->status }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Line Manager Approval</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $visit->line_manager_approval }}</dd>
            </div>
        </dl>

        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mt-8">Competitors</h3>
        @if (!empty($visit->competitors))
            <ul class="list-disc list-inside space-y-2 mt-4">
                @foreach ($visit->competitors as $competitor)
                    <li>
                        <strong>Name:</strong> {{ $competitor['name'] ?? 'N/A' }},
                        <strong>Product:</strong> {{ $competitor['product'] ?? 'N/A' }},
                        <strong>Details:</strong> {{ $competitor['details'] ?? 'N/A' }}
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-gray-500 dark:text-gray-400">No competitors data available.</p>
        @endif
    </div>
</div>
