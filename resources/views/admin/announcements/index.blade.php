<x-app-layout>
    <x-toast />
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ __('Announcements') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Push notifications sent through FCM. Each row records when the announcement went out
                        and how many devices it was dispatched to.
                    </p>
                </div>
                <a href="{{ route('announcements.create') }}"
                   style="background-color:#ea580c;color:#fff;"
                   onmouseover="this.style.backgroundColor='#c2410c'"
                   onmouseout="this.style.backgroundColor='#ea580c'"
                   class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold shadow-sm">
                    <i class="fas fa-plus mr-2" style="color:#fff;"></i>New Announcement
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Title</th>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Audience</th>
                                <th class="px-4 py-3 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Recipients</th>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Sent</th>
                                <th class="px-4 py-3 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($announcements as $a)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $a->title }}</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2 max-w-md">{{ $a->body }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600">
                                            {{ $a->audienceLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                        {{ number_format($a->recipient_count) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        <div>{{ optional($a->sent_at)->format('M d, Y H:i') ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">by {{ $a->creator?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('announcements.show', $a) }}"
                                           style="background-color:#ea580c;color:#fff;"
                                           onmouseover="this.style.backgroundColor='#c2410c'"
                                           onmouseout="this.style.backgroundColor='#ea580c'"
                                           class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold">
                                            <i class="fas fa-eye mr-1" style="color:#fff;"></i>View
                                        </a>
                                        <form method="POST" action="{{ route('announcements.destroy', $a) }}" class="inline ml-1"
                                              onsubmit="return confirm('Delete this announcement record? Already-delivered notifications cannot be recalled.');">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    style="background-color:#dc2626;color:#fff;"
                                                    class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold">
                                                <i class="fas fa-trash mr-1" style="color:#fff;"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-bullhorn text-3xl mb-2 text-gray-400 dark:text-gray-500"></i>
                                        <div>No announcements sent yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $announcements->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
