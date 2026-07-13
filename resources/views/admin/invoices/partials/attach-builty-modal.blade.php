{{-- Attach Builty modal — opened from any invoice row via Alpine's
     attachBuiltyModal() x-data. Posts to builties.attachToInvoice
     which re-merges the invoice PDF with the picked builty's PDF. --}}
<div x-show="open" x-cloak class="fixed inset-0 z-40 overflow-y-auto" @keydown.escape.window="open = false">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="open = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
            <form method="POST" :action="formAction">
                @csrf
                <input type="hidden" name="builty_id" :value="selectedBuilty?.id || ''">

                <div class="px-6 pt-5 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Attach Bilty to Invoice</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Invoice: <span class="font-semibold text-gray-700 dark:text-gray-200" x-text="invoiceLabel"></span>
                    </p>

                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bilty</label>
                        <input type="text" x-model="query" @input.debounce.300ms="searchBuilties()" @focus="searchBuilties()"
                               placeholder="Type bilty number…"
                               :class="selectedBuilty ? 'border-green-400 dark:border-green-700' : 'border-gray-300 dark:border-gray-600'"
                               class="block w-full rounded-md dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        <ul x-show="results.length > 0 && !selectedBuilty" x-cloak
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                            <template x-for="b in results" :key="b.id">
                                <li @click="pick(b)" class="px-3 py-2 text-sm cursor-pointer hover:bg-primary-50 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100">
                                    <div class="font-semibold" x-text="b.builty_number"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="b.order_number ? ('Order: ' + b.order_number) : ''"></div>
                                </li>
                            </template>
                        </ul>
                        <div x-show="selectedBuilty" x-cloak class="mt-1 text-xs text-green-600 dark:text-green-400">
                            Selected: <span class="font-semibold" x-text="selectedBuilty?.builty_number"></span>
                            <button type="button" class="ml-2 text-red-500 hover:underline" @click="selectedBuilty = null; query=''">change</button>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 px-6 py-3 sm:flex sm:flex-row-reverse gap-2">
                    <button type="submit" :disabled="!selectedBuilty"
                            class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed sm:text-sm">
                        Attach &amp; Merge
                    </button>
                    <button type="button" @click="open = false"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function attachBuiltyModal() {
        return {
            open: false,
            invoiceId: null,
            invoiceLabel: '',
            formAction: '',
            query: '', results: [], selectedBuilty: null,

            openFor(invoiceId, invoiceNumber) {
                this.invoiceId = invoiceId;
                this.invoiceLabel = invoiceNumber || ('#' + invoiceId);
                this.formAction = '{{ url('admin/builties/attach-to-invoice') }}/' + invoiceId;
                this.selectedBuilty = null;
                this.query = '';
                this.results = [];
                this.open = true;
            },

            async searchBuilties() {
                const url = "{{ route('builties.search') }}?q=" + encodeURIComponent(this.query);
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                this.results = j.data || [];
            },

            pick(b) {
                this.selectedBuilty = b;
                this.query = b.builty_number;
                this.results = [];
            },
        };
    }
</script>
