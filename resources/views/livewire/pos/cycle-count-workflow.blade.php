<div class="w-full space-y-6">

    <div class="pb-6 border-b border-gray-200 dark:border-gray-700">
        <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">POS</span>
        <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Cycle Count</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Scan a location, correct any quantities that don't match, submit. Compares against the portal's own on-hand — the Oracle-official reconciliation lands once Oracle exposes on-hand-by-locator (see the Scan &amp; Sync Roadmap).</p>
    </div>

    @if ($message)
        <div class="p-4 rounded-xl flex items-start gap-3
            {{ $status === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300' : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300' }}">
            <div class="flex-shrink-0 mt-0.5">
                @if ($status === 'success') <x-heroicon-s-check-circle class="h-5 w-5" />
                @else <x-heroicon-s-x-circle class="h-5 w-5" /> @endif
            </div>
            <span class="font-bold text-sm">{{ $message }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Scan Location</h3>
        <div class="relative max-w-md">
            <x-heroicon-o-map-pin class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
            <input type="text" wire:model.live="scannedLocation" autofocus
                placeholder="Scan or type location code..."
                class="w-full h-11 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
        </div>
    </div>

    @if ($location)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Counting: {{ $location['location_code'] }}</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Quantities default to system on-hand — only edit where the physical count differs.</p>
                </div>
                <div class="relative w-64">
                    <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                    <input type="text" wire:model.live="scannedItem"
                        placeholder="Scan item found but not listed..."
                        class="w-full h-9 pl-9 pr-3 bg-gray-50 dark:bg-gray-900 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-xs font-mono uppercase" />
                </div>
            </div>
            @if ($scanError)
                <p class="px-6 pt-3 text-xs font-bold text-rose-500">{{ $scanError }}</p>
            @endif

            @if (empty($lines))
                <div class="flex flex-col items-center justify-center py-16 opacity-40">
                    <x-heroicon-o-archive-box class="h-10 w-10 text-gray-400 mb-3" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">No stock on record at this location</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($lines as $itemCode => $line)
                        @php $variance = $line['counted_qty'] - $line['system_qty']; @endphp
                        <div class="px-6 py-3 flex items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm text-gray-800 dark:text-gray-200 font-mono">{{ $itemCode }}</p>
                                <p class="text-[10px] text-gray-400">System: {{ number_format($line['system_qty'], 3) }} {{ strtoupper($line['uom']) }}</p>
                            </div>
                            <input type="number" min="0" step="1"
                                wire:change="updateCounted('{{ $itemCode }}', $event.target.value)"
                                value="{{ rtrim(rtrim(number_format($line['counted_qty'], 3, '.', ''), '0'), '.') ?: '0' }}"
                                class="w-24 h-9 px-2 text-center bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-bold" />
                            <span class="w-28 text-right text-xs font-black
                                {{ $variance == 0 ? 'text-gray-300 dark:text-gray-600' : ($variance > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') }}">
                                {{ $variance == 0 ? 'Match' : ($variance > 0 ? '+' . number_format($variance, 3) : number_format($variance, 3)) }}
                            </span>
                            <button wire:click="removeLine('{{ $itemCode }}')" class="text-gray-300 hover:text-rose-500">
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-black uppercase text-gray-400">{{ count($lines) }} item(s) — {{ $this->varianceCount }} with variance</span>
                    </div>
                    <input type="text" wire:model="notes" placeholder="Notes (optional)"
                        class="w-full h-9 px-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-xs" />
                    <x-primary-button wire:click="submitCount" class="w-full justify-center py-3 !bg-emerald-600 hover:!bg-emerald-700">
                        Submit Count
                    </x-primary-button>
                </div>
            @endif
        </div>
    @endif
</div>
