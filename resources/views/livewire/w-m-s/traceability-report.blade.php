<div class="w-full space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">Warehouse (WMS)</span>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Traceability &amp; Audit Trail</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">End-to-end digital ledger — search by LPN, item code, lot number or GRN reference.</p>
        </div>
    </div>

    {{-- Search Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Search Ledger</h3>
        <p class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-tight mb-4">
            Enter LPN number, system sub-lot, item code or GRN number to pull the full movement history.
        </p>
        <div class="flex gap-3">
            <div class="relative flex-1">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                <input type="text" wire:model.live="search"
                    placeholder="Search PO, GRN, LPN or Lot..."
                    class="w-full h-10 pl-9 pr-4 text-sm border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none font-medium" />
            </div>
            <x-primary-button wire:click="searchTrace" class="h-10 px-6 flex items-center">
                Audit
            </x-primary-button>
        </div>
    </div>

    {{-- Results --}}
    @if(!empty($results))
        <div class="space-y-3">
            @foreach($results as $tx)
                <div class="flex bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-primary-300 dark:hover:border-primary-700 transition-colors">

                    {{-- Colour strip --}}
                    <div class="w-1 flex-shrink-0
                        {{ $tx->transaction_type === 'picking'    ? 'bg-emerald-500' :
                           ($tx->transaction_type === 'break_bulk' ? 'bg-orange-500'  :
                           ($tx->transaction_type === 'put_away'   ? 'bg-blue-500'    :
                           ($tx->transaction_type === 'relocate'   ? 'bg-purple-500'  :
                           'bg-primary-500'))) }}">
                    </div>

                    <div class="flex-1 p-4 grid grid-cols-1 md:grid-cols-4 gap-4 items-center">

                        {{-- Date / Time --}}
                        <div>
                            <div class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500">{{ $tx->created_at->format('d M Y') }}</div>
                            <div class="text-sm font-black text-gray-900 dark:text-gray-50 tracking-tight">{{ $tx->created_at->format('H:i:s') }}</div>
                        </div>

                        {{-- Type / Item --}}
                        <div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase border
                                {{ $tx->transaction_type === 'picking'    ? 'border-emerald-200 text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800' :
                                   ($tx->transaction_type === 'break_bulk' ? 'border-orange-200 text-orange-600 bg-orange-50 dark:bg-orange-900/20 dark:border-orange-800' :
                                   ($tx->transaction_type === 'put_away'   ? 'border-blue-200 text-blue-600 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800' :
                                   'border-primary-200 text-primary-600 bg-primary-50 dark:bg-primary-900/20 dark:border-primary-800')) }}">
                                {{ str_replace('_', ' ', $tx->transaction_type) }}
                            </span>
                            <div class="text-sm font-black mt-1 text-gray-900 dark:text-gray-50">{{ $tx->item_code }}</div>
                        </div>

                        {{-- Movement path --}}
                        <div>
                            <div class="text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 mb-1">Movement</div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center h-6 px-2 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-black">
                                    {{ $tx->fromLocation?->location_code ?? 'IN' }}
                                </span>
                                <x-heroicon-s-arrow-right class="h-3 w-3 text-gray-400 flex-shrink-0" />
                                <span class="inline-flex items-center h-6 px-2 rounded bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 text-[10px] font-black">
                                    {{ $tx->toLocation?->location_code ?? 'OUT' }}
                                </span>
                                <span class="text-[10px] font-black text-gray-500 dark:text-gray-400">
                                    {{ number_format($tx->quantity) }}
                                </span>
                            </div>
                        </div>

                        {{-- Operator --}}
                        <div class="text-right">
                            <div class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500">Operator</div>
                            <div class="text-sm font-black text-gray-700 dark:text-gray-300">{{ $tx->user?->name ?? 'SYSTEM' }}</div>
                            @if($tx->reference)
                                <div class="text-[9px] italic text-gray-400 dark:text-gray-500 mt-0.5 truncate max-w-[180px] ml-auto">
                                    {{ $tx->reference }}
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-20 bg-white dark:bg-gray-800 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700">
            <x-heroicon-o-magnifying-glass class="h-14 w-14 text-gray-300 dark:text-gray-600 mb-3" />
            <p class="text-xs font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Enter a search term above to audit</p>
        </div>
    @endif

</div>
