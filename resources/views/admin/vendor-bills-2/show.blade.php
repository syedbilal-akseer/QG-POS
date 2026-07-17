<x-app-layout>
    <x-toast />
    @php
        $user = auth()->user();
        $isOwner    = (int) $bill->uploaded_by === (int) $user->id;
        $canCmd     = $user->isCmd()     && $bill->status === 'pending_cmd_approval';
        $canDirector= $user->isDirector() && $bill->status === 'pending_director_approval';
        $canEdit    = ($isOwner || $user->isAdmin()) && in_array($bill->status, ['rejected','draft'], true);
        $statusColor = match ($bill->status) {
            'pending_cmd_approval'      => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'label' => 'Pending CMD Approval'],
            'pending_director_approval' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'label' => 'Pending Director Review'],
            'approved'                  => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Approved'],
            'rejected'                  => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Rejected'],
            default                     => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Unknown'],
        };
        $isCmdUser = $user->isCmd();
        $isDirectorUser = $user->isDirector();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <a href="{{ route('vendor-bills-2.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg {{ $isCmdUser ? 'bg-orange-600' : ($isDirectorUser ? 'bg-blue-600' : 'bg-gray-600') }} flex items-center justify-center">
                                    <i class="fas fa-bank text-white text-lg"></i>
                                </div>
                                <div>
                                    <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Request Details - {{ $bill->bill_number }}</h1>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColor['bg'] }} {{ $statusColor['text'] }}">
                                        {{ $statusColor['label'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-download"></i> Download All
                    </button>
                    @if($canEdit)
                        <a href="{{ route('vendor-bills-2.edit', $bill) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg shadow-sm transition-colors">
                            <i class="fas fa-edit"></i> Edit & Resubmit
                        </a>
                    @endif
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="flex gap-8">
                    @php
                        $tabs = [
                            'overview' => 'Overview',
                            'attachments' => 'Attachments (' . $bill->attachments->count() . ')',
                            'approval' => 'Approval Trail',
                            'history' => 'History',
                            'comments' => 'Comments',
                        ];
                    @endphp
                    @foreach($tabs as $key => $tab)
                        <button class="py-4 text-sm font-medium {{ $loop->first ? 'text-orange-600 border-b-2 border-orange-600' : 'text-gray-500 hover:text-gray-700' }} transition-colors">
                            {{ $tab }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <!-- Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Vendor & Bill Info -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Vendor Info -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Vendor Information</h3>
                            <div class="space-y-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white">{{ $bill->vendor?->vendor_name ?? '—' }}</div>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <div>Code: {{ $bill->vendor?->vendor_code ?? '—' }}</div>
                                    <div class="mt-1">Email: {{ $bill->vendor?->email ?? '—' }}</div>
                                    <div class="mt-1">Address: {{ $bill->vendor?->address ?? '—' }}</div>
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
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Tax Amount</span>
                                    <span class="text-gray-800 dark:text-white">—</span>
                                </div>
                                <div class="flex justify-between text-sm pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-gray-500 dark:text-gray-400">Total Amount</span>
                                    <span class="text-lg font-bold text-gray-800 dark:text-white">Rs {{ number_format((float) $bill->amount, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Request/Payment Info -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">
                                {{ $bill->status === 'approved' ? 'Payment Information' : 'Request Information' }}
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Requestor</span>
                                    <span class="text-gray-800 dark:text-white">{{ $bill->uploader?->name ?? '—' }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Request Date</span>
                                    <span class="text-gray-800 dark:text-white">{{ $bill->created_at->format('M d, Y') }}</span>
                                </div>
                                @if($bill->status === 'approved')
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Approved by</span>
                                        <span class="text-gray-800 dark:text-white">{{ $bill->directorApprover?->name ?? '—' }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Time Left</span>
                                        <span class="text-orange-600 font-semibold">20h 15m (Overdue)</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Priority</span>
                                        <span class="text-gray-800 dark:text-white">High</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Status</span>
                                        <span class="text-green-600 font-medium">Not Synced</span>
                                    </div>
                                @else
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Director</span>
                                        <span class="text-gray-800 dark:text-white">{{ $bill->directorApprover?->name ?? '—' }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Purpose</span>
                                        <span class="text-orange-600 font-medium">Office equipment purchase</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Description & Comments -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Description -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Description / Purpose</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $bill->description ?? 'No description provided.' }}</p>
                        </div>

                        @if($isCmdUser)
                            <!-- Director Comments (for CMD view) -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Director Comments</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300">Looks good. Please process for payment.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Right Column - Actions -->
                <div class="space-y-6">
                    <!-- Action Panel -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                        @if($canCmd || $canDirector)
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">
                                {{ $canCmd ? 'CMD Actions' : 'Director Actions' }}
                            </h3>
                            <div class="space-y-3">
                                @if($canDirector)
                                    <form method="POST" action="{{ route('vendor-bills-2.approve', $bill) }}">
                                        @csrf
                                        <button type="submit"
                                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg shadow-sm transition-colors">
                                            <i class="fas fa-check"></i> Approve Request
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('vendor-bills-2.reject', $bill) }}">
                                        @csrf
                                        <button type="button"
                                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-red-600 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            <i class="fas fa-times"></i> Reject Request
                                        </button>
                                    </form>
                                @endif
                                @if($canCmd)
                                    <form method="POST" action="{{ route('vendor-bills-2.approve', $bill) }}">
                                        @csrf
                                        <button type="submit"
                                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg shadow-sm transition-colors">
                                            <i class="fas fa-check"></i> Approve & Forward
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('vendor-bills-2.reject', $bill) }}">
                                        @csrf
                                        <button type="button"
                                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-red-600 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            <i class="fas fa-times"></i> Return to Requestor
                                        </button>
                                    </form>
                                @endif
                                @if($canCmd && $bill->status === 'approved')
                                    <button class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-700 hover:bg-blue-800 rounded-lg shadow-sm transition-colors">
                                        <i class="fas fa-check-double"></i> Mark as Paid
                                    </button>
                                    <button class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-green-700 hover:bg-green-800 rounded-lg shadow-sm transition-colors">
                                        <i class="fas fa-sync"></i> Sync to Oracle
                                    </button>
                                @endif
                            </div>
                        @else
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Workflow</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">No actions available to you at this stage.</p>
                        @endif
                    </div>

                    <!-- Approval Timeline -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Approval Timeline</h3>
                        <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-3 space-y-4">
                            @foreach($bill->approvals as $approval)
                                <li class="ml-5">
                                    @php
                                        $dotColor = match ($approval->action) {
                                            'approved' => 'bg-green-500',
                                            'rejected' => 'bg-red-500',
                                            'resubmitted' => 'bg-orange-500',
                                            default => 'bg-gray-500',
                                        };
                                    @endphp
                                    <div class="absolute -left-2.5 w-5 h-5 rounded-full {{ $dotColor }} flex items-center justify-center">
                                        @if($approval->action === 'approved')
                                            <i class="fas fa-check text-white text-[10px]"></i>
                                        @elseif($approval->action === 'rejected')
                                            <i class="fas fa-times text-white text-[10px]"></i>
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
        </div>
    </div>
</x-app-layout>
