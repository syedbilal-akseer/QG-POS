<div class="w-full space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">Warehouse (WMS)</span>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Directed Picking</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">FEFO hard-block + FIFO warning enforced. Scan bin → scan LPN → confirm.</p>
        </div>
        {{-- Step pills --}}
        <div class="flex items-center gap-2 flex-shrink-0 pb-0.5">
            @foreach([1 => 'Bin', 2 => 'LPN', 3 => 'Confirm'] as $s => $label)
                <div class="flex items-center gap-1.5">
                    <span class="h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-black shrink-0
                        {{ $step == $s ? 'bg-primary-600 text-white' : ($step > $s ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400') }}">
                        {{ $step > $s ? '✓' : $s }}
                    </span>
                    <span class="text-[10px] font-black uppercase hidden sm:inline
                        {{ $step == $s ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500' }}">
                        {{ $label }}
                    </span>
                </div>
                @if($s < 3) <div class="w-4 h-px bg-gray-300 dark:bg-gray-600"></div> @endif
            @endforeach
        </div>
    </div>

    {{-- Alert Banner --}}
    @if($message)
        <div class="p-4 rounded-xl flex items-start gap-3
            {{ $status === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300'
            : ($status === 'warning' ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300'
            : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300') }}">
            <div class="flex-shrink-0 mt-0.5">
                @if($status === 'success') <x-heroicon-s-check-circle class="h-5 w-5" />
                @elseif($status === 'warning') <x-heroicon-s-exclamation-triangle class="h-5 w-5" />
                @else <x-heroicon-s-x-circle class="h-5 w-5" /> @endif
            </div>
            <div>
                <p class="font-black text-sm">{{ $message }}</p>
                @if($blockDetails)
                    <div class="mt-2 space-y-0.5 text-xs">
                        <p><span class="font-black uppercase">LPN:</span> {{ $blockDetails['lpn_number'] }}</p>
                        @if(isset($blockDetails['expiry']))
                            <p><span class="font-black uppercase">Expiry:</span> {{ $blockDetails['expiry'] }}</p>
                        @endif
                        @if(isset($blockDetails['grn_date']))
                            <p><span class="font-black uppercase">GRN Date:</span> {{ $blockDetails['grn_date'] }}</p>
                            <p><span class="font-black uppercase">Location:</span> {{ $blockDetails['location'] }}</p>
                        @endif
                        <p><span class="font-black uppercase">Qty:</span> {{ number_format($blockDetails['qty']) }}</p>
                        @if($blockDetails['type'] === 'FIFO')
                            <p class="mt-1 italic opacity-75">You may proceed — the override will be logged in transactions.</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Scan Steps --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Scanner Steps</h3>
            </div>
            <div class="p-6 space-y-5">

                {{-- Step 1 --}}
                <div class="{{ $step > 1 ? 'opacity-40 pointer-events-none' : '' }}">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="h-8 w-8 rounded-full {{ $step > 1 ? 'bg-emerald-500' : 'bg-primary-600' }} text-white flex items-center justify-center font-black text-sm shrink-0">
                            {{ $step > 1 ? '✓' : '1' }}
                        </span>
                        <div>
                            <p class="font-black text-gray-700 dark:text-gray-200 uppercase text-xs tracking-widest">Scan Bin QR</p>
                            @if($activeBin)
                                <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 mt-0.5">✓ {{ $activeBin['location_code'] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass-circle class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
                        <input type="text" wire:model.live="scannedBin" autofocus
                            placeholder="Scan Bin QR Code..."
                            class="w-full h-11 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
                    </div>
                </div>

                {{-- Step 2 --}}
                <div class="{{ $step !== 2 ? 'opacity-40 pointer-events-none' : '' }}">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="h-8 w-8 rounded-full bg-primary-600 text-white flex items-center justify-center font-black text-sm shrink-0">2</span>
                        <p class="font-black text-gray-700 dark:text-gray-200 uppercase text-xs tracking-widest">Scan LPN Barcode</p>
                    </div>
                    <div class="relative">
                        <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
                        <input type="text" wire:model.live="scannedLpn"
                            placeholder="Scan LPN barcode..."
                            class="w-full h-11 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
                    </div>
                </div>

                {{-- Step 3 --}}
                <div class="{{ $step !== 3 ? 'opacity-20 pointer-events-none' : '' }} p-5 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-200 dark:border-primary-800">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="h-8 w-8 rounded-full bg-primary-600 text-white flex items-center justify-center font-black text-sm shrink-0">3</span>
                        <p class="font-black text-gray-700 dark:text-gray-200 uppercase text-xs tracking-widest">Confirm Qty</p>
                    </div>

                    @if($activeLpn)
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg mb-4 border border-gray-100 dark:border-gray-700 space-y-2">
                            @foreach([
                                ['Item',      $activeLpn['item_code']],
                                ['Lot',       $activeLpn['system_sub_lot'] ?? '—'],
                                ['GRN Date',  $activeLpn['grn_date'] ?? '—'],
                                ['Expiry',    $activeLpn['expiry_date'] ?? '—'],
                                ['Available', number_format($activeLpn['quantity']) . ' ' . strtoupper($activeLpn['uom'] ?? '')],
                            ] as [$lbl, $val])
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400 font-black uppercase">{{ $lbl }}</span>
                                    <span class="font-black text-gray-800 dark:text-white {{ $lbl === 'Expiry' ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ $val }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <button wire:click="$decrement('pickQty')"
                                class="h-11 w-11 rounded-lg bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-2xl font-bold hover:bg-gray-300 dark:hover:bg-gray-600 active:scale-95 transition-all text-gray-700 dark:text-gray-200">−</button>
                            <input type="number" wire:model="pickQty"
                                class="w-24 text-center text-2xl font-black border-none bg-transparent text-gray-900 dark:text-white focus:ring-0" />
                            <button wire:click="$increment('pickQty')"
                                class="h-11 w-11 rounded-lg bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-2xl font-bold hover:bg-gray-300 dark:hover:bg-gray-600 active:scale-95 transition-all text-gray-700 dark:text-gray-200">+</button>
                        </div>

                        <x-primary-button wire:click="confirmPick" class="w-full justify-center py-3 !bg-emerald-600 hover:!bg-emerald-700">
                            ✓ Confirm Pick
                        </x-primary-button>
                    @endif
                </div>

                <button wire:click="resetWorkflow"
                    class="w-full py-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-500 dark:hover:text-rose-400 transition-colors">
                    Cancel &amp; Reset
                </button>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Pick Rules</h3>
                <div class="space-y-4">
                    @foreach([
                        ['bg-rose-100 dark:bg-rose-900/30 text-rose-600', 'F', 'FEFO — Hard Block', 'If an older expiry exists in the same bin, picking is blocked. No override possible.'],
                        ['bg-amber-100 dark:bg-amber-900/30 text-amber-600', 'I', 'FIFO — Warning + Log', 'If older GRN-date stock exists in the warehouse, a warning fires. Override is logged.'],
                        ['bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600', '✓', 'Clean Pick', 'No older stock — proceed normally.'],
                    ] as [$badge, $char, $title, $desc])
                        <div class="flex items-start gap-3">
                            <span class="h-5 w-5 rounded-full {{ $badge }} flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">{{ $char }}</span>
                            <div>
                                <p class="text-xs font-black text-gray-700 dark:text-gray-200 uppercase">{{ $title }}</p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Session Status</h4>
                <div class="grid grid-cols-3 gap-3 text-center">
                    @foreach([
                        [$step, 'Step'],
                        [$activeBin['location_code'] ?? '—', 'Active Bin'],
                        [$blockDetails ? $blockDetails['type'] : ($activeLpn ? 'OK' : '—'), 'Rule Check'],
                    ] as [$val, $lbl])
                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-100 dark:border-gray-700">
                            <div class="text-base font-black {{ $lbl === 'Rule Check' && $blockDetails ? 'text-amber-500' : 'text-gray-900 dark:text-white' }}">
                                {{ $val }}
                            </div>
                            <div class="text-[9px] font-black text-gray-400 uppercase mt-0.5">{{ $lbl }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($activeLpn)
                <a href="{{ route('wms.pick-slip', $activeLpn['id']) }}" target="_blank"
                   class="flex items-center justify-center gap-2 w-full py-3 text-xs font-black uppercase text-primary-600 dark:text-primary-400 bg-white dark:bg-gray-800 border border-primary-200 dark:border-primary-700 rounded-xl hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-colors shadow-sm">
                    <x-heroicon-o-printer class="h-4 w-4" />
                    Print Pick Slip for this LPN
                </a>
            @endif
        </div>
    </div>
</div>
