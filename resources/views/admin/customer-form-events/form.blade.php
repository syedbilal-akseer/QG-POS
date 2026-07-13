<x-layout :pageTitle="$event->exists ? 'Edit Event' : 'New Event'">
    <div class="container mx-auto mt-4 p-4">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6 max-w-2xl mx-auto">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                {{ $event->exists ? 'Edit Event' : 'New Customer Form Event' }}
            </h1>

            @if($errors->any())
                <div class="mb-4 p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200">
                    <ul class="list-disc ml-5 text-sm">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ $event->exists ? route('admin.customer-form-events.update', $event) : route('admin.customer-form-events.store') }}">
                @csrf
                @if($event->exists) @method('PUT') @endif

                <div class="mb-3">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $event->name) }}" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">{{ old('description', $event->description) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $event->start_date?->format('Y-m-d')) }}"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $event->end_date?->format('Y-m-d')) }}"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $event->exists ? $event->is_active : true) ? 'checked' : '' }} class="rounded">
                        <span class="ml-2">Active (visible to salespersons)</span>
                    </label>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded bg-primary-600 text-white font-semibold hover:bg-primary-700">
                        {{ $event->exists ? 'Save Changes' : 'Create Event' }}
                    </button>
                    <a href="{{ route('admin.customer-form-events.index') }}" class="px-4 py-2 rounded border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-200">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layout>
