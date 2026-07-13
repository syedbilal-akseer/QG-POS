<x-app-layout>
    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Mobile Receipt Adoption — by Salesperson × Month
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Source: <span class="font-mono">APPS.QG_RECEIPTS_PERCENTAGE</span>. Cells show
                        <em>mobile%  (mobile / total)</em>.
                    </p>
                </div>
                <a href="{{ route('admin.reports.receipts-percentage.export') }}"
                   class="inline-flex items-center px-4 py-2 rounded-md border border-transparent shadow-sm text-sm font-semibold text-white bg-green-600 hover:bg-green-700">
                    <i class="fas fa-file-csv mr-2 text-white"></i>Download CSV
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-900 text-white">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold sticky left-0 bg-gray-900 z-10">Salesperson</th>
                                @foreach($months as $m)
                                    <th class="px-4 py-2 text-center font-semibold whitespace-nowrap">{{ $m }}</th>
                                @endforeach
                                <th class="px-4 py-2 text-right font-semibold whitespace-nowrap">Total Receipts</th>
                                <th class="px-4 py-2 text-right font-semibold whitespace-nowrap">Mobile Receipts</th>
                                <th class="px-4 py-2 text-right font-semibold whitespace-nowrap">Overall Mobile %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php $grandMob = 0; $grandTot = 0; @endphp
                            @foreach($matrix as $sp => $cells)
                                @php $spMob = 0; $spTot = 0; @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-100 sticky left-0 bg-white dark:bg-gray-800">
                                        {{ $sp }}
                                    </td>
                                    @foreach($months as $m)
                                        @php $c = $cells[$m] ?? null; @endphp
                                        <td class="px-4 py-2 text-center whitespace-nowrap text-gray-700 dark:text-gray-200">
                                            @if($c)
                                                <span class="font-semibold">{{ $c['pct'] }}</span>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 block">
                                                    {{ $c['mobile'] }} / {{ $c['total'] }}
                                                </span>
                                                @php
                                                    $spMob += $c['mobile'];
                                                    $spTot += $c['total'];
                                                @endphp
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($spTot) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($spMob) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums">
                                        {{ $spTot > 0 ? round(($spMob / $spTot) * 100, 2) . '%' : '0%' }}
                                    </td>
                                </tr>
                                @php $grandMob += $spMob; $grandTot += $spTot; @endphp
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-900 font-semibold">
                            <tr>
                                <td class="px-4 py-2 sticky left-0 bg-gray-100 dark:bg-gray-900">ALL SALESPERSONS</td>
                                @foreach($months as $m)
                                    @php
                                        $mob = $colTotals[$m]['mobile'];
                                        $tot = $colTotals[$m]['total'];
                                        $pct = $tot > 0 ? round(($mob / $tot) * 100, 2) . '%' : '0%';
                                    @endphp
                                    <td class="px-4 py-2 text-center whitespace-nowrap">
                                        <span>{{ $pct }}</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 block">{{ $mob }} / {{ $tot }}</span>
                                    </td>
                                @endforeach
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format($grandTot) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format($grandMob) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ $grandTot > 0 ? round(($grandMob / $grandTot) * 100, 2) . '%' : '0%' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
