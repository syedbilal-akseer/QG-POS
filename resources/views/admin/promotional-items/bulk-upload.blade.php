<x-layout pageTitle="Bulk Upload Promotional Items">
    <div class="container mx-auto mt-4 p-4">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6 max-w-2xl mx-auto">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Bulk Upload Promotional Items</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Upload an Excel file with item codes. Item details (description, original price) are automatically pulled from the existing <code class="px-1 py-0.5 bg-gray-100 dark:bg-neutral-900 rounded">items</code> and <code class="px-1 py-0.5 bg-gray-100 dark:bg-neutral-900 rounded">item_prices</code> tables.
            </p>

            @if(session('success'))
                <div class="mb-4 p-3 rounded bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200">
                    <ul class="list-disc ml-5 text-sm">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <details class="mb-4 p-3 rounded border border-gray-200 dark:border-neutral-700 text-sm">
                <summary class="font-bold cursor-pointer text-gray-700 dark:text-gray-300">Expected columns (only item_code is required)</summary>
                <table class="mt-3 text-xs w-full">
                    <thead class="bg-gray-50 dark:bg-neutral-900"><tr>
                        <th class="text-left px-2 py-1">Column</th>
                        <th class="text-left px-2 py-1">Required?</th>
                        <th class="text-left px-2 py-1">Notes</th>
                    </tr></thead>
                    <tbody>
                        <tr><td class="px-2 py-1 font-mono">item_code</td><td class="px-2 py-1">✓</td><td class="px-2 py-1">Must exist in items table</td></tr>
                        <tr><td class="px-2 py-1 font-mono">buy_qty</td><td class="px-2 py-1">—</td><td class="px-2 py-1">Buy N (scheme). e.g. 10</td></tr>
                        <tr><td class="px-2 py-1 font-mono">free_qty</td><td class="px-2 py-1">—</td><td class="px-2 py-1">Get M free. e.g. 1</td></tr>
                        <tr><td class="px-2 py-1 font-mono">free_item_code</td><td class="px-2 py-1">—</td><td class="px-2 py-1">If blank, free item = purchased item</td></tr>
                        <tr><td class="px-2 py-1 font-mono">discounted_price</td><td class="px-2 py-1">—</td><td class="px-2 py-1">Special price (if applicable)</td></tr>
                        <tr><td class="px-2 py-1 font-mono">scheme_start_date</td><td class="px-2 py-1">—</td><td class="px-2 py-1">YYYY-MM-DD</td></tr>
                        <tr><td class="px-2 py-1 font-mono">scheme_end_date</td><td class="px-2 py-1">—</td><td class="px-2 py-1">YYYY-MM-DD</td></tr>
                        <tr><td class="px-2 py-1 font-mono">is_active</td><td class="px-2 py-1">—</td><td class="px-2 py-1">1/0, defaults to active</td></tr>
                    </tbody>
                </table>
            </details>

            <form method="POST" action="{{ route('admin.promotional-items.bulk-upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Excel File</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded bg-primary-600 text-white font-semibold hover:bg-primary-700">Upload &amp; Import</button>
                    <a href="{{ route('promotional-items.all') }}" class="px-4 py-2 rounded border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-200">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layout>
