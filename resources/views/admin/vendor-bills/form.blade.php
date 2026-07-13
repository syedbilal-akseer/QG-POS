<x-app-layout>
    <x-toast />
    @php
        $isEdit = (bool) $bill;
        $action = $isEdit ? route('vendor-bills.update', $bill) : route('vendor-bills.store');
        // Shared input class so every field renders with the same padding /
        // border / focus ring — previously some fields collapsed to their
        // intrinsic size on the form.
        $inputCls = 'block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm';
    @endphp

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                        {{ $isEdit ? 'Edit & Resubmit Bill' : 'New Vendor Bill' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $isEdit
                            ? 'Update the details below; on save the bill goes back to CMD for approval.'
                            : 'Fill in the bill details and attach the supporting documents. After submit the bill enters the CMD approval queue.' }}
                    </p>
                </div>
                <a href="{{ route('vendor-bills.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline whitespace-nowrap">
                    <i class="fas fa-arrow-left mr-1"></i>Back to list
                </a>
            </div>

            @if($isEdit && $bill->status === 'rejected')
                <div class="mb-4 px-4 py-3 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-800 dark:text-red-200">
                    <strong>This bill was rejected
                        @if($bill->rejected_by_role) by {{ strtoupper($bill->rejected_by_role) }}@endif.</strong>
                    Update the details / attachments below and submit again — the chain restarts at CMD.
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 px-4 py-3 rounded-md bg-red-50 border border-red-200 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $action }}" enctype="multipart/form-data"
                  class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                @csrf
                @if($isEdit) @method('PUT') @endif

                {{-- ─── Section: Bill details ─── --}}
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
                        Bill Details
                    </h3>

                    <div class="space-y-4">
                        {{-- Vendor (full width — the picker needs the room) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Vendor <span class="text-red-500">*</span>
                            </label>
                            <select name="vendor_id" required class="{{ $inputCls }}">
                                <option value="">— Select vendor —</option>
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" @selected(old('vendor_id', $bill?->vendor_id) == $v->id)>
                                        {{ $v->vendor_name }} ({{ $v->vendor_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Bill Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="bill_number" required maxlength="64"
                                       placeholder="e.g. INV-2026-04211"
                                       value="{{ old('bill_number', $bill?->bill_number) }}"
                                       class="{{ $inputCls }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Bill Date
                                </label>
                                <input type="date" name="bill_date"
                                       value="{{ old('bill_date', optional($bill?->bill_date)->format('Y-m-d')) }}"
                                       class="{{ $inputCls }}">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Amount <span class="text-red-500">*</span>
                            </label>
                            <input type="number" step="0.01" min="0" name="amount" required
                                   inputmode="decimal" placeholder="0.00"
                                   value="{{ old('amount', $bill?->amount) }}"
                                   class="{{ $inputCls }}">
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">All vendor bills are in PKR.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Description / Notes
                            </label>
                            <textarea name="description" rows="3" maxlength="2000"
                                      placeholder="What's the bill for? (optional)"
                                      class="{{ $inputCls }}">{{ old('description', $bill?->description) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- ─── Section: Attachments ─── --}}
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
                        Attachments
                    </h3>

                    @if($isEdit && $bill->attachments->isNotEmpty())
                        <div class="mb-3">
                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Existing files</div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700">
                                @foreach($bill->attachments as $att)
                                    <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                        <a href="{{ route('vendor-bills.attachment', $att) }}" target="_blank" class="text-primary-600 hover:underline flex items-center gap-2 truncate">
                                            <i class="fas fa-paperclip text-gray-400"></i>
                                            <span class="truncate">{{ $att->original_filename }}</span>
                                            <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ number_format(($att->size_bytes ?? 0) / 1024, 1) }} KB</span>
                                        </a>
                                        <label class="text-[11px] text-red-500 flex items-center gap-1 cursor-pointer whitespace-nowrap">
                                            <input type="checkbox" name="remove_attachment_ids[]" value="{{ $att->id }}" class="rounded border-gray-300 text-red-500 focus:ring-red-400">
                                            Remove
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ $isEdit ? 'Add more files' : 'Attach bill / supporting documents' }}
                    </label>
                    <input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg"
                           class="block w-full text-sm text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md p-1.5 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700">
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">PDF / PNG / JPG / JPEG. Max 20 files, 25 MB each.</p>
                </div>

                {{-- ─── Section: Resubmission remarks (edit only) ─── --}}
                @if($isEdit && $bill->status === 'rejected')
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Resubmission remarks <span class="text-xs text-gray-500">(optional)</span>
                        </label>
                        <textarea name="remarks" rows="2"
                                  placeholder="What changed since the last rejection?"
                                  class="{{ $inputCls }}"></textarea>
                    </div>
                @endif

                {{-- ─── Footer actions ─── --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 flex justify-end gap-2">
                    <a href="{{ $isEdit ? route('vendor-bills.show', $bill) : route('vendor-bills.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Cancel
                    </a>
                    <button type="submit"
                            style="background-color:#ea580c;color:#fff;"
                            onmouseover="this.style.backgroundColor='#c2410c'"
                            onmouseout="this.style.backgroundColor='#ea580c'"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold shadow-sm">
                        <i class="fas fa-paper-plane mr-2" style="color:#fff;"></i>
                        {{ $isEdit ? 'Update & Resubmit' : 'Submit for CMD approval' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
