<div class="mx-auto mt-2">
    <div class="mb-6">
        <x-secondary-button onclick="window.history.back();"
            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-neutral-700 hover:bg-gray-300 dark:hover:bg-neutral-600 transition duration-150 ease-in-out rounded-lg">
            {{ __('Go Back') }}
        </x-secondary-button>
    </div>

    <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">Expense Details</h2>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Visit ID</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $expense->visit_id }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Expense Type</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                    {{ ucwords(str_replace('_', ' ', $expense->expense_type)) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Total</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $expense->total }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Comments</dt>
                <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $expense->comments }}</dd>
            </div>
        </dl>

        <!-- Expense Details -->
        <div class="mt-8">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Expense Breakdown</h3>
            @if (!empty($expense->expense_details))
                <div class="overflow-x-auto mt-4">
                    <table
                        class="w-full border-collapse border border-gray-300 dark:border-neutral-700 text-gray-900 dark:text-gray-100">
                        <thead>
                            <tr class="bg-gray-200 dark:bg-neutral-800 text-left">
                                <th class="px-4 py-2 border border-gray-300 dark:border-neutral-700">Date</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-neutral-700">Description</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-neutral-700">Amount</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-neutral-700">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expense->expense_details as $detail)
                                <tr>
                                    <td class="px-4 py-2 border border-gray-300 dark:border-neutral-700">
                                        {{ $detail['date'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 border border-gray-300 dark:border-neutral-700">
                                        {{ $detail['description'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 border border-gray-300 dark:border-neutral-700">
                                        {{ $detail['amount'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 border border-gray-300 dark:border-neutral-700">
                                        {{ $detail['details'] ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 mt-4">No expense breakdown available.</p>
            @endif
        </div>

        <!-- Attachments -->
        <div class="mt-8" x-data="{ preview: null }">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mt-8">Attachments</h3>
            @if (!empty($expense->attachments))
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    @foreach ($expense->attachments as $attachment)
                        <div
                            class="border border-gray-300 dark:border-neutral-700 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
                            <div class="bg-gray-100 dark:bg-neutral-800 p-4">
                                @if (Str::endsWith($attachment, ['.jpg', '.jpeg', '.png', '.gif']))
                                    <img src="{{ asset('storage/' . $attachment) }}" alt="Attachment Preview"
                                        class="w-full h-32 object-cover rounded-md cursor-pointer"
                                        @click="preview = '{{ asset('storage/' . $attachment) }}'">
                                @else
                                    <div class="flex items-center justify-center w-full h-32 bg-gray-200 dark:bg-neutral-700 cursor-pointer"
                                        @click="preview = '{{ asset('storage/' . $attachment) }}'">
                                        <x-heroicon-s-document class="w-10 h-10 text-gray-500 dark:text-gray-300" />
                                    </div>
                                @endif
                            </div>
                            <div class="p-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">
                                    {{ basename($attachment) }}
                                </p>
                                <a href="{{ asset('storage/' . $attachment) }}" target="_blank"
                                    class="text-primary-500 hover:underline text-sm">
                                    View / Download
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 mt-4">No attachments available.</p>
            @endif

            <!-- Preview Modal -->
            <div x-show="preview" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
                x-cloak>
                <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-lg overflow-hidden w-11/12 max-w-3xl">
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-neutral-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Attachment Preview</h2>
                        <button @click="preview = null"
                            class="text-gray-500 hover:text-gray-900 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-4">
                        <template
                            x-if="preview && (preview.endsWith('.jpg') || preview.endsWith('.jpeg') || preview.endsWith('.png') || preview.endsWith('.gif'))">
                            <img :src="preview" alt="Preview"
                                class="w-full h-auto max-h-[70vh] object-contain rounded-md">
                        </template>
                        <template
                            x-if="preview && !(preview.endsWith('.jpg') || preview.endsWith('.jpeg') || preview.endsWith('.png') || preview.endsWith('.gif'))">
                            <div class="text-center py-8">
                                <x-heroicon-s-document-text class="w-20 h-20 text-gray-400 mx-auto" />
                                <p class="text-gray-600 dark:text-gray-300 mt-2">Non-image attachment preview not
                                    available</p>
                                <a :href="preview" download
                                    class="mt-4 inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                    <x-heroicon-s-arrow-down-tray class="w-5 h-5 mr-2" />
                                    Download Attachment
                                </a>
                            </div>
                        </template>

                    </div>
                    <div class="p-4 bg-gray-100 dark:bg-neutral-800 text-right">
                        <button @click="preview = null"
                            class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Section -->
        <div class="mt-8">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Approval Status</h3>
            <dl class="grid grid-cols-4 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Status</dt>
                    <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ ucwords($expense->status) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Line Manager Approval</dt>
                    <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                        @if (is_null($expense->line_manager_approval))
                            Pending
                        @elseif ($expense->line_manager_approval === 0)
                            Not Approved
                        @elseif ($expense->line_manager_approval === 1)
                            Approved
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">HOD Approval</dt>
                    <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">
                        @if (is_null($expense->hod_approval))
                            Pending
                        @elseif ($expense->hod_approval === 0)
                            Not Approved
                        @elseif ($expense->hod_approval === 1)
                            Approved
                        @endif
                    </dd>
                </div>

                @if ($expense->rejection_reason)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">Rejection Reason</dt>
                        <dd class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $expense->rejection_reason }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>
