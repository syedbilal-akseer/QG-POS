<x-app-layout>
    <x-toast />
    <div class="py-6" x-data="builtyAddModal()" x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Bilty Management') }}
                </h2>

                <div class="flex-1 max-w-sm mx-4">
                    <form action="{{ route('builties.index') }}" method="GET" class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fas fa-search text-sm"></i>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-700 rounded-xl leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm shadow-sm hover:border-gray-400 dark:hover:border-gray-600"
                               placeholder="Search bilty / order / invoice…">
                    </form>
                </div>

                <button type="button" @click="open = true"
                        class="py-2 px-4 inline-flex justify-center items-center text-white gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 shadow-sm">
                    <i class="fas fa-plus mr-2"></i>Add Bilty
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto rounded-lg">
                    <table class="min-w-full bg-white dark:bg-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Bilty #</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Uploaded</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($builties as $b)
                                <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $b->builty_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                        {{ $b->order?->order_number ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                        @if($b->invoice)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800">
                                                {{ Str::limit($b->invoice->invoice_number, 24) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                        <a href="{{ route('builties.file', $b->id) }}" target="_blank"
                                           class="inline-flex items-center gap-1 text-primary-600 hover:underline">
                                            <i class="fas fa-file-pdf"></i> {{ $b->original_filename ?: 'View' }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-700 dark:text-gray-200">
                                        <div>{{ $b->created_at->format('M d, Y H:i') }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">by {{ $b->uploader->name ?? 'Unknown' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <form method="POST" action="{{ route('builties.destroy', $b->id) }}" class="inline"
                                              onsubmit="return confirm('Delete this builty?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium text-white bg-red-600 hover:bg-red-700">
                                                <i class="fas fa-trash mr-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-truck text-3xl mb-2 text-gray-400 dark:text-gray-500"></i>
                                        <div>No builties uploaded yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $builties->links() }}</div>
            </div>
        </div>

        {{-- Add Builty modal — multi-file uploader (max 200) with a
             Bulk-Apply panel that prefills every row's customer/order/
             invoice/builty-number-prefix, plus per-row overrides for the
             builty number. Uses flex-centering on a fixed inset overlay
             so the modal renders centred regardless of viewport width;
             the earlier inline-block / sm:align-bottom pattern was
             rendering off-screen on certain layouts. --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="open = false">
            <div class="fixed inset-0 bg-gray-900/60" @click="open = false"></div>
            {{-- OUTER scroll container — when the file list grows or a per-row
                 dropdown opens past the modal bottom, the whole page scrolls
                 here instead of the modal body. That way absolutely-positioned
                 dropdowns inside rows are never clipped by a nested overflow
                 ancestor (the previous version had overflow-y-auto on the body
                 which cut suggestion lists off mid-text). --}}
            <div class="relative w-full h-full overflow-y-auto">
                <div class="min-h-screen flex items-start justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl flex flex-col my-4">
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Add Bilty</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Bulk upload up to 200 files (PDF / PNG / JPG / JPEG). Non-PDFs are auto-converted.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 px-2 py-1 rounded bg-gray-100 dark:bg-gray-700"
                                  x-text="rows.length + ' / 200 files'"></span>
                            <button type="button" @click="open = false" :disabled="submitting"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:opacity-50">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Body — no own overflow; the OUTER container does the
                         scrolling so dropdowns can extend freely. --}}
                    <div class="px-6 py-5 space-y-5">
                            {{-- File picker --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Choose files</label>
                                <input type="file" multiple accept=".pdf,.png,.jpg,.jpeg"
                                       @change="onFilesPicked($event)"
                                       class="block w-full text-sm text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md p-1 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700">
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    When a Customer is set, the file lands in <span class="font-mono">invoices/customers/&lt;code&gt;/</span> next to that customer's invoice PDFs. When an Invoice is set, the bilty is merged into the invoice PDF.
                                </p>
                            </div>

                            {{-- "Copy row 1 → all" helper. Builty Number is
                                 now auto-generated on the server as
                                 BLT-YYYY-N (same pattern as Order numbers),
                                 so the prefix / auto-number controls were
                                 removed. --}}
                            <div class="rounded-lg border border-primary-200 dark:border-primary-800 bg-primary-50/60 dark:bg-primary-900/10 p-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="text-[11px] text-gray-700 dark:text-gray-300">
                                    <i class="fas fa-info-circle mr-1 text-primary-600 dark:text-primary-300"></i>
                                    Bilty numbers are auto-generated as
                                    <span class="font-mono font-semibold">BLT-{{ now()->format('Y') }}-N</span>
                                    on save.
                                </div>
                                <button type="button" @click="copyRowOneToAll()" :disabled="rows.length < 2"
                                        class="inline-flex items-center px-3 py-2 rounded-md text-xs font-semibold bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                                    <i class="fas fa-copy mr-1"></i>Copy row 1 → all
                                </button>
                            </div>

                            {{-- Per-row table — each row carries its own
                                 builty number text + inline searchable pickers
                                 for Customer / Order / Invoice. Search state
                                 is per-row (row.customerResults etc.) so
                                 multiple rows can be edited without their
                                 dropdowns clashing. --}}
                            {{-- Outer wrappers MUST stay overflow: visible so the
                                 absolutely-positioned per-row dropdowns (Customer
                                 / Order / Invoice search results) can float over
                                 sibling rows instead of being clipped at the table
                                 boundary. Scroll for long file lists is handled
                                 by the modal body, not by a nested container. --}}
                            <div x-show="rows.length > 0" x-cloak
                                 class="rounded-md border border-gray-200 dark:border-gray-700"
                                 style="overflow: visible;">
                                <div style="overflow: visible;">
                                    <table class="min-w-full text-sm" style="overflow: visible;">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">#</th>
                                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Filename</th>
                                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Bilty&nbsp;#</th>
                                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Customer</th>
                                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Order <span class="text-red-500">*</span></th>
                                                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase">Invoice</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                            <template x-for="(row, idx) in rows" :key="row.uid">
                                                <tr>
                                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400 align-top" x-text="idx + 1"></td>
                                                    <td class="px-3 py-2 text-xs text-gray-800 dark:text-gray-100 align-top">
                                                        <div class="font-medium truncate max-w-[10rem]" :title="row.file.name" x-text="row.file.name"></div>
                                                        <div class="text-[10px] text-gray-500 dark:text-gray-400" x-text="formatBytes(row.file.size)"></div>
                                                    </td>

                                                    {{-- Builty # — pre-filled with the auto-generated preview
                                                         (BLT-YYYY-N) but editable per row. The Restore button
                                                         resets a row's value back to the predicted number. --}}
                                                    <td class="px-3 py-2 align-top whitespace-nowrap">
                                                        <div class="flex items-center gap-1">
                                                            <input type="text" x-model="row.builty_number"
                                                                   maxlength="64"
                                                                   :placeholder="previewBuiltyNumber(idx)"
                                                                   class="w-32 rounded font-mono text-[11px] border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                            <button type="button"
                                                                    x-show="row.builty_number && row.builty_number !== previewBuiltyNumber(idx)"
                                                                    @click="row.builty_number = previewBuiltyNumber(idx)"
                                                                    title="Restore auto-generated number"
                                                                    class="text-[10px] text-gray-400 hover:text-primary-600">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        </div>
                                                    </td>

                                                    {{-- Customer (inline) — simple absolute-positioned dropdown
                                                         inside a relative wrapper. Because the modal body and
                                                         table wrappers are all overflow: visible now, nothing
                                                         clips the suggestion list. --}}
                                                    <td class="px-3 py-2 align-top">
                                                        <div class="relative" style="overflow: visible;">
                                                            <input type="text" x-model="row.customerQuery"
                                                                   @input.debounce.300ms="searchCustomersForRow(row)"
                                                                   @focus="searchCustomersForRow(row)"
                                                                   placeholder="Customer…"
                                                                   :class="row.customer ? 'border-green-400 dark:border-green-700' : 'border-gray-300 dark:border-gray-600'"
                                                                   class="w-44 rounded dark:bg-gray-700 dark:text-white text-xs">
                                                            <ul x-show="row.customerResults.length > 0 && !row.customer" x-cloak
                                                                style="position: absolute; z-index: 999; left: 0; top: 100%; min-width: 16rem;"
                                                                class="mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-xl max-h-60 overflow-auto">
                                                                <template x-for="c in row.customerResults" :key="c.id">
                                                                    <li @click="pickCustomerForRow(row, c)" class="px-3 py-2 text-xs cursor-pointer hover:bg-primary-50 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100">
                                                                        <div class="font-semibold whitespace-normal" x-text="c.customer_name"></div>
                                                                        <div class="text-[10px] text-gray-500 dark:text-gray-400" x-text="'Code: ' + c.customer_id"></div>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                            <button x-show="row.customer" x-cloak type="button"
                                                                    @click="row.customer = null; row.customerQuery=''; row.customerResults=[]"
                                                                    class="mt-1 text-[10px] text-red-500 hover:underline">×&nbsp;clear</button>
                                                        </div>
                                                    </td>

                                                    {{-- Order (inline, required) --}}
                                                    <td class="px-3 py-2 align-top">
                                                        <div class="relative" style="overflow: visible;">
                                                            <input type="text" x-model="row.orderQuery"
                                                                   @input.debounce.300ms="searchOrdersForRow(row)"
                                                                   @focus="searchOrdersForRow(row)"
                                                                   placeholder="Order #…"
                                                                   :class="row.order ? 'border-green-400 dark:border-green-700' : 'border-red-300 dark:border-red-700'"
                                                                   class="w-36 rounded dark:bg-gray-700 dark:text-white text-xs">
                                                            <ul x-show="row.orderResults.length > 0 && !row.order" x-cloak
                                                                style="position: absolute; z-index: 999; left: 0; top: 100%; min-width: 14rem;"
                                                                class="mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-xl max-h-60 overflow-auto">
                                                                <template x-for="o in row.orderResults" :key="o.id">
                                                                    <li @click="pickOrderForRow(row, o)" class="px-3 py-2 text-xs cursor-pointer hover:bg-primary-50 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100">
                                                                        <div class="font-semibold" x-text="o.order_number"></div>
                                                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 whitespace-normal" x-text="o.customer || ''"></div>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                            <button x-show="row.order" x-cloak type="button"
                                                                    @click="row.order = null; row.orderQuery=''; row.orderResults=[]"
                                                                    class="mt-1 text-[10px] text-red-500 hover:underline">×&nbsp;clear</button>
                                                        </div>
                                                    </td>

                                                    {{-- Invoice (inline, optional) --}}
                                                    <td class="px-3 py-2 align-top">
                                                        <div class="relative" style="overflow: visible;">
                                                            <input type="text" x-model="row.invoiceQuery"
                                                                   @input.debounce.300ms="searchInvoicesForRow(row)"
                                                                   @focus="searchInvoicesForRow(row)"
                                                                   placeholder="Invoice #…"
                                                                   :class="row.invoice ? 'border-green-400 dark:border-green-700' : 'border-gray-300 dark:border-gray-600'"
                                                                   class="w-36 rounded dark:bg-gray-700 dark:text-white text-xs">
                                                            <ul x-show="row.invoiceResults.length > 0 && !row.invoice" x-cloak
                                                                style="position: absolute; z-index: 999; left: 0; top: 100%; min-width: 16rem;"
                                                                class="mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-xl max-h-60 overflow-auto">
                                                                <template x-for="i in row.invoiceResults" :key="i.id">
                                                                    <li @click="pickInvoiceForRow(row, i)" class="px-3 py-2 text-xs cursor-pointer hover:bg-primary-50 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100">
                                                                        <div class="font-semibold" x-text="i.invoice_number || '—'"></div>
                                                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 whitespace-normal" x-text="i.customer || ''"></div>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                            <button x-show="row.invoice" x-cloak type="button"
                                                                    @click="row.invoice = null; row.invoiceQuery=''; row.invoiceResults=[]"
                                                                    class="mt-1 text-[10px] text-red-500 hover:underline">×&nbsp;clear</button>
                                                        </div>
                                                    </td>

                                                    <td class="px-3 py-2 text-right align-top">
                                                        <button type="button" @click="removeRow(idx)" class="text-red-500 hover:text-red-700" title="Remove this file">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Submission progress / errors --}}
                            <div x-show="submitting" x-cloak class="rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-sm text-blue-800 dark:text-blue-200">
                                <i class="fas fa-spinner fa-spin mr-1"></i>
                                Uploading <span x-text="rows.length"></span> file(s)…
                            </div>
                            <div x-show="submitErrors.length > 0" x-cloak class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-xs text-red-800 dark:text-red-200">
                                <div class="font-semibold mb-1">Some files failed:</div>
                                <ul class="list-disc list-inside">
                                    <template x-for="err in submitErrors" :key="err.index">
                                        <li>
                                            <span class="font-mono" x-text="err.filename"></span>
                                            — <span x-text="err.error"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                    </div>{{-- end scrollable body --}}

                    {{-- Footer (sticky, outside scroll area) --}}
                    <div class="flex flex-row-reverse gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 shrink-0">
                        <button type="button" @click="submit()" :disabled="!canSubmit || submitting"
                                :style="(!canSubmit || submitting) ? '' : 'color: #ffffff;'"
                                class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-sm font-medium text-white hover:bg-primary-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 disabled:text-gray-500 disabled:cursor-not-allowed">
                            <span x-show="!submitting" class="text-white">
                                <i class="fas fa-save mr-1 text-white"></i>Save <span x-text="rows.length" class="text-white"></span> <span class="text-white">Bilty(s)</span>
                            </span>
                            <span x-show="submitting" class="text-white">
                                <i class="fas fa-spinner fa-spin mr-1 text-white"></i>Uploading…
                            </span>
                        </button>
                        <button type="button" @click="open = false" :disabled="submitting"
                                class="inline-flex justify-center items-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50">
                            Cancel
                        </button>
                    </div>
                </div>{{-- end modal root --}}
                </div>{{-- end centered flex --}}
            </div>{{-- end outer scroll container --}}
        </div>{{-- end fixed inset overlay --}}
    </div>

    <script>
        function builtyAddModal() {
            return {
                open: false,
                submitting: false,
                submitErrors: [],

                // Builty Number is generated server-side by Builty::booted.
                // We fetch the next sequence on modal open so each row in the
                // table can SHOW the number it will receive (BLT-YYYY-N,
                // BLT-YYYY-N+1, …) — strictly informational, real numbers
                // are pinned under a row-lock at insert time.
                numberPreview: { prefix: 'BLT-' + (new Date()).getFullYear() + '-', nextSeq: 1 },

                // The per-row file list. Each row holds: file (File object),
                // builty_number (text), customer / order / invoice (the same
                // shape the bulk pickers return). All three pickers default to
                // the Bulk Apply selection at applyToAll() time.
                rows: [],
                uidCounter: 0,

                init() {
                    if (new URLSearchParams(window.location.search).get('add') === '1') {
                        this.open = true;
                    }
                    // Refresh the auto-number preview whenever the modal
                    // opens so concurrent uploads from other admins don't
                    // leave the preview stale.
                    this.$watch('open', (val) => {
                        if (val) this.fetchNextNumber();
                    });
                    this.fetchNextNumber();
                },

                async fetchNextNumber() {
                    try {
                        const oldPrefix  = this.numberPreview.prefix;
                        const oldNextSeq = this.numberPreview.nextSeq;
                        const r = await fetch("{{ route('builties.nextNumberPreview') }}", {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (r.ok) {
                            const j = await r.json();
                            this.numberPreview = {
                                prefix:  j.prefix  || this.numberPreview.prefix,
                                nextSeq: j.next_seq || 1,
                            };
                            // Any row whose builty_number still matches the
                            // OLD predicted value (i.e. the user hadn't edited
                            // it) gets bumped to the freshly fetched preview.
                            // Rows the user has customised are left alone.
                            this.rows.forEach((r, idx) => {
                                const stale = oldPrefix + (oldNextSeq + idx);
                                if (r.builty_number === stale) {
                                    r.builty_number = this.previewBuiltyNumber(idx);
                                }
                            });
                        }
                    } catch (e) { /* keep current preview on failure */ }
                },

                previewBuiltyNumber(idx) {
                    return this.numberPreview.prefix + (this.numberPreview.nextSeq + idx);
                },

                get canSubmit() {
                    if (this.rows.length === 0) return false;
                    // Order is the only client-required field; Builty
                    // numbers are generated server-side.
                    return this.rows.every(r => !!r.order);
                },

                onFilesPicked(e) {
                    const files = Array.from(e.target.files || []);
                    if (files.length + this.rows.length > 200) {
                        alert('Max 200 files per upload. Trimming.');
                    }
                    const room = 200 - this.rows.length;
                    files.slice(0, room).forEach((file) => {
                        const newIdx = this.rows.length;
                        this.rows.push({
                            uid: ++this.uidCounter,
                            file,
                            // Builty Number is pre-filled with the predicted
                            // auto-generated value but stays editable per row.
                            // On submit we send whatever the user has there;
                            // if blank, the server falls back to its own
                            // auto-generation in Builty::booted.
                            builty_number: this.previewBuiltyNumber(newIdx),
                            // Each row owns its own search state so multiple
                            // dropdowns can be open without clobbering each
                            // other's results.
                            customerQuery: '', customerResults: [], customer: null,
                            orderQuery: '',    orderResults: [],    order: null,
                            invoiceQuery: '',  invoiceResults: [],  invoice: null,
                        });
                    });
                    // Reset input so picking the same file again works.
                    e.target.value = '';
                },

                removeRow(idx) {
                    this.rows.splice(idx, 1);
                },

                copyRowOneToAll() {
                    if (this.rows.length < 2) return;
                    const src = this.rows[0];
                    for (let i = 1; i < this.rows.length; i++) {
                        const r = this.rows[i];
                        if (src.customer) {
                            r.customer = src.customer;
                            r.customerQuery = src.customerQuery;
                        }
                        if (src.order) {
                            r.order = src.order;
                            r.orderQuery = src.orderQuery;
                        }
                        if (src.invoice) {
                            r.invoice = src.invoice;
                            r.invoiceQuery = src.invoiceQuery;
                        }
                    }
                },

                formatBytes(b) {
                    if (b < 1024) return b + ' B';
                    if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
                    return (b / (1024 * 1024)).toFixed(1) + ' MB';
                },

                // ── Per-row search endpoints ──
                async searchCustomersForRow(row) {
                    const url = "{{ route('builties.searchCustomers') }}?q=" + encodeURIComponent(row.customerQuery || '');
                    const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const j = await r.json();
                    row.customerResults = j.data || [];
                },
                pickCustomerForRow(row, c) {
                    row.customer = c;
                    row.customerQuery = c.customer_name + ' (' + c.customer_id + ')';
                    row.customerResults = [];
                },

                async searchOrdersForRow(row) {
                    const url = "{{ route('builties.searchOrders') }}?q=" + encodeURIComponent(row.orderQuery || '');
                    const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const j = await r.json();
                    row.orderResults = j.data || [];
                },
                pickOrderForRow(row, o) {
                    row.order = o;
                    row.orderQuery = o.order_number;
                    row.orderResults = [];
                },

                async searchInvoicesForRow(row) {
                    const url = "{{ route('builties.searchInvoices') }}?q=" + encodeURIComponent(row.invoiceQuery || '');
                    const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const j = await r.json();
                    row.invoiceResults = j.data || [];
                },
                pickInvoiceForRow(row, i) {
                    row.invoice = i;
                    row.invoiceQuery = i.invoice_number || ('#' + i.id);
                    row.invoiceResults = [];
                },

                async submit() {
                    if (!this.canSubmit || this.submitting) return;
                    this.submitting = true;
                    this.submitErrors = [];

                    const fd = new FormData();
                    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
                    this.rows.forEach((row, i) => {
                        fd.append('files[]', row.file);
                        // builty_number is editable now — send whatever the
                        // user has in the field; if blank, the server falls
                        // back to its own auto-generation in Builty::booted.
                        fd.append(`metadata[${i}][builty_number]`, (row.builty_number || '').trim());
                        fd.append(`metadata[${i}][order_id]`, row.order?.id ?? '');
                        if (row.customer)
                            fd.append(`metadata[${i}][customer_id]`, row.customer.customer_id ?? row.customer.id);
                        if (row.invoice)
                            fd.append(`metadata[${i}][invoice_id]`, row.invoice.id);
                    });

                    try {
                        const r = await fetch("{{ route('builties.bulkStore') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            body: fd,
                        });
                        const j = await r.json();
                        if (!r.ok) {
                            this.submitErrors = [{ index: -1, filename: 'request', error: j.message || ('HTTP ' + r.status) }];
                            return;
                        }
                        this.submitErrors = j.errors || [];
                        if (this.submitErrors.length === 0) {
                            window.location.reload();
                        }
                    } catch (e) {
                        this.submitErrors = [{ index: -1, filename: 'network', error: e.message }];
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
