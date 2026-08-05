<x-app-layout>
    <x-toast />
    <div class="py-6" x-data="documentExplorer('{{ $customer->code }}')" x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Breadcrumb --}}
            <nav class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                <a href="{{ route('documents.index') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Documents</a>
                <span class="mx-1">/</span>
                <span class="text-gray-800 dark:text-gray-200 font-medium">{{ $customer->name }}</span>
            </nav>

            <div class="flex items-center gap-3 mb-4">
                <i class="fas fa-folder-open text-amber-500 dark:text-amber-400 text-2xl"></i>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $customer->name }}</h2>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Code: {{ $customer->code }}
                        @if($customer->number)
                            · #{{ $customer->number }}
                        @endif
                    </div>
                </div>
            </div>

            {{-- Split-pane explorer --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden flex" style="height: 75vh;">

                {{-- LEFT — directory tree --}}
                <div class="w-80 shrink-0 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50/60 dark:bg-gray-900/30">
                    {{-- Search --}}
                    <div class="p-2.5 border-b border-gray-200 dark:border-gray-700 shrink-0">
                        <div class="relative">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-gray-400"></i>
                            <input type="text" x-model="query"
                                   placeholder="Search invoice / bilty…"
                                   class="w-full pl-7 pr-2 py-1.5 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto doc-tree">
                        <template x-if="loading">
                            <div class="p-6 text-center text-xs text-gray-500 dark:text-gray-400">
                                <i class="fas fa-spinner fa-spin mr-1"></i>Loading…
                            </div>
                        </template>

                        <template x-if="!loading">
                            <div class="py-2 text-sm select-none">

                                {{-- Invoices folder --}}
                                <div @click="invoicesOpen = !invoicesOpen"
                                     class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold text-gray-800 dark:text-gray-100">
                                    <i class="fas fa-chevron-right text-[10px] text-gray-400 transition-transform shrink-0" :class="invoicesOpen ? 'rotate-90' : ''"></i>
                                    <i class="fas shrink-0 text-blue-500 dark:text-blue-400" :class="invoicesOpen ? 'fa-folder-open' : 'fa-folder'"></i>
                                    <span>Invoices</span>
                                    <span class="ml-auto text-[11px] font-normal text-gray-400" x-text="filteredInvoices.length"></span>
                                </div>

                                <div x-show="invoicesOpen" x-cloak class="tree-children">
                                    <template x-if="filteredInvoices.length === 0">
                                        <div class="tree-node py-2 text-xs text-gray-400 dark:text-gray-500 italic">
                                            <span x-text="query ? 'No matches' : 'No invoices'"></span>
                                        </div>
                                    </template>

                                    <template x-for="inv in filteredInvoices" :key="inv.id">
                                        <div class="tree-node">
                                            {{-- Invoice folder — prefixed "Invoice" so it reads clearly
                                                 as a folder for that invoice, not a bare number. --}}
                                            <div @click="toggleInvoice(inv.id)"
                                                 class="flex items-center gap-2 pr-3 py-1.5 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200">
                                                <i class="fas fa-chevron-right text-[9px] text-gray-400 transition-transform shrink-0" :class="isExpanded(inv.id) ? 'rotate-90' : ''"></i>
                                                <i class="fas text-amber-500 dark:text-amber-400 text-xs shrink-0" :class="isExpanded(inv.id) ? 'fa-folder-open' : 'fa-folder'"></i>
                                                <span class="truncate"><span class="text-gray-400 dark:text-gray-500 font-normal">Invoice</span> <span x-text="inv.label"></span></span>
                                                <i x-show="inv.builties.length > 0" class="fas fa-truck text-yellow-500 text-[10px] ml-auto shrink-0" title="Has bilty"></i>
                                            </div>

                                            <div x-show="isExpanded(inv.id)" x-cloak class="tree-children">
                                                {{-- Invoice PDF leaf --}}
                                                <template x-if="inv.pdf">
                                                    <div @click="select(inv.pdf)"
                                                         class="tree-node flex items-center gap-2 pr-3 py-1.5 cursor-pointer text-xs"
                                                         :class="isSelected(inv.pdf) ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300'">
                                                        <i class="far fa-file-pdf text-red-500 shrink-0"></i>
                                                        <span class="truncate" x-text="inv.pdf.name"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!inv.pdf">
                                                    <div class="tree-node py-1.5 text-[11px] text-gray-400 dark:text-gray-500 italic">No invoice PDF</div>
                                                </template>

                                                {{-- Attached bilty(s) — separate leaves, not merged --}}
                                                <template x-for="b in inv.builties" :key="'b'+b.id">
                                                    <div @click="select(b)"
                                                         class="tree-node flex items-center gap-2 pr-3 py-1.5 cursor-pointer text-xs"
                                                         :class="isSelected(b) ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300'">
                                                        <i class="fas fa-truck text-yellow-500 shrink-0"></i>
                                                        <span class="truncate" x-text="b.label"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Builties folder — anything not yet attached to an invoice --}}
                                <div @click="builtiesOpen = !builtiesOpen"
                                     class="flex items-center gap-2 px-3 py-2 mt-1 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold text-gray-800 dark:text-gray-100">
                                    <i class="fas fa-chevron-right text-[10px] text-gray-400 transition-transform shrink-0" :class="builtiesOpen ? 'rotate-90' : ''"></i>
                                    <i class="fas shrink-0 text-yellow-500 dark:text-yellow-400" :class="builtiesOpen ? 'fa-folder-open' : 'fa-folder'"></i>
                                    <span>Builties</span>
                                    <span class="ml-auto text-[11px] font-normal text-gray-400" x-text="filteredUnattachedBuilties.length"></span>
                                </div>

                                <div x-show="builtiesOpen" x-cloak class="tree-children">
                                    <template x-if="filteredUnattachedBuilties.length === 0">
                                        <div class="tree-node py-2 text-xs text-gray-400 dark:text-gray-500 italic">
                                            <span x-text="query ? 'No matches' : 'No unattached builties'"></span>
                                        </div>
                                    </template>
                                    <template x-for="b in filteredUnattachedBuilties" :key="'u'+b.id">
                                        <div @click="select(b)"
                                             class="tree-node flex items-center gap-2 pr-3 py-1.5 cursor-pointer text-xs"
                                             :class="isSelected(b) ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300'">
                                            <i class="fas fa-truck text-yellow-500 shrink-0"></i>
                                            <span class="truncate" x-text="b.label"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- RIGHT — preview pane --}}
                <div class="flex-1 min-w-0 flex flex-col">
                    <template x-if="!selected">
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                            <i class="fas fa-file-alt text-5xl mb-3"></i>
                            <div class="text-sm">Select a file on the left to preview</div>
                        </div>
                    </template>

                    <template x-if="selected">
                        <div class="flex-1 flex flex-col min-h-0">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i class="fas" :class="selected.kind === 'invoice' ? 'far fa-file-pdf text-red-500' : 'fa-truck text-yellow-500'"></i>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" x-text="selected.label || selected.name"></span>
                                    <span x-show="selected.size" class="text-[11px] text-gray-400 dark:text-gray-500" x-text="'· ' + selected.size"></span>
                                </div>
                                <a :href="selected.open_url" target="_blank" rel="noopener"
                                   class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-orange-600 hover:bg-orange-700 shrink-0">
                                    <i class="fas fa-external-link-alt mr-1"></i>Open in new tab
                                </a>
                            </div>
                            <iframe :src="selected.open_url" class="flex-1 w-full border-0"></iframe>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Classic directory-tree connector lines: each nesting level is wrapped
         in .tree-children (a dashed vertical guide down its left edge), and
         every row inside is a .tree-node that draws a short horizontal tick
         connecting it to that vertical line. --}}
    <style>
        .tree-children {
            position: relative;
            margin-left: 1.05rem;
            padding-left: 0.85rem;
            border-left: 1px dashed #d1d5db;
        }
        :root[data-theme="dark"] .tree-children,
        .dark .tree-children {
            border-left-color: #4b5563;
        }
        .tree-node {
            position: relative;
        }
        .tree-node::before {
            content: '';
            position: absolute;
            top: 1.05rem;
            left: -0.85rem;
            width: 0.65rem;
            border-top: 1px dashed #d1d5db;
        }
        :root[data-theme="dark"] .tree-node::before,
        .dark .tree-node::before {
            border-top-color: #4b5563;
        }
    </style>

    <script>
        function documentExplorer(customerCode) {
            return {
                loading: true,
                invoices: [],
                unattachedBuilties: [],
                invoicesOpen: true,
                builtiesOpen: false,
                expanded: {},
                selected: null,
                query: '',

                async init() {
                    // Named-route placeholder so this picks up the correct
                    // /app prefix — see resources/views/admin/documents/index.blade.php
                    // history / attach-builty-modal.blade.php for why a bare
                    // url('admin/...') silently 404s here instead.
                    const template = "{{ route('documents.tree', ['customerCode' => '__CUSTOMER_CODE__']) }}";
                    const url = template.replace('__CUSTOMER_CODE__', encodeURIComponent(customerCode));
                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const j = await r.json();
                        this.invoices = j.invoices || [];
                        this.unattachedBuilties = j.unattached_builties || [];
                        // Auto-expand + preview the newest invoice's PDF so the
                        // right pane isn't empty on first load.
                        if (this.invoices.length > 0) {
                            this.expanded[this.invoices[0].id] = true;
                            if (this.invoices[0].pdf) {
                                this.select(this.invoices[0].pdf);
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load document tree', e);
                    } finally {
                        this.loading = false;
                    }
                },

                get filteredInvoices() {
                    const q = this.query.trim().toLowerCase();
                    if (!q) return this.invoices;
                    return this.invoices.filter(inv => {
                        const invMatch = (inv.label || '').toLowerCase().includes(q);
                        const builtyMatch = inv.builties.some(b => (b.label || '').toLowerCase().includes(q));
                        if (invMatch || builtyMatch) {
                            this.expanded[inv.id] = true; // surface matching bilty leaves directly
                            return true;
                        }
                        return false;
                    });
                },
                get filteredUnattachedBuilties() {
                    const q = this.query.trim().toLowerCase();
                    if (!q) return this.unattachedBuilties;
                    return this.unattachedBuilties.filter(b => (b.label || '').toLowerCase().includes(q));
                },

                toggleInvoice(id) {
                    this.expanded[id] = !this.expanded[id];
                },
                isExpanded(id) {
                    return !!this.expanded[id];
                },
                select(leaf) {
                    this.selected = leaf;
                },
                isSelected(leaf) {
                    return this.selected && this.selected.kind === leaf.kind && this.selected.open_url === leaf.open_url;
                },
            };
        }
    </script>
</x-app-layout>
