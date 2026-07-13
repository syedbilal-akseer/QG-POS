<x-layout pageTitle="Upload Salesperson Targets">
    <div class="container mx-auto mt-4 p-4">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6 max-w-2xl mx-auto">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Upload TARGET.xlsx</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Upload the monthly receipt targets sheet. Existing (salesperson, month) rows will be updated; new ones inserted. Salesperson is matched against <code class="px-1 py-0.5 bg-gray-100 dark:bg-neutral-900 rounded">users.name</code> / <code class="px-1 py-0.5 bg-gray-100 dark:bg-neutral-900 rounded">users.oracle_user_name</code>. Rows that can't be matched are still saved by primary_name so you can link them later.
            </p>

            @if($errors->any())
                <div class="mb-4 p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800">
                    <ul class="list-disc ml-5 text-sm">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.salesperson-targets.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Excel File</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 mt-1">Expected columns: PRIMARY NAME, Salesman Name, RCP TGT, Sales TGT, datekey, dateFormatted. <strong>Sales TGT</strong> is optional &mdash; rows without it default to 0.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Value Unit <span class="text-red-500">*</span></label>
                    <select name="unit" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                        <option value="MILLION_PKR" selected>Millions PKR  — e.g. 2.87 in the sheet means 2,870,000</option>
                        <option value="THOUSAND_PKR">Thousands PKR — e.g. 2,870 in the sheet means 2,870,000</option>
                        <option value="PKR">PKR (exact rupees) — sheet values are already full PKR amounts</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        The dashboard API converts to full PKR before returning, so mobile / portal don't need to know the unit.
                    </p>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded bg-primary-600 text-white font-semibold hover:bg-primary-700">Upload &amp; Import</button>
                    <a href="{{ route('admin.salesperson-targets.index') }}" class="px-4 py-2 rounded border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-200">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layout>
