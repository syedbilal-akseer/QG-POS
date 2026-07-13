<x-app-layout>
    <x-toast />
    @php
        $user = auth()->user();
        $canSubmit = $user->isAdmin() || $user->isAccountUser();
        // tiny helper to keep current ?queue/?search/?status when switching tabs
        $with = fn (array $extra) => array_filter(array_merge(request()->except('page'), $extra), fn ($v) => $v !== null && $v !== '');
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Vendors AP') }}
                </h2>
                <div class="flex flex-wrap items-center gap-3">
                    <form action="{{ route('vendor-bills.index') }}" method="GET" class="relative group">
                        <input type="hidden" name="queue"  value="{{ $activeQueue }}">
                        @if($statusFilter)<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Search bill # / vendor…"
                               class="w-72 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </form>
                    @if($canSubmit)
                        <a href="{{ route('vendor-bills.create') }}"
                           style="background-color: #ea580c; color: #ffffff;"
                           onmouseover="this.style.backgroundColor='#c2410c'"
                           onmouseout="this.style.backgroundColor='#ea580c'"
                           class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow-sm">
                            <i class="fas fa-plus mr-2" style="color:#fff;"></i>New Bill
                        </a>
                    @endif
                </div>
            </div>

            {{-- Queue tabs --}}
            @php
                $tabs = [
                    'mine'     => ['label' => 'My Bills',         'count' => $counts['mine']],
                    'cmd'      => ['label' => 'Pending CMD',      'count' => $counts['cmd']],
                    'director' => ['label' => 'Pending Director', 'count' => $counts['director']],
                    'approved' => ['label' => 'Approved',         'count' => $counts['approved']],
                    'rejected' => ['label' => 'Rejected',         'count' => $counts['rejected']],
                    'all'      => ['label' => 'All',              'count' => null],
                ];
            @endphp
            <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex flex-wrap gap-1 text-sm">
                    @foreach($tabs as $key => $tab)
                        @php $active = $activeQueue === $key; @endphp
                        <a href="{{ route('vendor-bills.index', $with(['queue' => $key])) }}"
                           class="px-4 py-2 border-b-2 {{ $active ? 'border-primary-600 text-primary-700 dark:text-primary-300 font-semibold' : 'border-transparent text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                            {{ $tab['label'] }}
                            @if(!is_null($tab['count']))
                                <span class="ml-1 text-[11px] inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $tab['count'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Bill #</th>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Vendor</th>
                                <th class="px-4 py-3 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Uploaded</th>
                                <th class="px-4 py-3 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($bills as $bill)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $bill->bill_number }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        <div class="font-medium">{{ $bill->vendor?->vendor_name ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $bill->vendor?->vendor_code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                        Rs {{ number_format((float) $bill->amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $color = match ($bill->status) {
                                                'pending_cmd_approval'      => ['bg' => '#fef3c7', 'fg' => '#92400e'],
                                                'pending_director_approval' => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
                                                'approved'                  => ['bg' => '#d1fae5', 'fg' => '#065f46'],
                                                'rejected'                  => ['bg' => '#fee2e2', 'fg' => '#991b1b'],
                                                default                     => ['bg' => '#e5e7eb', 'fg' => '#374151'],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
                                              style="background:{{ $color['bg'] }};color:{{ $color['fg'] }};">
                                            {{ $bill->statusLabel() }}
                                        </span>
                                        @if($bill->status === 'rejected' && $bill->rejected_by_role)
                                            <span class="ml-1 text-[10px] text-red-600 dark:text-red-400">(by {{ strtoupper($bill->rejected_by_role) }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">
                                        <div>{{ $bill->created_at->format('M d, Y H:i') }}</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">by {{ $bill->uploader?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('vendor-bills.show', $bill) }}"
                                           style="background-color:#ea580c;color:#fff;"
                                           onmouseover="this.style.backgroundColor='#c2410c'"
                                           onmouseout="this.style.backgroundColor='#ea580c'"
                                           class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold">
                                            <i class="fas fa-eye mr-1" style="color:#fff;"></i>Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-inbox text-3xl mb-2 text-gray-400 dark:text-gray-500"></i>
                                        <div>No bills in this queue.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $bills->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
