<x-app-layout>
    <x-toast />
    @php
        $user = auth()->user();
        $canSubmit = $user->isAdmin() || $user->isAccountUser();
        $with = fn (array $extra) => array_filter(array_merge(request()->except('page'), $extra), fn ($v) => $v !== null && $v !== '');
        $isCmd = $user->isCmd();
        $isDirector = $user->isDirector();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $isCmd ? 'bg-orange-600' : ($isDirector ? 'bg-blue-600' : 'bg-gray-600') }} flex items-center justify-center">
                            <i class="fas fa-bank text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="font-semibold text-2xl text-gray-800 dark:text-gray-200">
                                {{ $isCmd ? 'CMD Dashboard' : ($isDirector ? 'Director Dashboard' : 'Dashboard') }}
                            </h1>
                            <p class="text-gray-500 text-sm mt-1">
                                {{ $isCmd ? 'Process payments and sync to Oracle' : ($isDirector ? 'Review and approve AP requests' : 'Welcome back, ' . $user->name) }}
                            </p>
                        </div>
                    </div>
                </div>
                @if($canSubmit)
                    <a href="{{ route('vendor-bills-2.create') }}"
                       class="inline-flex items-center px-5 py-2.5 text-sm font-semibold rounded-lg shadow-sm bg-orange-600 hover:bg-orange-700 text-white transition-colors">
                        <i class="fas fa-plus mr-2"></i>Create AP Request
                    </a>
                @endif
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                @if($isCmd)
                    <!-- CMD-specific stats -->
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
                            <div class="w-12 h-12 rounded-full bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">1</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Due Soon (&lt; 12h)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">2</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Urgent (&lt; 8h)</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">3</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Processed Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                                <i class="fas fa-clock text-orange-600 dark:text-orange-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">4.2</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Avg. Approval (h)</div>
                            </div>
                        </div>
                    </div>
                @elseif($isDirector)
                    <!-- Director-specific stats -->
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
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">5</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Approved Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-600 dark:text-red-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">2</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Rejected Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">12</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Total This Week</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                                <i class="fas fa-clock text-orange-600 dark:text-orange-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">4.2</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Avg. Approval (h)</div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Default stats for other users -->
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
                            <div class="w-12 h-12 rounded-full bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600 dark:text-yellow-400 text-lg"></i>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $counts['director'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Pending Director</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <i class="fas fa-user-circle text-blue-600 dark:text-blue-400 text-lg"></i>
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
                                <div class="text-xs text-gray-500 dark:text-gray-400">Approved</div>
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
                                <div class="text-xs text-gray-500 dark:text-gray-400">Rejected</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Content Area -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Tabs -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                        <div class="border-b border-gray-200 dark:border-gray-700 px-6">
                            <nav class="flex gap-8">
                                @php
                                    $tabs = [
                                        'recent' => ['label' => 'Recent Requests'],
                                        'pending' => ['label' => 'Pending With Me'],
                                        'requiring' => ['label' => 'Requiring My Action'],
                                    ];
                                @endphp
                                @foreach($tabs as $key => $tab)
                                    <button class="py-4 text-sm font-medium {{ $loop->first ? 'text-orange-600 border-b-2 border-orange-600' : 'text-gray-500 hover:text-gray-700' }} transition-colors">
                                        {{ $tab['label'] }}
                                    </button>
                                @endforeach
                            </nav>
                        </div>

                        <!-- Filters -->
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex flex-wrap gap-3 items-center">
                                <select class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    <option>All Status</option>
                                </select>
                                <select class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    <option>All Vendors</option>
                                </select>
                                <input type="text" placeholder="Date range" class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                <div class="flex-1"></div>
                                <form action="{{ route('vendor-bills-2.index') }}" method="GET" class="relative">
                                    <input type="hidden" name="queue" value="{{ $activeQueue }}">
                                    @if($statusFilter)<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                        <input type="text" name="search" value="{{ $search }}"
                                               placeholder="Search bill #, vendor, amount..."
                                               class="pl-10 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    </div>
                                </form>
                                <button class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white flex items-center gap-1">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
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
                                        @if($isCmd || $isDirector)
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Priority</th>
                                        @endif
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
                                                        \App\Models\VendorBill::STATUS_REJECTED => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
                                                        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700'],
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColors['bg'] }} {{ $statusColors['text'] }}">
                                                    {{ $bill->statusLabel() }}
                                                </span>
                                            </td>
                                            @if($isCmd || $isDirector)
                                                <td class="px-6 py-4">
                                                    @php
                                                        $priorityColors = ['High' => ['bg' => 'bg-red-100', 'text' => 'text-red-700'], 'Medium' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700'], 'Low' => ['bg' => 'bg-green-100', 'text' => 'text-green-700']];
                                                        $priority = $loop->index % 3 === 0 ? 'High' : ($loop->index % 3 === 1 ? 'Medium' : 'Low');
                                                        $priorityColor = $priorityColors[$priority];
                                                    @endphp
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $priorityColor['bg'] }} {{ $priorityColor['text'] }}">
                                                        {{ $priority }}
                                                    </span>
                                                </td>
                                            @endif
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <a href="{{ route('vendor-bills-2.show', $bill) }}"
                                                       class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                        View
                                                    </a>
                                                    <button class="inline-flex items-center p-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $isCmd || $isDirector ? 8 : 7 }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                                <div>No requests found.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            {{ $bills->links() }}
                        </div>
                    </div>
                </div>

                <!-- Sidebar Widgets -->
                <div class="space-y-6">
                    <!-- 24-Hour CMD Tracker -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">24-Hour CMD Tracker</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Urgent (&lt; 8h)</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-800 dark:text-white">2</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Due Soon (8-12h)</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-800 dark:text-white">1</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-green-400"></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">On Track (12-24h)</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-800 dark:text-white">2</span>
                            </div>
                        </div>
                        <button class="mt-4 w-full text-xs font-semibold text-orange-600 hover:text-orange-700 transition-colors">
                            View all pending CMD
                        </button>
                    </div>

                    <!-- Workflow Summary -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Workflow Summary</h3>
                        <ol class="space-y-3">
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Request Created</div>
                                    <div class="text-xs text-gray-500">12</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Director Approved</div>
                                    <div class="text-xs text-gray-500">8</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">CMD Approved</div>
                                    <div class="text-xs text-gray-500">5</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Synced to Oracle</div>
                                    <div class="text-xs text-gray-500">4</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 w-5 h-5 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center text-xs">
                                    <i class="fas fa-circle text-[8px]"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-gray-800 dark:text-white font-medium">Paid</div>
                                    <div class="text-xs text-gray-500"></div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
