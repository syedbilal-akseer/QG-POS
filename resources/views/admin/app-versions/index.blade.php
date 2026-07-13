<x-app-layout>
    <x-toast />
    @php
        $inputCls = 'block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm';
    @endphp

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ __('App Version') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Set the latest published version. Any mobile client running an older version will be
                    forced to update on the next API call.
                </p>
            </div>

            @if($errors->any())
                <div class="mb-4 px-4 py-3 rounded-md bg-red-50 border border-red-200 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('app-versions.update', $version) }}"
                  class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                @csrf
                @method('PUT')

                <div class="px-6 py-5">
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mb-3">
                        Currently
                        <span class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $version->latest_version }}</span>
                        — last updated
                        {{ $version->updated_at?->diffForHumans() ?? 'never' }}
                        @if($version->updater) by {{ $version->updater->name }} @endif
                    </div>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Latest version <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="latest_version" required
                           pattern="^\d+(\.\d+){0,3}$"
                           value="{{ old('latest_version', $version->latest_version) }}"
                           placeholder="e.g. 1.0.1"
                           class="{{ $inputCls }}">
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Use dot-separated numerics (1–4 segments). Example:
                        <span class="font-mono">1.0.1</span>, <span class="font-mono">1.2.3</span>, <span class="font-mono">2.0.0.4</span>.
                    </p>
                </div>

                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 flex justify-end">
                    <button type="submit"
                            style="background-color:#ea580c;color:#fff;"
                            onmouseover="this.style.backgroundColor='#c2410c'"
                            onmouseout="this.style.backgroundColor='#ea580c'"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold shadow-sm">
                        <i class="fas fa-save mr-2" style="color:#fff;"></i>Save Version
                    </button>
                </div>
            </form>

            <div class="mt-6 p-4 rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-xs text-blue-900 dark:text-blue-200">
                <div class="font-semibold mb-1"><i class="fas fa-info-circle mr-1"></i>How it works</div>
                <ul class="list-disc list-inside space-y-1">
                    <li>Mobile sends an <span class="font-mono">X-App-Version</span> header on every authenticated API call.</li>
                    <li>If that version is lower than the value above, the API responds with HTTP <strong>426 Upgrade Required</strong>.</li>
                    <li>The same version applies to both Android and iOS — the app isn't on a store, so the install URL is left blank.</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
