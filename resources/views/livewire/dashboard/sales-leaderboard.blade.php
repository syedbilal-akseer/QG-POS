<div>
    @foreach($locationRows as $location)
        <div class="mt-8">
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
                {{ $detail ? $location['title'] . ' — Salespeople' : $location['title'] . ' Overview' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($location['cards'] as $card)
                    <div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 h-full">
                            <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                                <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                                    {{ ucfirst($card['unit']) }}
                                </h4>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-gray-200">
                                        Total: <span class="font-semibold">{{ number_format($card['overall']) }}</span> {{ $card['unit'] }}
                                    </span>
                                    @if($detail && $card['rows'] && $card['rows']->total() > 0)
                                        <span class="text-xs px-2 py-1 rounded-full {{ $card['badge_class'] }}">
                                            {{ $card['rows']->total() }} {{ Str::plural('salesperson', $card['rows']->total()) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if(!$detail)
                                {{-- Overall-count card (no per-salesperson detail) — for users like Fahim --}}
                                <div class="text-center py-6">
                                    <p class="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                        {{ number_format($card['overall']) }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Total {{ $card['unit'] }} in {{ $location['title'] }}
                                    </p>
                                </div>
                            @elseif($card['rows']->total() === 0)
                                <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-6">
                                    No {{ $card['unit'] }} yet.
                                </p>
                            @else
                                <div class="overflow-hidden border border-gray-100 dark:border-neutral-700 rounded">
                                    <table class="min-w-full divide-y divide-gray-100 dark:divide-neutral-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-neutral-900">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">#</th>
                                                <th class="px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Salesperson</th>
                                                <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ ucfirst($card['unit']) }}</th>
                                                <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-neutral-700">
                                            @foreach($card['rows'] as $i => $row)
                                                @php $rank = $card['rows']->firstItem() + $i; @endphp
                                                <tr>
                                                    <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                                        @if($rank === 1) 🥇
                                                        @elseif($rank === 2) 🥈
                                                        @elseif($rank === 3) 🥉
                                                        @else {{ $rank }}
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $row['name'] }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right">
                                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $card['count_class'] }}">
                                                            {{ number_format($row['count']) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300 font-mono text-xs">
                                                        Rs {{ number_format($row['total_amount'], 0) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($card['rows']->hasPages())
                                    <div class="mt-3 text-sm">
                                        {{ $card['rows']->onEachSide(1)->links() }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
