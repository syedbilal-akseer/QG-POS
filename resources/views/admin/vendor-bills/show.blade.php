<x-app-layout>
    <x-toast />
    @php
        $user = auth()->user();
        $isOwner     = (int) $bill->uploaded_by === (int) $user->id;
        $canDirector = $user->isDirector() && $bill->status === 'pending_director_approval';
        $canCmd      = $user->isCmd()      && $bill->status === 'pending_cmd_approval';
        $canClose    = $user->isAdmin()    && $bill->status === 'approved';
        $canEdit     = ($isOwner || $user->isAdmin()) && in_array($bill->status, ['rejected', 'draft'], true);
        $statusColor = match ($bill->status) {
            'pending_director_approval' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700'],
            'pending_cmd_approval'      => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700'],
            'approved'                  => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
            'closed'                    => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
            'rejected'                  => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
            default                     => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700'],
        };
        $isCmdUser = $user->isCmd();
        $isDirectorUser = $user->isDirector();
        $lastDirectorRemark = $bill->approvals->last(fn ($e) => $e->step === 'director' && $e->remarks);
    @endphp

    <div class="py-6" x-data="{ tab: 'overview' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <a href="{{ route('vendor-bills.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $isCmdUser ? 'bg-orange-600' : ($isDirectorUser ? 'bg-blue-600' : 'bg-gray-600') }} flex items-center justify-center">
                            <i class="fas fa-bank text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Request Details - {{ $bill->bill_number }}</h1>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColor['bg'] }} {{ $statusColor['text'] }}">
                                {{ $bill->statusLabel() }}
                            </span>
                            @if($bill->status === 'rejected' && $bill->rejected_by_role)
                                <span class="ml-1 text-[11px] text-red-600 dark:text-red-400">(by {{ strtoupper($bill->rejected_by_role) }})</span>
                            @endif
                        </div>
                    </div>
                </div>
                @if($canEdit)
                    <a href="{{ route('vendor-bills.edit', $bill) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg shadow-sm transition-colors">
                        <i class="fas fa-edit"></i> Edit & Resubmit
                    </a>
                @endif
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="flex gap-8">
                    <button type="button" @click="tab = 'overview'"
                            :class="tab === 'overview' ? 'text-orange-600 border-orange-600' : 'text-gray-500 border-transparent hover:text-gray-700'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors">Overview</button>
                    <button type="button" @click="tab = 'attachments'"
                            :class="tab === 'attachments' ? 'text-orange-600 border-orange-600' : 'text-gray-500 border-transparent hover:text-gray-700'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors">Attachments ({{ $bill->attachments->count() }})</button>
                    <button type="button" @click="tab = 'history'"
                            :class="tab === 'history' ? 'text-orange-600 border-orange-600' : 'text-gray-500 border-transparent hover:text-gray-700'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors">Approval History</button>
                </nav>
            </div>

            <!-- Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <div x-show="tab === 'overview'">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Vendor Info -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Vendor Information</h3>
                                <div class="space-y-3">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white">{{ $bill->vendor?->vendor_name ?? '—' }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">
                                        <div>Code: {{ $bill->vendor?->vendor_code ?? '—' }}</div>
                                        <div class="mt-1">Email: {{ $bill->vendor?->email_address ?? '—' }}</div>
                                        <div class="mt-1">City: {{ $bill->vendor?->city ?? '—' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bill Info -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Bill Information</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Invoice #</span>
                                        <span class="text-gray-800 dark:text-white font-medium">{{ $bill->bill_number }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Bill Date</span>
                                        <span class="text-gray-800 dark:text-white">{{ optional($bill->bill_date)->format('M d, Y') ?? '—' }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <span class="text-gray-500 dark:text-gray-400">Total Amount</span>
                                        <span class="text-lg font-bold text-gray-800 dark:text-white">Rs {{ number_format((float) $bill->amount, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Request Info -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Request Information</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Requestor</span>
                                        <span class="text-gray-800 dark:text-white">{{ $bill->uploader?->name ?? '—' }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Request Date</span>
                                        <span class="text-gray-800 dark:text-white">{{ $bill->created_at->format('M d, Y') }}</span>
                                    </div>
                                    @if($bill->director_approved_at)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">Director</span>
                                            <span class="text-gray-800 dark:text-white">{{ $bill->directorApprover?->name ?? '—' }}</span>
                                        </div>
                                    @endif
                                    @if(in_array($bill->status, ['pending_cmd_approval', 'approved', 'closed'], true) && $bill->cmd_deadline_at)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">CMD SLA Deadline</span>
                                            <span class="text-gray-800 dark:text-white">{{ $bill->cmd_deadline_at->format('M d, Y H:i') }}</span>
                                        </div>
                                    @endif
                                    @if($bill->status === 'pending_cmd_approval' && $bill->cmd_deadline_at)
                                        @php $hrs = $bill->cmdHoursRemaining(); @endphp
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">Time Left</span>
                                            @if($hrs < 0)
                                                <span class="text-red-600 font-semibold">Overdue by {{ abs($hrs) }}h</span>
                                            @else
                                                <span class="{{ $hrs < 8 ? 'text-red-600' : ($hrs < 12 ? 'text-yellow-600' : 'text-green-700') }} font-semibold">{{ $hrs }}h left</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if($bill->cmd_approved_at)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">CMD Approved By</span>
                                            <span class="text-gray-800 dark:text-white">{{ $bill->cmdApprover?->name ?? '—' }}</span>
                                        </div>
                                    @endif
                                    @if($bill->closed_at)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500 dark:text-gray-400">Closed By</span>
                                            <span class="text-gray-800 dark:text-white">{{ $bill->closer?->name ?? '—' }} · {{ $bill->closed_at->format('M d, Y H:i') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Description / Purpose</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $bill->description ?? 'No description provided.' }}</p>
                            </div>

                            @if($isCmdUser && $lastDirectorRemark)
                                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Director Comments</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $lastDirectorRemark->remarks }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div x-show="tab === 'attachments'">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                            @if($bill->attachments->isEmpty())
                                <div class="text-sm text-gray-500 dark:text-gray-400">No files attached.</div>
                            @else
                                <ul class="space-y-2">
                                    @foreach($bill->attachments as $att)
                                        <li class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <i class="fas fa-file-pdf text-red-500 text-lg"></i>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ $att->original_filename }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ number_format(($att->size_bytes ?? 0) / 1024, 1) }} KB · uploaded by {{ $att->uploader?->name ?? '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="{{ route('vendor-bills.attachment', $att) }}" target="_blank"
                                               class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-orange-600 hover:bg-orange-700 text-white">
                                                <i class="fas fa-external-link-alt mr-1"></i>Open
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div x-show="tab === 'history'">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                            <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-3 space-y-4">
                                @foreach($bill->approvals as $approval)
                                    <li class="ml-5">
                                        @php
                                            $dotColor = match ($approval->action) {
                                                'approved' => 'bg-green-500',
                                                'rejected' => 'bg-red-500',
                                                'resubmitted' => 'bg-orange-500',
                                                'closed' => 'bg-blue-500',
                                                default => 'bg-gray-500',
                                            };
                                        @endphp
                                        <div class="absolute -left-2.5 w-5 h-5 rounded-full {{ $dotColor }} flex items-center justify-center">
                                            @if($approval->action === 'approved')
                                                <i class="fas fa-check text-white text-[10px]"></i>
                                            @elseif($approval->action === 'rejected')
                                                <i class="fas fa-times text-white text-[10px]"></i>
                                            @elseif($approval->action === 'closed')
                                                <i class="fas fa-check-double text-white text-[10px]"></i>
                                            @else
                                                <i class="fas fa-circle text-white text-[6px]"></i>
                                            @endif
                                        </div>
                                        <div class="mb-1">
                                            <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ strtoupper($approval->step) }} {{ ucfirst($approval->action) }}</span>
                                            <span class="text-xs text-gray-500 ml-2">by {{ $approval->user?->name ?? '—' }} · {{ $approval->acted_at?->format('M d, Y H:i') }}</span>
                                        </div>
                                        @if($approval->remarks)
                                            <div class="text-xs text-gray-600 dark:text-gray-300 pl-3 border-l-2 border-gray-200 dark:border-gray-700">{{ $approval->remarks }}</div>
                                        @endif
                                    </li>
                                @endforeach
                                @if($bill->approvals->isEmpty())
                                    <div class="text-sm text-gray-500 dark:text-gray-400">No actions recorded yet.</div>
                                @endif
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Actions -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                        @if($canDirector || $canCmd)
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">
                                {{ $canDirector ? 'Director Actions' : 'CMD Actions' }}
                            </h3>
                            <div class="space-y-3">
                                <form method="POST" action="{{ route('vendor-bills.approve', $bill) }}" class="space-y-2">
                                    @csrf
                                    <textarea name="remarks" rows="2" placeholder="Approval remarks (optional)"
                                              class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white"></textarea>
                                    <button type="submit"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg shadow-sm transition-colors">
                                        <i class="fas fa-check"></i> {{ $canDirector ? 'Approve & Forward to CMD' : 'Approve & Forward to Admin' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('vendor-bills.reject', $bill) }}" class="space-y-2">
                                    @csrf
                                    <textarea name="remarks" rows="2" required placeholder="Rejection remarks (required)"
                                              class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white"></textarea>
                                    <button type="submit"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-red-600 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                        <i class="fas fa-times"></i> Reject (return to Admin)
                                    </button>
                                </form>
                            </div>
                        @elseif($canClose)
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Admin Action</h3>
                            <form method="POST" action="{{ route('vendor-bills.close', $bill) }}" class="space-y-2">
                                @csrf
                                <textarea name="remarks" rows="2" placeholder="Close-out remarks (optional)"
                                          class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white"></textarea>
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-700 hover:bg-blue-800 rounded-lg shadow-sm transition-colors">
                                    <i class="fas fa-check-double"></i> Close Bill
                                </button>
                            </form>
                        @else
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Workflow</h3>
                            @if($bill->status === 'rejected')
                                <p class="text-sm text-red-600 dark:text-red-400">Rejected — back in Admin's queue for edit & resubmit.</p>
                            @elseif($bill->status === 'approved')
                                <p class="text-sm text-green-700 dark:text-green-300"><i class="fas fa-check-circle mr-1"></i>Fully approved — awaiting Admin close-out.</p>
                            @elseif($bill->status === 'closed')
                                <p class="text-sm text-blue-700 dark:text-blue-300"><i class="fas fa-check-double mr-1"></i>Closed.</p>
                            @else
                                <p class="text-sm text-gray-600 dark:text-gray-300">No actions available to you at this stage.</p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
