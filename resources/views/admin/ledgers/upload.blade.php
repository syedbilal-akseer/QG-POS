<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Ledger Import') }}
            </h2>
            <a href="{{ route('ledgers.index') }}"
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Back to Ledgers
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Upload Form -->
                    <form action="{{ route('ledgers.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf

                        <div class="space-y-6">
                            <!-- File Upload -->
                            <div>
                                <label for="ledger_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Ledger PDF File *
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-200">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                            <label for="ledger_file" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Upload a file</span>
                                                <input id="ledger_file"
                                                       name="ledger_file"
                                                       type="file"
                                                       accept=".pdf"
                                                       class="sr-only"
                                                       required
                                                       onchange="updateFileName(this)">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">PDF up to 100MB</p>
                                        <p id="fileName" class="text-sm text-green-600 dark:text-green-400 font-medium hidden"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-between">
                                <button type="submit"
                                        id="submitBtn"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-upload mr-2"></i>
                                    <span id="submitText">Process Ledger PDF</span>
                                </button>

                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-clock mr-1"></i>
                                    Large files (500+ pages) may take a few minutes
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Imports — found / imported / duplicate / failed per upload -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-history mr-2"></i>Recent Imports
                    </h3>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full bg-white dark:bg-gray-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">File</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Period</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Found</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Imported</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Duplicate</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Failed</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Uploaded</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($recentImports as $import)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" title="{{ $import->error }}">
                                        <td class="px-4 py-2 max-w-xs">
                                            <div class="truncate text-gray-900 dark:text-gray-100" title="{{ $import->original_filename }}">{{ $import->original_filename }}</div>
                                            @if($import->status === 'failed')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Failed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>Completed
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-700 dark:text-gray-200">
                                            @if($import->period_from && $import->period_to)
                                                {{ $import->period_from->format('d M') }} – {{ $import->period_to->format('d M Y') }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center font-semibold text-gray-900 dark:text-gray-100">{{ $import->customers_found }}</td>
                                        <td class="px-4 py-2 text-center font-semibold text-green-700 dark:text-green-400">{{ $import->imported_count }}</td>
                                        <td class="px-4 py-2 text-center font-semibold text-yellow-700 dark:text-yellow-400">{{ $import->duplicate_count }}</td>
                                        <td class="px-4 py-2 text-center font-semibold text-red-700 dark:text-red-400">{{ $import->failed_count }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-700 dark:text-gray-200">
                                            <div>{{ optional($import->uploaded_at)->format('M d, Y H:i') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">by {{ $import->uploader->name ?? 'Unknown' }}</div>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if($import->imported_count > 0)
                                                <a href="{{ route('ledgers.index', ['import' => $import->id]) }}"
                                                   class="inline-flex items-center px-2 py-1 border border-transparent text-xs leading-4 font-medium rounded text-white bg-primary-600 hover:bg-primary-700">
                                                    <i class="fas fa-arrow-right mr-1"></i>Review &amp; Send
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            No ledger imports yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Blocking overlay — parsing runs synchronously and a 500+ page PDF can
         take a couple of minutes, so make it unmistakable that it's still
         working rather than looking hung. --}}
    <div id="processingOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/70">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-8 max-w-sm w-full mx-4 text-center">
            <i class="fas fa-spinner fa-spin text-4xl text-primary-600 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Processing ledger PDF…</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Splitting pages per customer and matching salespeople. Large files (500+ pages) can take a few minutes — please don't close this tab.
            </p>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = document.getElementById('fileName');

            if (input.files && input.files[0]) {
                const sizeMb = (input.files[0].size / (1024 * 1024)).toFixed(1);
                fileName.textContent = `📄 ${input.files[0].name} (${sizeMb} MB)`;
                fileName.classList.remove('hidden');
            } else {
                fileName.classList.add('hidden');
            }
        }

        document.getElementById('uploadForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');

            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            document.getElementById('processingOverlay').classList.remove('hidden');
        });

        const dropZone = document.getElementById('ledger_file').closest('.border-dashed');

        if (dropZone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('border-blue-400', 'bg-blue-50'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('border-blue-400', 'bg-blue-50'), false);
            });

            dropZone.addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    document.getElementById('ledger_file').files = files;
                    updateFileName(document.getElementById('ledger_file'));
                }
            }, false);
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
    </script>
</x-app-layout>
