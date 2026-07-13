<x-app-layout>
    <x-toast />
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ __('Announcement') }}</h2>
                <a href="{{ route('announcements.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline whitespace-nowrap">
                    <i class="fas fa-arrow-left mr-1"></i>Back to list
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Title</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ $announcement->title }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Body</div>
                    <div class="text-sm text-gray-800 dark:text-gray-100 mt-0.5 whitespace-pre-line">{{ $announcement->body }}</div>
                </div>
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Audience</div>
                        <div class="text-sm text-gray-800 dark:text-gray-100 mt-0.5">{{ $announcement->audienceLabel() }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Recipients</div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ number_format($announcement->recipient_count) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sent</div>
                        <div class="text-sm text-gray-800 dark:text-gray-100 mt-0.5">{{ optional($announcement->sent_at)->format('M d, Y H:i') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sent by</div>
                        <div class="text-sm text-gray-800 dark:text-gray-100 mt-0.5">{{ $announcement->creator?->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
