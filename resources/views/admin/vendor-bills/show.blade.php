<x-app-layout>
    <x-toast />
    @php
        $user = auth()->user();
        $isOwner    = (int) $bill->uploaded_by === (int) $user->id;
        $canCmd     = ($user->isCmd() || $user->isAdmin())     && $bill->status === 'pending_cmd_approval';
        $canDirector= ($user->isDirector() || $user->isAdmin()) && $bill->status === 'pending_director_approval';
        $canEdit    = ($isOwner || $user->isAdmin()) && in_array($bill->status, ['rejected','draft'], true);
        $statusColor = match ($bill->status) {
            'pending_cmd_approval'      => ['bg' => '#fef3c7', 'fg' => '#92400e'],
            'pending_director_approval' => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
            'approved'                  => ['bg' => '#d1fae5', 'fg' => '#065f46'],
            'rejected'                  => ['bg' => '#fee2e2', 'fg' => '#991b1b'],
            default                     => ['bg' => '#e5e7eb', 'fg' => '#374151'],
        };
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-4 gap-2">
                <div class="flex items-center gap-3">
                    <a href="{{ route('vendor-bills.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </a>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                        Bill #{{ $bill->bill_number }}
                    </h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
                          style="background:{{ $statusColor['bg'] }};color:{{ $statusColor['fg'] }};">
                        {{ $bill->statusLabel() }}
                    </span>
                </div>
                @if($canEdit)
                    <a href="{{ route('vendor-bills.edit', $bill) }}"
                       style="background-color:#ea580c;color:#fff;"
                       onmouseover="this.style.backgroundColor='#c2410c'"
                       onmouseout="this.style.backgroundColor='#ea580c'"
                       class="inline-flex items-center px-3 py-2 rounded text-xs font-semibold">
                        <i class="fas fa-edit mr-1" style="color:#fff;"></i>Edit & Resubmit
                    </a>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Bill details --}}
                <div class="md:col-span-2 bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">Vendor</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $bill->vendor?->vendor_name ?? '—' }}</dd>
                            <dd class="text-[11px] text-gray-500 dark:text-gray-400">{{ $bill->vendor?->vendor_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">Amount</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-bold text-lg">Rs {{ number_format((float) $bill->amount, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">Bill Date</dt>
                            <dd class="text-gray-700 dark:text-gray-200">{{ optional($bill->bill_date)->format('M d, Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">Uploaded</dt>
                            <dd class="text-gray-700 dark:text-gray-200">{{ $bill->created_at->format('M d, Y H:i') }}</dd>
                            <dd class="text-[11px] text-gray-500 dark:text-gray-400">by {{ $bill->uploader?->name ?? '—' }}</dd>
                        </div>
                        @if($bill->cmd_approved_at)
                            <div>
                                <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">CMD approved</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ $bill->cmd_approved_at->format('M d, Y H:i') }}</dd>
                                <dd class="text-[11px] text-gray-500 dark:text-gray-400">by {{ $bill->cmdApprover?->name ?? '—' }}</dd>
                            </div>
                        @endif
                        @if($bill->director_approved_at)
                            <div>
                                <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">Director approved</dt>
                                <dd class="text-gray-700 dark:text-gray-200">{{ $bill->director_approved_at->format('M d, Y H:i') }}</dd>
                                <dd class="text-[11px] text-gray-500 dark:text-gray-400">by {{ $bill->directorApprover?->name ?? '—' }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($bill->description)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Description</dt>
                            <dd class="text-sm text-gray-800 dark:text-gray-100 whitespace-pre-line">{{ $bill->description }}</dd>
                        </div>
                    @endif

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <dt class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-2">Attachments ({{ $bill->attachments->count() }})</dt>
                        @if($bill->attachments->isEmpty())
                            <div class="text-sm text-gray-500 dark:text-gray-400">No files attached.</div>
                        @else
                            <ul class="space-y-2">
                                @foreach($bill->attachments as $att)
                                    <li class="flex items-center justify-between gap-2 px-3 py-2 rounded border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <i class="far fa-file-pdf text-red-500"></i>
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ $att->original_filename }}</div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                    {{ number_format(($att->size_bytes ?? 0) / 1024, 1) }} KB · uploaded by {{ $att->uploader?->name ?? '—' }}
                                                </div>
                                            </div>
                                        </div>
                                        <a href="{{ route('vendor-bills.attachment', $att) }}" target="_blank"
                                           style="background-color:#ea580c;color:#fff;"
                                           onmouseover="this.style.backgroundColor='#c2410c'"
                                           onmouseout="this.style.backgroundColor='#ea580c'"
                                           class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold">
                                            <i class="fas fa-external-link-alt mr-1" style="color:#fff;"></i>Open
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Approval action panel --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 uppercase tracking-wider mb-3">
                        @if($canCmd)        CMD Action
                        @elseif($canDirector) Director Action
                        @else                 Workflow
                        @endif
                    </h3>

                    @if($canCmd || $canDirector)
                        <form method="POST"
                              action="{{ route('vendor-bills.approve', $bill) }}"
                              class="space-y-2 mb-4">
                            @csrf
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Approval remarks (optional)</label>
                            <textarea name="remarks" rows="2"
                                      class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                            <button type="submit"
                                    style="background-color:#059669;color:#fff;"
                                    onmouseover="this.style.backgroundColor='#047857'"
                                    onmouseout="this.style.backgroundColor='#059669'"
                                    class="w-full inline-flex justify-center items-center px-3 py-2 rounded text-sm font-semibold">
                                <i class="fas fa-check mr-1" style="color:#fff;"></i>
                                @if($canCmd) Approve & forward to Director
                                @else        Final Approve
                                @endif
                            </button>
                        </form>

                        <form method="POST"
                              action="{{ route('vendor-bills.reject', $bill) }}"
                              class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Rejection remarks <span class="text-red-500">*</span></label>
                            <textarea name="remarks" rows="2" required
                                      class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                      placeholder="Why is this being rejected?"></textarea>
                            <button type="submit"
                                    style="background-color:#dc2626;color:#fff;"
                                    onmouseover="this.style.backgroundColor='#b91c1c'"
                                    onmouseout="this.style.backgroundColor='#dc2626'"
                                    class="w-full inline-flex justify-center items-center px-3 py-2 rounded text-sm font-semibold">
                                <i class="fas fa-times mr-1" style="color:#fff;"></i>Reject (send back to uploader)
                            </button>
                        </form>
                    @else
                        @if($bill->status === 'rejected')
                            <p class="text-sm text-red-600 dark:text-red-400">Rejected — back in {{ $bill->uploader?->name ?? 'uploader' }}'s queue for edit & resubmit.</p>
                        @elseif($bill->status === 'approved')
                            <p class="text-sm text-green-700 dark:text-green-300"><i class="fas fa-check-circle mr-1"></i>Fully approved.</p>
                        @else
                            <p class="text-sm text-gray-600 dark:text-gray-300">No action available to you at this stage.</p>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Timeline — initial 'submitted' event is hidden because the
                 bill creation date is already shown in the detail card above
                 and the dot was visual noise. Only approval/rejection/
                 resubmission steps appear here. --}}
            <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 uppercase tracking-wider mb-4">Approval Timeline</h3>
                @php $events = $bill->approvals->reject(fn ($e) => $e->action === 'submitted'); @endphp
                @if($events->isEmpty())
                    <div class="text-sm text-gray-500 dark:text-gray-400">No actions recorded yet.</div>
                @else
                    <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
                        @foreach($events as $ev)
                            @php
                                $dot = match ($ev->action) {
                                    'approved'    => '#16a34a',
                                    'rejected'    => '#dc2626',
                                    'resubmitted' => '#d97706',
                                    default       => '#6b7280',
                                };
                                $label = match ($ev->action) {
                                    'approved'    => strtoupper($ev->step) . ' approved',
                                    'rejected'    => strtoupper($ev->step) . ' rejected',
                                    'resubmitted' => 'Resubmitted',
                                    default       => ucfirst($ev->action),
                                };
                            @endphp
                            <li class="mb-5 ml-6 last:mb-0">
                                <span class="absolute -left-2 flex items-center justify-center w-4 h-4 rounded-full" style="background:{{ $dot }};"></span>
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $label }}</span>
                                    <span class="ml-2 text-[11px] text-gray-500 dark:text-gray-400">
                                        by {{ $ev->user?->name ?? '—' }} · {{ $ev->acted_at?->format('M d, Y H:i') }}
                                    </span>
                                </div>
                                @if($ev->remarks)
                                    <div class="mt-1 text-xs text-gray-700 dark:text-gray-200 whitespace-pre-line border-l-2 pl-3" style="border-color:{{ $dot }};">
                                        {{ $ev->remarks }}
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
