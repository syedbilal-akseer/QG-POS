<x-app-layout>
    <x-toast />
    @php
        $isEdit = (bool) $bill;
        $action = $isEdit ? route('vendor-bills.update', $bill) : route('vendor-bills.store');
    @endphp

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                        {{ $isEdit ? 'Edit & Resubmit Bill' : 'Create AP Request' }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $isEdit
                            ? 'Update the details below; on save the bill goes back to Director for approval.'
                            : 'Fill in the bill details and attach supporting documents. After submit the bill enters the Director approval queue.' }}
                    </p>
                </div>
                <a href="{{ $isEdit ? route('vendor-bills.show', $bill) : route('vendor-bills.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white whitespace-nowrap">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>

            @if($isEdit && $bill->status === 'rejected')
                <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-800 dark:text-red-200">
                    <strong>This bill was rejected @if($bill->rejected_by_role) by {{ strtoupper($bill->rejected_by_role) }} @endif.</strong>
                    Update details and attachments then resubmit — the chain restarts at Director.
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $action }}" enctype="multipart/form-data"
                  class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
                @csrf
                @if($isEdit) @method('PUT') @endif

                <!-- Vendor Selection -->
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Vendor Selection</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Vendor <span class="text-red-500">*</span></label>
                        <select name="vendor_id" required class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                            <option value="">— Select a vendor —</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}" @selected(old('vendor_id', $bill?->vendor_id) == $v->id)>
                                    {{ $v->vendor_name }} ({{ $v->vendor_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Bill Details -->
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Bill Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Invoice / Bill # <span class="text-red-500">*</span></label>
                            <input type="text" name="bill_number" required maxlength="64"
                                   placeholder="e.g., INV-2026-001"
                                   value="{{ old('bill_number', $bill?->bill_number) }}"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bill Date</label>
                            <input type="date" name="bill_date"
                                   value="{{ old('bill_date', optional($bill?->bill_date)->format('Y-m-d')) }}"
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        </div>
                    </div>
                </div>

                <!-- Amount & Description -->
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (PKR) <span class="text-red-500">*</span></label>
                        <div class="relative max-w-xs">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 font-semibold">Rs</span>
                            <input type="number" step="0.01" min="0" name="amount" required
                                   inputmode="decimal" placeholder="0.00"
                                   value="{{ old('amount', $bill?->amount) }}"
                                   class="w-full pl-10 pr-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">All vendor bills are in PKR.</p>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Purpose / Description</label>
                        <textarea name="description" rows="4" maxlength="2000"
                                  placeholder="What is this payment for?"
                                  class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500">{{ old('description', $bill?->description) }}</textarea>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Attachments</h3>

                    @if($isEdit && $bill->attachments->isNotEmpty())
                        <div class="mb-4">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Existing Files</div>
                            <ul class="space-y-2">
                                @foreach($bill->attachments as $att)
                                    <li class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <i class="fas fa-file-pdf text-red-500 text-lg"></i>
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ $att->original_filename }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format(($att->size_bytes ?? 0) / 1024, 1) }} KB</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('vendor-bills.attachment', $att) }}" target="_blank"
                                               class="text-gray-500 hover:text-gray-700 dark:hover:text-white">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <label class="text-sm text-red-500 flex items-center gap-1 cursor-pointer">
                                                <input type="checkbox" name="remove_attachment_ids[]" value="{{ $att->id }}"
                                                       class="rounded border-gray-300 text-red-500 focus:ring-red-400">
                                                Remove
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-orange-500 transition-colors">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <div class="text-sm text-gray-600 dark:text-gray-300 mb-1">Drag and drop files here, or click to browse</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">PDF, PNG, JPG (Max 25MB each)</div>
                        <input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg" class="mt-3 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-600 file:text-white hover:file:bg-orange-700">
                    </div>
                </div>

                @if($isEdit && $bill->status === 'rejected')
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Resubmission Remarks</label>
                        <textarea name="remarks" rows="3"
                                  placeholder="Tell us what changed since last submission"
                                  class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500">{{ old('remarks') }}</textarea>
                    </div>
                @endif

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 flex justify-end gap-3">
                    <a href="{{ $isEdit ? route('vendor-bills.show', $bill) : route('vendor-bills.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2 rounded-lg text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 shadow-sm transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>
                        {{ $isEdit ? 'Update & Resubmit' : 'Submit for Approval' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
