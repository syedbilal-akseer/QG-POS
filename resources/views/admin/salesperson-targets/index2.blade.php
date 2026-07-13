<x-layout pageTitle="Salesperson Targets">
    <div class="container mx-auto mt-4 p-4">
        @if(session('success'))
            <div class="mb-4 p-3 rounded bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Salesperson Targets</h1>
                @if($isAdmin)
                    <a href="{{ route('admin.salesperson-targets.upload-form') }}"
                       class="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700">
                        Upload TARGET.xlsx
                    </a>
                @endif
            </div>

            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">Period</label>
                    <select name="period" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                        <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
                        <option value="year"  {{ $period === 'year'  ? 'selected' : '' }}>Whole Year</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">Year</label>
                    <input type="number" name="year" value="{{ $year }}" min="2020" max="2099"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">Month</label>
                    <select name="month" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($isAdmin)
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">User</label>
                        <select name="user_id" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                            <option value="">All</option>
                            @foreach($userOptions as $uid => $uname)
                                <option value="{{ $uid }}" {{ (string)$user_id === (string)$uid ? 'selected' : '' }}>{{ $uname }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">Name contains</label>
                    <input type="text" name="name" value="{{ $name }}" placeholder="e.g. Anjum, WZQ"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                </div>
                <div class="md:col-span-5 flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded bg-primary-600 text-white font-semibold hover:bg-primary-700 text-sm">Apply</button>
                    <a href="{{ route('admin.salesperson-targets.index') }}" class="px-4 py-2 rounded border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-200 text-sm">Reset</a>
                </div>
            </form>

            <div class="border border-gray-200 dark:border-neutral-700 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-neutral-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Year</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Month</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Primary Name</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Salesman Name</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-500 dark:text-gray-400">User Linked</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Receipt Target</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase text-gray-500 dark:text-gray-400">In PKR</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-gray-100 dark:divide-neutral-700">
                        @forelse($targets as $t)
                            <tr>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $t->year }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::create()->month($t->month)->format('M') }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $t->primary_name }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $t->salesman_name }}</td>
                                <td class="px-3 py-2">
                                    @if($t->user_id)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ optional($t->user)->name ?? '#'.$t->user_id }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Unlinked</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-gray-900 dark:text-gray-100">
                                    {{ rtrim(rtrim(number_format((float) $t->receipt_target, 6), '0'), '.') }}
                                    <span class="text-xs text-gray-500 ml-1">{{ $t->unit }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-gray-700 dark:text-gray-300">
                                    Rs {{ number_format($t->receipt_target_pkr, 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No targets for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $targets->links() }}</div>
        </div>
    </div>
</x-layout>
