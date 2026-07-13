<x-app-layout>
    <x-toast />
    @php
        $inputCls = 'block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm';
    @endphp

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ __('New Announcement') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Sends an FCM push notification to the chosen audience. Only users with a registered FCM token receive it.
                    </p>
                </div>
                <a href="{{ route('announcements.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline whitespace-nowrap">
                    <i class="fas fa-arrow-left mr-1"></i>Back to list
                </a>
            </div>

            @if($errors->any())
                <div class="mb-4 px-4 py-3 rounded-md bg-red-50 border border-red-200 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('announcements.store') }}"
                  class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden"
                  x-data="{ target: '{{ old('target_type', 'all') }}' }">
                @csrf

                <div class="px-6 py-5 space-y-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Title <span class="text-red-500">*</span>
                            <span class="text-xs text-gray-500">(appears as the notification headline)</span>
                        </label>
                        <input type="text" name="title" required maxlength="200"
                               value="{{ old('title') }}"
                               placeholder="e.g. System maintenance tonight"
                               class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Body <span class="text-red-500">*</span>
                            <span class="text-xs text-gray-500">(the notification message; up to 2000 characters)</span>
                        </label>
                        <textarea name="body" rows="4" required maxlength="2000"
                                  placeholder="Type the message users will see…"
                                  class="{{ $inputCls }}">{{ old('body') }}</textarea>
                    </div>
                </div>

                <div class="px-6 py-5 space-y-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Audience</label>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="target_type" value="all" x-model="target"
                                       class="text-primary-600 focus:ring-primary-500">
                                Everyone
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="target_type" value="role" x-model="target"
                                       class="text-primary-600 focus:ring-primary-500">
                                By role
                            </label>
                        </div>
                    </div>

                    <div x-show="target === 'role'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                        <select name="target_value" class="{{ $inputCls }}">
                            <option value="">— Select role —</option>
                            @foreach($roles as $val => $label)
                                <option value="{{ $val }}" @selected(old('target_value') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 flex justify-end gap-2">
                    <a href="{{ route('announcements.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Cancel
                    </a>
                    <button type="submit"
                            style="background-color:#ea580c;color:#fff;"
                            onmouseover="this.style.backgroundColor='#c2410c'"
                            onmouseout="this.style.backgroundColor='#ea580c'"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold shadow-sm">
                        <i class="fas fa-paper-plane mr-2" style="color:#fff;"></i>Send Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
