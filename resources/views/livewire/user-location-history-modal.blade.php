<div>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $user->name }}</span>
                — {{ $histories->count() }} location{{ $histories->count() === 1 ? '' : 's' }} logged
                @if($histories->count() >= 200)
                    <span class="text-xs">(showing latest 200)</span>
                @endif
            </p>
        </div>
    </div>

    @if($histories->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            No location history yet.
        </div>
    @else
        <div class="border border-gray-200 dark:border-neutral-700 rounded-lg overflow-hidden">
            <div style="max-height: 480px; overflow: auto;">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-900 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">#</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">When</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Address</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Latitude</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Longitude</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Map</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-gray-100 dark:divide-neutral-700">
                        @foreach($histories as $i => $h)
                            <tr>
                                <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $i + 1 }}
                                </td>
                                <td class="px-3 py-2 text-xs whitespace-nowrap text-gray-800 dark:text-gray-200">
                                    <div class="font-medium">{{ $h->created_at->format('M d, Y') }}</div>
                                    <div class="text-gray-500 dark:text-gray-400">{{ $h->created_at->format('h:i A') }}</div>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                    {{ $h->address ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-right font-mono text-gray-800 dark:text-gray-200">
                                    {{ number_format((float) $h->lat, 6) }}
                                </td>
                                <td class="px-3 py-2 text-xs text-right font-mono text-gray-800 dark:text-gray-200">
                                    {{ number_format((float) $h->lng, 6) }}
                                </td>
                                <td class="px-3 py-2 text-xs text-center">
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $h->lat }},{{ $h->lng }}"
                                       target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
