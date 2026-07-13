<x-layout pageTitle="Customer Form Events">
    <div class="container mx-auto mt-4 p-4">
        @if(session('success'))
            <div class="mb-4 p-3 rounded bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200">{{ session('success') }}</div>
        @endif

        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Customer Form Events</h1>
                <a href="{{ route('admin.customer-form-events.create') }}"
                   class="px-4 py-2 rounded bg-primary-600 text-white font-semibold hover:bg-primary-700">
                    + New Event
                </a>
            </div>

            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name…"
                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                <select name="status" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                    <option value="">All</option>
                    <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded bg-primary-600 text-white text-sm">Filter</button>
                    <a href="{{ route('admin.customer-form-events.index') }}" class="px-4 py-2 rounded border border-gray-300 dark:border-neutral-600 text-sm text-gray-700 dark:text-gray-200">Reset</a>
                </div>
            </form>

            <div class="border border-gray-200 dark:border-neutral-700 rounded overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-neutral-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Name</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Dates</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Forms</th>
                            <th class="px-3 py-2 text-center text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Active</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-gray-100 dark:divide-neutral-700">
                        @forelse($events as $event)
                            <tr>
                                <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-100">{{ $event->name }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($event->description, 60) }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs">
                                    {{ $event->start_date?->format('d M Y') ?? '—' }} → {{ $event->end_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ $event->forms_count }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($event->is_active)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-gray-300">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.customer-form-events.edit', $event) }}"
                                       class="text-primary-600 dark:text-primary-400 text-xs font-semibold hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.customer-form-events.destroy', $event) }}"
                                          class="inline ml-2" onsubmit="return confirm('Delete this event?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 dark:text-red-400 text-xs font-semibold hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">No events yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $events->links() }}</div>
        </div>
    </div>
</x-layout>
