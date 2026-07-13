<div class="w-full space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">Warehouse (WMS)</span>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Put-Away</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Scan LPN → scan destination bin → confirm. Updates location and writes movement record.</p>
        </div>
        {{-- Step pills --}}
        <div class="flex items-center gap-2 flex-shrink-0 pb-0.5">
            @foreach([1 => 'Scan LPN', 2 => 'Scan Bin', 3 => 'Confirm'] as $s => $label)
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
            <span class="font-bold text-sm">{{ $message }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Scan Panel --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Scanner</h3>
            </div>
            <div class="p-6 space-y-5">

                {{-- Step 1 — LPN --}}
                <div class="{{ $step !== 1 ? 'opacity-40 pointer-events-none' : '' }}">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="h-8 w-8 rounded-full {{ $step > 1 ? 'bg-emerald-500' : 'bg-primary-600' }} text-white flex items-center justify-center font-black text-sm shrink-0">
                            {{ $step > 1 ? '✓' : '1' }}
                        </span>
                        <div>
                            <p class="font-black text-gray-700 dark:text-gray-200 uppercase text-xs tracking-widest">Scan LPN Barcode</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Scan the Code-128 barcode on the pallet or carton label</p>
                        </div>
                    </div>
                    <div class="relative">
                        <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
                        <input type="text" wire:model.live="scannedLpn" autofocus
                            placeholder="Scan or type LPN number..."
                            class="w-full h-11 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
                    </div>
                    @if($activeLpn)
                        <p class="mt-2 text-center text-xs font-black text-emerald-600 dark:text-emerald-400">
                            ✓ {{ $activeLpn['lpn_number'] }} — {{ $activeLpn['item_code'] }}
                        </p>
                    @endif
                </div>

                {{-- Step 2 — Bin --}}
                <div class="{{ $step !== 2 ? 'opacity-40 pointer-events-none' : '' }}">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="h-8 w-8 rounded-full {{ $step > 2 ? 'bg-emerald-500' : 'bg-primary-600' }} text-white flex items-center justify-center font-black text-sm shrink-0">
                            {{ $step > 2 ? '✓' : '2' }}
                        </span>
                        <div>
                            <p class="font-black text-gray-700 dark:text-gray-200 uppercase text-xs tracking-widest">Scan Destination Bin</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Point scanner at the bin/rack barcode on the shelf</p>
                        </div>
                    </div>
                    <div class="relative">
                        <x-heroicon-o-map-pin class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
                        <input type="text" wire:model.live="scannedBin"
                            placeholder="Scan bin QR code..."
                            class="w-full h-11 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
                    </div>
                    @if($activeBin)
                        <p class="mt-2 text-center text-xs font-black text-emerald-600 dark:text-emerald-400">
                            ✓ BIN: {{ $activeBin['location_code'] }}
                        </p>
                    @endif
                </div>

                {{-- Step 3 — Confirm --}}
                <div class="{{ $step !== 3 ? 'opacity-20 pointer-events-none' : '' }} p-5 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-200 dark:border-primary-800">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="h-8 w-8 rounded-full bg-primary-600 text-white flex items-center justify-center font-black text-sm shrink-0">3</span>
                        <p class="font-black text-gray-700 dark:text-gray-200 uppercase text-xs tracking-widest">Confirm Put-Away</p>
                    </div>

                    @if($activeLpn && $activeBin)
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 border border-gray-100 dark:border-gray-700 space-y-2">
                            @foreach([
                                ['LPN',      $activeLpn['lpn_number'], 'font-mono'],
                                ['Item',     $activeLpn['item_code'], ''],
                                ['Qty/UOM',  number_format($activeLpn['quantity']) . ' ' . strtoupper($activeLpn['uom'] ?? ''), ''],
                                ['Lot',      $activeLpn['system_sub_lot'] ?? '—', 'font-mono'],
                            ] as [$lbl, $val, $extra])
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400 font-black uppercase">{{ $lbl }}</span>
                                    <span class="font-black text-gray-800 dark:text-white {{ $extra }}">{{ $val }}</span>
                                </div>
                            @endforeach
                            <div class="border-t border-gray-100 dark:border-gray-700 pt-2 flex justify-between text-xs">
                                <span class="text-gray-400 font-black uppercase">→ Destination</span>
                                <span class="font-black text-primary-600 dark:text-primary-400 text-sm">{{ $activeBin['location_code'] }}</span>
                            </div>
                        </div>

                        <x-primary-button wire:click="confirmPutaway" class="w-full justify-center py-3 !bg-emerald-600 hover:!bg-emerald-700">
                            ✓ Confirm Put-Away
                        </x-primary-button>
                    @endif
                </div>

                <button wire:click="resetWorkflow"
                    class="w-full py-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-500 dark:hover:text-rose-400 transition-colors">
                    Cancel &amp; Reset
                </button>
            </div>
        </div>

        {{-- Detail Panel --}}
        <div class="space-y-6">

            {{-- LPN Details --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">LPN Details</h3>

                @if($activeLpn)
                    <div class="space-y-2">
                        @php
                            $rows = [
                                ['GRN #',       $activeLpn['grn_number'] ?? '—'],
                                ['GRN Date',    $activeLpn['grn_date'] ?? '—'],
                                ['Sup Lot',     $activeLpn['lot_number'] ?? '—'],
                                ['System Lot',  $activeLpn['system_sub_lot'] ?? '—'],
                                ['Mfg Date',    $activeLpn['mfg_date'] ?? '—'],
                                ['Expiry',      $activeLpn['expiry_date'] ?? '—'],
                                ['Cost/Unit',   $activeLpn['cost_price'] ? '₨ ' . number_format($activeLpn['cost_price'], 2) : '—'],
                                ['WH / OU',     $activeLpn['ou_id'] ?? '—'],
                            ];
                        @endphp
                        @foreach($rows as [$lbl, $val])
                            <div class="flex justify-between items-center text-xs border-b border-gray-50 dark:border-gray-700/50 pb-2">
                                <span class="text-gray-400 font-black uppercase">{{ $lbl }}</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200 font-mono">{{ $val }}</span>
                            </div>
                        @endforeach

                        @if($activeLpn['current_location'])
                            <div class="mt-2 p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-center border border-amber-100 dark:border-amber-800">
                                <p class="text-[10px] font-black text-amber-700 dark:text-amber-400">Currently at: {{ $activeLpn['current_location'] }}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-10 opacity-40">
                        <x-heroicon-o-qr-code class="h-10 w-10 text-gray-400 mb-3" />
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Scan LPN to view details</p>
                    </div>
                @endif
            </div>

            {{-- Pending List --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Pending Put-Away</h3>
                @php $pending = \App\Models\WMS\Lpn::where('status', 'received')->latest()->limit(8)->get(); @endphp
                @forelse($pending as $p)
                    <div class="flex justify-between items-center py-2 border-b border-gray-50 dark:border-gray-700/50 text-xs">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-clock class="h-3.5 w-3.5 text-amber-400 shrink-0" />
                            <span class="font-black text-gray-800 dark:text-gray-200 font-mono">{{ $p->lpn_number }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-right">
                            <span class="text-gray-400">{{ $p->item_code }}</span>
                            <span class="text-[10px] font-black uppercase text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-1.5 py-0.5 rounded">{{ $p->uom }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-gray-400 text-xs italic py-4">All LPNs are stored.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
