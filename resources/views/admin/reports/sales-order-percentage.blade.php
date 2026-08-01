<x-app-layout>
    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Mobile Order Adoption — by Salesperson × Month
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Source: <span class="font-mono">APPS.QG_SALES_ORDER_PERCENTAGE</span>.
                    </p>
                </div>
                <a href="{{ route('admin.reports.sales-order-percentage.export') }}"
                   class="inline-flex items-center px-4 py-2 rounded-md border border-transparent shadow-sm text-sm font-semibold text-white bg-green-600 hover:bg-green-700">
                    <i class="fas fa-file-excel mr-2 text-white"></i>Download Excel
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-900 text-white">
                            <tr>
                                <th rowspan="2" class="sticky left-0 bg-gray-900 z-10">Segment</th>
                                <th rowspan="2">Salesperson</th>

                                @foreach ($months as $month)
                                    <th colspan="4" class="text-center border-l border-gray-700">{{ $month }}</th>
                                @endforeach

                                <th rowspan="2">Growth</th>
                            </tr>

                            <tr>
                                @foreach ($months as $month)
                                    <th>Total Orders</th>
                                    <th>Mobile Orders</th>
                                    <th>Oracle Orders</th>
                                    <th>Mobile %</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php $grandMob = 0; $grandTot = 0; @endphp
                            @foreach ($matrix as $sp => $cells)
                                @php $spMob = 0; $spTot = 0; @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300 sticky left-0 bg-white dark:bg-gray-800">{{ \App\Services\SalespersonSegmentResolver::forSalesperson($sp) }}</td>
                                    <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-100">{{ $sp }}</td>
                                    @foreach ($months as $m)
                                        @php $c = $cells[$m] ?? null; @endphp
                                        <td class="px-3 py-2 text-center">{{ $c['total'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-center">{{ $c['mobile'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-center">{{ $c ? $c['total'] - $c['mobile'] : '-' }}</td>
                                        <td class="px-3 py-2 text-center font-semibold">{{ $c['pct_label'] ?? '-' }}</td>
                                        @php if ($c) { $spMob += $c['mobile']; $spTot += $c['total']; } @endphp
                                    @endforeach
                                    <td class="px-4 py-2 text-center whitespace-nowrap font-semibold">{{ $growthRows[$sp] ?? '—' }}</td>
                                </tr>
                                @php $grandMob += $spMob; $grandTot += $spTot; @endphp
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-900 font-semibold">
                            <tr>
                                <td class="px-4 py-2 sticky left-0 bg-gray-100 dark:bg-gray-900"></td>
                                <td class="px-4 py-2">ALL SALESPERSONS</td>
                                @foreach ($months as $m)
                                    @php $mob = $colTotals[$m]['mobile']; $tot = $colTotals[$m]['total']; $pct = $tot > 0 ? round(($mob / $tot) * 100, 2) . '%' : '0%'; @endphp
                                    <td class="text-center">{{ $tot }}</td>
                                    <td class="text-center">{{ $mob }}</td>
                                    <td class="text-center">{{ $tot - $mob }}</td>
                                    <td class="text-center font-semibold">{{ $pct }}</td>
                                @endforeach
                                <td class="text-center">{{ $overallGrowth }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
