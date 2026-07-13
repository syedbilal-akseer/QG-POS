<x-layout pageTitle="QR Labels">
    <div class="container mx-auto mt-4 p-4 sm:p-6">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            @if(session('success'))
                <div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="flex items-start justify-between mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Print QR Labels</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Pick the items you want to print packing-level QR codes for.
                        {{ $items->count() }} item(s) have packing definitions.
                    </p>
                </div>
                <button type="button" onclick="document.getElementById('addItemModal').showModal()"
                    class="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700">
                    + Add Item
                </button>
            </div>

            {{-- Add Item modal — uses native <dialog> for a no-dependency modal --}}
            <dialog id="addItemModal" class="rounded-lg p-0 max-w-lg w-full backdrop:bg-black/50">
                <form method="POST" action="{{ route('items.qr-labels.store') }}" class="bg-white dark:bg-neutral-800 p-6 rounded-lg">
                    @csrf
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">Add Packing Definitions</h2>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">
                        Adds the 4 packing levels for one item. Conversion factors are optional —
                        leave blank if SC hasn't shared them yet; you can fill them in later.
                    </p>

                    @if($errors->any())
                        <div class="mb-3 p-3 rounded bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 text-sm">
                            <ul class="list-disc ml-5">
                                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-3">
                        <div x-data="{
                                q: '',
                                results: [],
                                selected: null,
                                open: false,
                                loading: false,
                                searchUrl: '{{ route('items.qr-labels.search') }}',
                                debounceTimer: null,
                                async search() {
                                    clearTimeout(this.debounceTimer);
                                    this.debounceTimer = setTimeout(async () => {
                                        this.loading = true;
                                        try {
                                            const r = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.q)}`, {
                                                headers: { 'Accept': 'application/json' },
                                                credentials: 'same-origin',
                                            });
                                            const data = await r.json();
                                            this.results = data.items || [];
                                            this.open = true;
                                        } finally {
                                            this.loading = false;
                                        }
                                    }, 200);
                                },
                                pick(item) {
                                    this.selected = item;
                                    this.q = item.item_code + ' — ' + (item.item_description || '');
                                    this.open = false;
                                },
                                clear() {
                                    this.selected = null;
                                    this.q = '';
                                    this.results = [];
                                }
                            }" class="relative">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Item Code <span class="text-red-500">*</span></label>

                            {{-- Hidden input that actually submits --}}
                            <input type="hidden" name="item_code" :value="selected ? selected.item_code : ''" required>

                            <div class="relative">
                                <input type="text" x-model="q" @input="search()" @focus="if (results.length === 0) search()"
                                    placeholder="Type item code or description to search…"
                                    autocomplete="off"
                                    class="w-full px-3 py-2 pr-8 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500">
                                <button type="button" x-show="selected || q" @click="clear()"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg leading-none">×</button>
                            </div>

                            {{-- Results dropdown --}}
                            <div x-show="open && (results.length > 0 || loading)"
                                @click.outside="open = false"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-neutral-900 border border-gray-200 dark:border-neutral-700 rounded shadow-lg max-h-72 overflow-y-auto">
                                <div x-show="loading" class="px-3 py-2 text-xs text-gray-500">Searching…</div>
                                <template x-for="item in results" :key="item.item_code">
                                    <button type="button" @click="pick(item)"
                                        class="w-full text-left px-3 py-2 hover:bg-primary-50 dark:hover:bg-primary-900/30 border-b border-gray-100 dark:border-neutral-800 last:border-b-0">
                                        <div class="font-mono text-sm text-gray-900 dark:text-gray-100" x-text="item.item_code"></div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400" x-text="item.item_description"></div>
                                    </button>
                                </template>
                                <div x-show="!loading && results.length === 0" class="px-3 py-2 text-xs text-gray-500">No items match.</div>
                            </div>

                            <p class="text-xs text-gray-500 mt-1">Searches all items synced from Oracle. Pick one to continue.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Primary UOM <span class="text-red-500">*</span></label>
                                <input type="text" name="primary_uom" required value="KG"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Secondary UOM</label>
                                <input type="text" name="secondary_uom" placeholder="e.g. TB / BTL"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Primary Packing</label>
                                <input type="text" name="primary_packing" value="Box"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Secondary Packing</label>
                                <input type="text" name="secondary_packing" value="Carton"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                            </div>
                        </div>

                        <details class="border border-gray-200 dark:border-neutral-700 rounded p-3">
                            <summary class="text-xs font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">Conversion factors (optional)</summary>
                            <div class="grid grid-cols-3 gap-3 mt-3">
                                <div>
                                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Secondary → Primary</label>
                                    <input type="number" step="0.000001" name="secondary_to_primary" placeholder="e.g. 0.11"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                                    <p class="text-[10px] text-gray-500 mt-1">1 tube/bottle = X kg</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Box → Secondary</label>
                                    <input type="number" step="0.000001" name="packing_to_secondary" placeholder="e.g. 12"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                                    <p class="text-[10px] text-gray-500 mt-1">1 box = X tubes/bottles</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Carton → Box</label>
                                    <input type="number" step="0.000001" name="carton_to_packing" placeholder="e.g. 6"
                                        class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                                    <p class="text-[10px] text-gray-500 mt-1">1 carton = X boxes</p>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" onclick="document.getElementById('addItemModal').close()"
                            class="px-4 py-2 rounded text-sm border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-neutral-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded text-sm bg-primary-600 text-white font-semibold hover:bg-primary-700">
                            Save Packing Definitions
                        </button>
                    </div>
                </form>
            </dialog>

            @if($items->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-neutral-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zm0 9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 19.125v-4.5zm9.75-9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" /></svg>
                    <p class="mt-4 text-gray-600 dark:text-gray-400">No items have packing definitions yet.</p>
                    <p class="text-sm text-gray-500 mt-1">Run <code class="bg-gray-100 dark:bg-neutral-900 px-2 py-1 rounded">php artisan import:item-packings &lt;file&gt;</code> first.</p>
                </div>
            @else
                <div x-data="{
                        search: '',
                        selected: new Set(),
                        toggle(code) {
                            if (this.selected.has(code)) this.selected.delete(code);
                            else this.selected.add(code);
                            this.selected = new Set(this.selected); // trigger reactivity
                        },
                        toggleAll(visibleCodes) {
                            const allSelected = visibleCodes.every(c => this.selected.has(c));
                            visibleCodes.forEach(c => allSelected ? this.selected.delete(c) : this.selected.add(c));
                            this.selected = new Set(this.selected);
                        },
                        printSelected() {
                            if (this.selected.size === 0) return;
                            const codes = [...this.selected].join(',');
                            window.open(`{{ route('items.qr-labels.bulk') }}?codes=${encodeURIComponent(codes)}`, '_blank');
                        },
                        get visibleItems() {
                            const q = this.search.toLowerCase().trim();
                            if (!q) return this.allItems;
                            return this.allItems.filter(i =>
                                i.item_code.toLowerCase().includes(q) ||
                                (i.item_description || '').toLowerCase().includes(q)
                            );
                        },
                        allItems: @js($items->map(fn($i) => ['item_code' => $i->item_code, 'item_description' => $i->item_description])->values())
                    }">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <div class="flex-1 min-w-[240px]">
                            <input type="text" x-model="search" placeholder="Search by item code or description…"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            <span x-text="selected.size"></span> selected
                        </span>
                        <button type="button" @click="printSelected()" :disabled="selected.size === 0"
                            class="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            🖨️ Print Selected
                        </button>
                    </div>

                    <div class="border border-gray-200 dark:border-neutral-700 rounded-lg overflow-hidden">
                        <div style="max-height: 60vh; overflow-y: auto;">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-neutral-900 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900 w-10">
                                            <input type="checkbox"
                                                @change="toggleAll(visibleItems.map(i => i.item_code))"
                                                :checked="visibleItems.length > 0 && visibleItems.every(i => selected.has(i.item_code))"
                                                class="rounded">
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Item Code</th>
                                        <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Description</th>
                                        <th class="px-3 py-2 text-right text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-neutral-900">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-neutral-800 divide-y divide-gray-100 dark:divide-neutral-700">
                                    <template x-for="item in visibleItems" :key="item.item_code">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/40">
                                            <td class="px-3 py-2">
                                                <input type="checkbox"
                                                    :checked="selected.has(item.item_code)"
                                                    @change="toggle(item.item_code)"
                                                    class="rounded">
                                            </td>
                                            <td class="px-3 py-2 font-mono text-gray-900 dark:text-gray-100" x-text="item.item_code"></td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300" x-text="item.item_description"></td>
                                            <td class="px-3 py-2 text-right">
                                                <a :href="`{{ url('app/admin/items') }}/${encodeURIComponent(item.item_code)}/qr-labels`"
                                                   target="_blank"
                                                   class="text-primary-600 dark:text-primary-400 hover:underline text-xs font-semibold">
                                                    Print This
                                                </a>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="visibleItems.length === 0">
                                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">No items match your search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layout>
