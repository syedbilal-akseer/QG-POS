<x-app-layout>
    <x-toast />
    @php
        $user = auth()->user();
        $canSubmit = $user->isAdmin();
        $with = fn (array $extra) => array_filter(array_merge(request()->except('page'), $extra), fn ($v) => $v !== null && $v !== '');
        $isCmd = $user->isCmd();
        $isDirector = $user->isDirector();
    @endphp

    <div class="py-3">
        <div class=" mx-2 px-2 sm:px-6 lg:px-2">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $isCmd ? 'bg-orange-600' : ($isDirector ? 'bg-blue-600' : 'bg-gray-600') }} flex items-center justify-center">
                            <i class="fas fa-bank text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="font-semibold text-2xl text-gray-800 dark:text-gray-200">
                                {{ $isCmd ? 'CMD Dashboard' : ($isDirector ? 'Director Dashboard' : 'Vendors AP') }}
                            </h1>
                            <p class="text-gray-500 text-sm mt-1">
                                {{ $isCmd ? 'Review and finalize AP requests within 24h' : ($isDirector ? 'First review — approve or reject AP requests' : 'Admin-submitted vendor bills') }}
                            </p>
                        </div>
                    </div>
                </div>
                @if($canSubmit)
                    <a href="{{ route('vendor-bills.create') }}"
                       class="inline-flex items-center px-5 py-2.5 text-sm font-semibold rounded-lg shadow-sm bg-orange-600 hover:bg-orange-700 text-white transition-colors">
                        <i class="fas fa-plus mr-2"></i>Create AP Request
                    </a>
                @endif
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                @if($isCmd)
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <i class="fas fa-file-alt text-blue-600 dark:text-blue-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['cmd'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Pending CMD Action</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $slaBuckets['overdue'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Overdue (24h SLA)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                                <i class="fas fa-clock text-orange-600 dark:text-orange-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $slaBuckets['urgent'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Urgent (&lt; 8h left)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $slaBuckets['dueSoon'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Due Soon (8-12h left)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $slaBuckets['onTrack'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">On Track (12-24h left)</div>
                            </div>
                        </div>
                    </div>
                @elseif($isDirector)
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <i class="fas fa-file-alt text-blue-600 dark:text-blue-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['director'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Ready for Action</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $workflowSummary['director'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Approved by Director (all time)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['rejected'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Rejected (open)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $workflowSummary['created'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Total Requests</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                                <i class="fas fa-file-invoice text-orange-600 dark:text-orange-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['mine'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">My Requests</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <i class="fas fa-user-circle text-blue-600 dark:text-blue-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['director'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Pending Director</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['cmd'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Pending CMD</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['approved'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Approved (needs close-out)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gray-50 dark:bg-gray-900/20 flex items-center justify-center">
                                <i class="fas fa-box-archive text-gray-600 dark:text-gray-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['closed'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Closed</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Content Area -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                        <!-- Queue tabs -->
                        <div class="border-b border-gray-200 dark:border-gray-700 px-6">
                            @php
                                $allTabs = [
                                    'mine'     => ['label' => 'My Requests',      'count' => $counts['mine']],
                                    'director' => ['label' => 'Pending Director', 'count' => $counts['director']],
                                    'cmd'      => ['label' => 'Pending CMD',      'count' => $counts['cmd']],
                                    'approved' => ['label' => 'Approved',        'count' => $counts['approved']],
                                    'closed'   => ['label' => 'Closed',          'count' => $counts['closed']],
                                    'rejected' => ['label' => 'Rejected',        'count' => $counts['rejected']],
                                    'all'      => ['label' => 'All',            'count' => null],
                                ];

                                // Each role only sees tabs relevant to them: Admin is the only
                                // one who creates bills (so only Admin gets "My Requests"/"All"),
                                // and Director/CMD each only see the *other* stage's queue label
                                // implicitly via Approved/Closed/Rejected — not each other's
                                // "Pending" queue, which isn't actionable to them anyway.
                                if ($user->isAdmin()) {
                                    $visibleKeys = ['mine', 'director', 'cmd', 'approved', 'closed', 'rejected', 'all'];
                                } elseif ($isDirector) {
                                    $visibleKeys = ['director', 'approved', 'closed', 'rejected'];
                                } elseif ($isCmd) {
                                    $visibleKeys = ['cmd', 'approved', 'closed', 'rejected'];
                                } else {
                                    $visibleKeys = [];
                                }
                                $tabs = array_intersect_key($allTabs, array_flip($visibleKeys));
                            @endphp
                            <nav class="flex gap-6 flex-wrap">
                                @foreach($tabs as $key => $tab)
                                    @php $active = $activeQueue === $key; @endphp
                                    <a href="{{ route('vendor-bills.index', $with(['queue' => $key])) }}"
                                       class="py-4 text-sm font-medium border-b-2 {{ $active ? 'text-orange-600 border-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }} transition-colors">
                                        {{ $tab['label'] }}
                                        @if(!is_null($tab['count']))
                                            <span class="ml-1 text-[11px] inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $tab['count'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </nav>
                        </div>

                        <!-- Search -->
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <form action="{{ route('vendor-bills.index') }}" method="GET" class="relative">
                                <input type="hidden" name="queue" value="{{ $activeQueue }}">
                                @if($statusFilter)<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
                                <div class="relative max-w-sm">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <input type="text" name="search" value="{{ $search }}"
                                           placeholder="Search bill #, vendor..."
                                           class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                </div>
                            </form>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Bill #</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Vendor</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Requester</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">SLA</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($bills as $bill)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <td class="px-6 py-4">
                                                <span class="font-semibold text-gray-800 dark:text-white">{{ $bill->bill_number }}</span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-800 dark:text-white font-medium">{{ $bill->vendor?->vendor_name ?? '—' }}</div>
                                                <div class="text-xs text-gray-500">{{ $bill->vendor?->vendor_code ?? '—' }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-800 dark:text-white font-medium">{{ $bill->uploader?->name ?? '—' }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="font-semibold text-gray-800 dark:text-white">Rs {{ number_format((float) $bill->amount, 2) }}</span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300 text-xs">
                                                {{ optional($bill->bill_date)->format('M d, Y') ?? $bill->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4">
                                                @php
                                                    $statusColors = match($bill->status) {
                                                        \App\Models\VendorBill::STATUS_DRAFT => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700'],
                                                        \App\Models\VendorBill::STATUS_PENDING_DIRECTOR => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700'],
                                                        \App\Models\VendorBill::STATUS_PENDING_CMD => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700'],
                                                        \App\Models\VendorBill::STATUS_APPROVED => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
                                                        \App\Models\VendorBill::STATUS_CLOSED => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
                                                        \App\Models\VendorBill::STATUS_REJECTED => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
                                                        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700'],
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColors['bg'] }} {{ $statusColors['text'] }}">
                                                    {{ $bill->statusLabel() }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-xs">
                                                @if($bill->status === \App\Models\VendorBill::STATUS_PENDING_CMD && $bill->cmd_deadline_at)
                                                    @php $hrs = $bill->cmdHoursRemaining(); @endphp
                                                    @if($hrs < 0)
                                                        <span class="text-red-600 font-semibold">Overdue {{ abs($hrs) }}h</span>
                                                    @else
                                                        <span class="{{ $hrs < 8 ? 'text-red-600' : ($hrs < 12 ? 'text-yellow-600' : 'text-gray-500') }} font-medium">{{ $hrs }}h left</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="{{ route('vendor-bills.show', $bill) }}"
                                                   class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                                <div>No requests found.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            {{ $bills->links() }}
                        </div>
                    </div>
                </div>

                <!-- Sidebar Widgets -->
                <div class="space-y-6">
                    @if($isCmd || $user->isAdmin())
                        <!-- 24-Hour CMD Tracker -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">24-Hour CMD Tracker</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-red-600"></span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Overdue</span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-800 dark:text-white">{{ $slaBuckets['overdue'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Urgent (&lt; 8h)</span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-800 dark:text-white">{{ $slaBuckets['urgent'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Due Soon (8-12h)</span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-800 dark:text-white">{{ $slaBuckets['dueSoon'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-green-400"></span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">On Track (12-24h)</span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-800 dark:text-white">{{ $slaBuckets['onTrack'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Workflow Summary -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Workflow Summary</h3>
                        <ol class="space-y-3">
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Requests Created</div>
                                    <div class="text-xs text-gray-500">{{ $workflowSummary['created'] }}</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Director Approved</div>
                                    <div class="text-xs text-gray-500">{{ $workflowSummary['director'] }}</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">CMD Approved</div>
                                    <div class="text-xs text-gray-500">{{ $workflowSummary['cmd'] }}</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Closed by Admin</div>
                                    <div class="text-xs text-gray-500">{{ $workflowSummary['closed'] }}</div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
