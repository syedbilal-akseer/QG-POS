<x-app-layout>
    <x-toast />
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Documents') }}
                </h2>

                <form action="{{ route('documents.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Search customer</label>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Code or name…"
                               class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                    </div>
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Document type</label>
                        <select name="type"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                            <option value="">All types</option>
                            <option value="invoices" @selected($typeFilter === 'invoices')>Invoices only</option>
                            <option value="builties" @selected($typeFilter === 'builties')>Builties only</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-transparent bg-primary-600 hover:bg-primary-700 text-white shadow-sm">
                        Apply
                    </button>
                    @if($search || $typeFilter)
                        <a href="{{ route('documents.index') }}"
                           class="py-2 px-4 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            {{-- Totals --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Customers</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totals['customers']) }}</div>
                    <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">with at least one document</div>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-blue-700 dark:text-blue-300">Invoices</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totals['invoices']) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-yellow-700 dark:text-yellow-300">Builties</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totals['builties']) }}</div>
                </div>
            </div>

            {{-- Customer accordion list --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($customersPage as $row)
                        <div x-data="customerFolder('{{ $row->customer_code }}')"
                             class="bg-white dark:bg-gray-800">
                            {{-- Customer header (clickable, toggles all-types view) --}}
                            <button type="button"
                                    @click="toggleOpen()"
                                    class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-left">
                                <div class="flex items-center min-w-0 flex-1">
                                    <svg :style="'transform: rotate(' + (open ? 90 : 0) + 'deg); transition: transform 0.2s ease;'"
                                         class="w-4 h-4 mr-3 text-primary-600 dark:text-primary-400 shrink-0"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <i class="fas fa-folder text-amber-500 dark:text-amber-400 mr-3 text-lg shrink-0"></i>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ $row->customer_name }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Code: {{ $row->customer_code }}
                                            @if($row->customer_number)
                                                · #{{ $row->customer_number }}
                                            @endif
                                            @if($row->last_at)
                                                · last upload {{ $row->last_at->diffForHumans() }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($row->invoice_count > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                            <i class="far fa-file-pdf mr-1"></i>{{ $row->invoice_count }} invoice{{ $row->invoice_count === 1 ? '' : 's' }}
                                        </span>
                                    @endif
                                    @if($row->builty_count > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
                                            <i class="fas fa-truck mr-1"></i>{{ $row->builty_count }} builty
                                        </span>
                                    @endif
                                </div>
                            </button>

                            {{-- Expanded body — single flat file table holding
                                 BOTH invoices and builties for this customer.
                                 No more inner Invoices/Builties sub-accordions;
                                 each row carries a type badge and invoice
                                 rows surface a Has Builty column. --}}
                            <div x-show="open" x-cloak class="px-6 pb-4 bg-gray-50 dark:bg-gray-900/30">
                                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    Folder: <span class="font-mono">invoices/customers/{{ $row->customer_code }}/</span>
                                </div>
                                <template x-if="loading.all">
                                    <div class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">Loading files…</div>
                                </template>
                                <template x-if="!loading.all && files.all.length === 0">
                                    <div class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">No files.</div>
                                </template>
                                <template x-if="!loading.all && files.all.length > 0">
                                    <div x-html="renderFileTable(files.all)"></div>
                                </template>
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-folder-open text-4xl mb-3 text-gray-400 dark:text-gray-500"></i>
                            <div class="text-base">No customers with documents found.</div>
                        </div>
                    @endforelse
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $customersPage->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        function customerFolder(customerCode) {
            return {
                customerCode,
                open: false,
                loading: { all: false },
                loaded:  { all: false },
                files:   { all: [] },

                async toggleOpen() {
                    this.open = !this.open;
                    if (this.open && !this.loaded.all) {
                        await this.fetchFiles();
                    }
                },

                async fetchFiles() {
                    this.loading.all = true;
                    try {
                        // Use the named route with a placeholder so we pick up
                        // the correct /app prefix from routes/web.php (manual
                        // url('admin/...') would silently 404 and leave the
                        // panel stuck on "No files.").
                        const template = "{{ route('documents.files', ['customerCode' => '__CUSTOMER_CODE__']) }}";
                        const url = template.replace('__CUSTOMER_CODE__', encodeURIComponent(this.customerCode));
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) {
                            console.error('Documents fetch failed', r.status, url);
                            this.files.all = [];
                            this.loaded.all = true;
                            return;
                        }
                        const j = await r.json();
                        this.files.all = j.files || [];
                        this.loaded.all = true;
                    } catch (e) {
                        console.error('Failed to load documents', e);
                    } finally {
                        this.loading.all = false;
                    }
                },

                /**
                 * Render a small HTML table for the loaded files. Doing this
                 * in JS via x-html keeps the Blade template lean and avoids
                 * shipping unloaded rows in the initial page payload.
                 */
                renderFileTable(files) {
                    const fmtSize = (b) => {
                        if (b === null || b === undefined) return '—';
                        if (b < 1024) return b + ' B';
                        if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
                        return (b / (1024 * 1024)).toFixed(1) + ' MB';
                    };
                    const esc = (s) => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
                    const rows = files.map(f => {
                        // Single Amount cell (replaces the old "Meta" pill
                        // strip). Pages was dropped at the user's request —
                        // the invoice's total amount is now the only meta
                        // surfaced in the grid. Builty rows show an em-dash.
                        const amountCell = (f.amount != null && f.amount !== '')
                            ? `<span class="text-sm font-semibold text-gray-900 dark:text-gray-100">${esc(f.amount)}</span>`
                            : `<span class="text-gray-400 dark:text-gray-500">—</span>`;
                        const badge = f.badge
                            ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 ml-2">${esc(f.badge)}</span>`
                            : '';
                        // Inline styles on each action button so the colours
                        // always render — earlier diagnosis showed the
                        // compiled Tailwind bundle is from Dec 2024 and
                        // doesn't carry every utility class the recent
                        // refactors introduced (e.g. bg-yellow-600), which
                        // left some buttons invisible on light backgrounds.
                        const openBtn = f.open_url
                            ? `<a href="${esc(f.open_url)}" target="_blank" rel="noopener"
                                 style="background-color: #ea580c; color: #ffffff;"
                                 onmouseover="this.style.backgroundColor='#c2410c'"
                                 onmouseout="this.style.backgroundColor='#ea580c'"
                                 class="inline-flex items-center px-2 py-1 rounded text-xs font-medium">
                                 <i class="fas fa-external-link-alt mr-1" style="color:#ffffff;"></i>Open</a>`
                            : '';
                        const detailBtn = f.detail_url
                            ? `<a href="${esc(f.detail_url)}"
                                 style="background-color: #4b5563; color: #ffffff;"
                                 onmouseover="this.style.backgroundColor='#374151'"
                                 onmouseout="this.style.backgroundColor='#4b5563'"
                                 class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ml-2">
                                 <i class="fas fa-eye mr-1" style="color:#ffffff;"></i>Details</a>`
                            : '';
                        // Type badge differentiates invoice vs builty rows so
                        // the flat list stays scannable.
                        const typePill = f.type === 'invoice'
                            ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800"><i class="far fa-file-pdf mr-1"></i>Invoice</span>`
                            : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800"><i class="fas fa-truck mr-1"></i>Bilty</span>`;

                        // Has Builty column: only meaningful for invoice rows.
                        // Builty rows show an em-dash so the column stays
                        // visually consistent without misleading "false".
                        let hasBuiltyCell;
                        if (f.type !== 'invoice') {
                            hasBuiltyCell = `<span class="text-gray-400 dark:text-gray-500">—</span>`;
                        } else if (f.has_builty) {
                            hasBuiltyCell = `<span class="inline-flex items-center gap-1 text-green-700 dark:text-green-300 font-semibold"><i class="fas fa-check-circle"></i>Yes</span>`;
                        } else {
                            hasBuiltyCell = `<span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><i class="fas fa-minus-circle"></i>No</span>`;
                        }

                        const fileIcon = f.type === 'invoice'
                            ? `<i class="far fa-file-pdf text-red-500 mr-2"></i>`
                            : `<i class="fas fa-truck text-yellow-500 mr-2"></i>`;

                        return `
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 align-middle">
                                <td class="px-3 py-3 align-middle">
                                    <div class="flex items-center text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        ${fileIcon}
                                        <span class="truncate" title="${esc(f.label || f.name)}">${esc(f.label || f.name)}</span>
                                        ${badge}
                                    </div>
                                    ${f.sublabel ? `<div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate" title="${esc(f.sublabel)}">${esc(f.sublabel)}</div>` : ''}
                                </td>
                                <td class="px-3 py-3 align-middle whitespace-nowrap">${typePill}</td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap align-middle">${hasBuiltyCell}</td>
                                <td class="px-3 py-3 align-middle text-right whitespace-nowrap">${amountCell}</td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap align-middle">
                                    <div>${esc(f.uploaded_at || '—')}</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">by ${esc(f.uploaded_by || 'Unknown')}</div>
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap text-right align-middle">${fmtSize(f.size_bytes)}</td>
                                <td class="px-3 py-3 text-right align-middle whitespace-nowrap">${openBtn}${detailBtn}</td>
                            </tr>`;
                    }).join('');
                    return `
                        <div class="overflow-x-auto mt-2 rounded border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">File</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Has Bilty</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Uploaded</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">${rows}</tbody>
                            </table>
                        </div>`;
                },
            };
        }
    </script>
</x-app-layout>
