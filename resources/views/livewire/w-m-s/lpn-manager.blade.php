<div class="w-full space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">Warehouse (WMS)</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white">LPN &amp; Stock Explorer</h1>
            <p class="text-sm text-gray-500 dark:text-gray-300 mt-1">Real-time LPN tracking, FEFO/FIFO monitoring, break-bulk and relocation.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <select wire:model.live="filter_uom"
                class="h-9 text-xs font-bold border border-gray-200 dark:border-gray-700 rounded-lg px-3 bg-white dark:bg-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">All UOMs</option>
                <option value="pallet">Pallet</option>
                <option value="carton">Carton</option>
                <option value="box">Box</option>
                <option value="unit">Unit</option>
            </select>
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                <input type="text" wire:model.live="search" placeholder="Search LPN / SKU / lot..."
                    class="h-9 pl-9 pr-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm w-52 dark:text-gray-100 placeholder:text-gray-400 font-medium focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
            </div>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="p-3 text-sm font-medium text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300 rounded-lg">{{ session('message') }}</div>
    @endif
    @if(session()->has('error'))
        <div class="p-3 text-sm font-medium text-red-700 bg-red-100 dark:bg-red-900/40 dark:text-red-300 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── LPN Table ── -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex justify-between items-center">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Stock Ledger</h3>
                <span class="text-[10px] font-bold text-gray-400">{{ count($lpns) }} LPNs</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">LPN / Location</th>
                            <th class="px-4 py-3 text-left">SKU / Lot</th>
                            <th class="px-4 py-3 text-center">UOM / Qty</th>
                            <th class="px-4 py-3 text-center">Expiry / GRN Date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($lpns as $lpn)
                            @php
                                $isExpiring = $lpn->expiry_date && \Carbon\Carbon::parse($lpn->expiry_date)->diffInDays(now(), false) > -30 && \Carbon\Carbon::parse($lpn->expiry_date)->isFuture();
                                $isPast     = $lpn->expiry_date && \Carbon\Carbon::parse($lpn->expiry_date)->isPast();
                                $grnDate    = $lpn->grnLine?->grn?->received_date;
                            @endphp
                            <tr wire:key="lpn-{{ $lpn->id }}"
                                class="group hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors cursor-pointer {{ $selectedLpn?->id == $lpn->id ? 'bg-primary-50 dark:bg-primary-900/10 ring-1 ring-inset ring-primary-200 dark:ring-primary-800' : '' }}"
                                wire:click="selectLpn({{ $lpn->id }})">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-qr-code class="h-4 w-4 text-gray-400 shrink-0" />
                                        <div>
                                            <div class="font-black text-xs tracking-tight text-gray-900 dark:text-gray-50 font-mono">{{ $lpn->lpn_number }}</div>
                                            <div class="text-[10px] font-bold uppercase mt-0.5 {{ $lpn->location ? 'text-primary-600 dark:text-primary-400' : 'text-amber-500' }}">
                                                {{ $lpn->location?->location_code ?? 'STAGING / UNPLACED' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-900 dark:text-gray-50">{{ $lpn->item_code }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">{{ $lpn->system_sub_lot }}</div>
                                    @if($lpn->ou_id)
                                        <div class="text-[9px] text-gray-400">OU: {{ $lpn->ou_id }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase
                                        {{ $lpn->uom == 'pallet' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30' :
                                           ($lpn->uom == 'carton' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30' :
                                           ($lpn->uom == 'box' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30' :
                                           'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30')) }}">
                                        {{ $lpn->uom }}
                                    </span>
                                    <div class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ number_format($lpn->quantity) }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <div class="text-xs font-black {{ $isPast ? 'text-rose-600' : ($isExpiring ? 'text-amber-500' : 'text-gray-600 dark:text-gray-400') }}">
                                        {{ $lpn->expiry_date ?? '—' }}
                                    </div>
                                    @if($grnDate)
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">GRN: {{ \Carbon\Carbon::parse($grnDate)->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($lpn->uom !== 'unit')
                                            <button wire:click.stop="openBreakModal({{ $lpn->id }})"
                                                class="text-[10px] font-black text-primary-600 hover:text-white dark:text-primary-400 uppercase bg-primary-100 dark:bg-primary-900/20 hover:bg-primary-600 px-2 py-1 rounded transition-colors">
                                                Break
                                            </button>
                                        @endif
                                        <button wire:click.stop="openRelocateModal({{ $lpn->id }})"
                                            class="text-[10px] font-black text-gray-600 hover:text-white dark:text-gray-400 uppercase bg-gray-100 dark:bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded transition-colors">
                                            Move
                                        </button>
                                        <a href="{{ route('wms.labels.single', $lpn->id) }}" target="_blank"
                                            class="text-[10px] font-black text-gray-600 dark:text-gray-400 uppercase bg-gray-100 dark:bg-gray-700 hover:bg-gray-600 hover:text-white px-2 py-1 rounded transition-colors">
                                            Label
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-400 italic text-sm">No active LPNs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Side Panel ── -->
        <div class="space-y-4">
            <!-- Identity Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">LPN Identity Card</h3>

                @if($selectedLpn)
                    <div class="flex flex-col items-center mb-4">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($selectedLpn->lpn_number) }}"
                             class="h-28 w-28 rounded border border-gray-200 dark:border-gray-600 p-1 bg-white" alt="QR" />
                        <div class="mt-2 text-sm font-black tracking-widest uppercase text-gray-900 dark:text-gray-50 text-center">{{ $selectedLpn->lpn_number }}</div>
                    </div>
                    <div class="space-y-2">
                        @php
                            $grnObj = $selectedLpn->grnLine?->grn;
                            $totalLife = $selectedLpn->mfg_date && $selectedLpn->expiry_date
                                ? \Carbon\Carbon::parse($selectedLpn->mfg_date)->diffInDays($selectedLpn->expiry_date) : 0;
                            $remaining = $selectedLpn->expiry_date ? now()->diffInDays($selectedLpn->expiry_date, false) : null;
                            $consumedPct = $totalLife > 0 ? max(0, min(100, (1 - ($remaining / $totalLife)) * 100)) : null;
                            $infoRows = [
                                ['Status',   $selectedLpn->status],
                                ['Location', $selectedLpn->location?->location_code ?? 'Unplaced'],
                                ['GRN',      $grnObj?->grn_number ?? '—'],
                                ['GRN Date', $grnObj?->received_date?->format('d M Y') ?? '—'],
                                ['WH / OU',  $selectedLpn->ou_id ?? '—'],
                                ['Sup Lot',  $selectedLpn->lot_number ?? '—'],
                                ['System Lot',$selectedLpn->system_sub_lot],
                                ['Mfg',      $selectedLpn->mfg_date ?? '—'],
                                ['Expiry',   $selectedLpn->expiry_date ?? '—'],
                                ['Cost/Unit', $selectedLpn->grnLine?->cost_price ? '₨ ' . number_format($selectedLpn->grnLine->cost_price, 2) : '—'],
                                ['Children', $selectedLpn->children?->count() ?? 0],
                            ];
                        @endphp
                        @foreach($infoRows as [$lbl, $val])
                            <div class="flex justify-between text-xs border-b border-gray-50 dark:border-gray-700/50 pb-1.5">
                                <span class="text-gray-400 font-black uppercase text-[10px]">{{ $lbl }}</span>
                                <span class="font-bold text-gray-700 dark:text-gray-300">{{ $val }}</span>
                            </div>
                        @endforeach

                        @if($consumedPct !== null)
                            <div class="mt-3">
                                <div class="flex justify-between text-[10px] font-black mb-1">
                                    <span class="text-gray-400 uppercase">Shelf Life Consumed</span>
                                    <span class="{{ $consumedPct > 75 ? 'text-rose-500' : ($consumedPct > 50 ? 'text-amber-500' : 'text-emerald-500') }}">
                                        {{ number_format($consumedPct, 0) }}%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ $consumedPct > 75 ? 'bg-rose-500' : ($consumedPct > 50 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                         style="width: {{ min($consumedPct, 100) }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-10 opacity-40">
                        <x-heroicon-o-qr-code class="h-10 w-10 mx-auto mb-3 text-gray-400" />
                        <p class="text-[10px] font-bold uppercase text-gray-400">Select LPN to inspect</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Break-Bulk Modal ── --}}
    @if($showBreakModal && $breakLpnData)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase text-sm tracking-widest">Break-Bulk</h3>
                    <button wire:click="$set('showBreakModal', false)" class="text-gray-400 hover:text-gray-600"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-700 space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400 font-black uppercase">Parent LPN</span>
                            <span class="font-mono font-black text-gray-800 dark:text-white">{{ $breakLpnData['lpn_number'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400 font-black uppercase">Current UOM</span>
                            <span class="font-black text-amber-600 dark:text-amber-400 uppercase">{{ $breakLpnData['uom'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400 font-black uppercase">Total Qty</span>
                            <span class="font-black text-gray-800 dark:text-white">{{ number_format($breakLpnData['quantity']) }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400 font-black uppercase">→ Child UOM</span>
                            <span class="font-black text-primary-600 dark:text-primary-400 uppercase">{{ $breakChildUom ?? '—' }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">
                            Qty per {{ $breakChildUom ?? 'child' }} <span class="text-red-500">*</span>
                        </label>
                        <x-text-input type="number" wire:model.live="breakQtyPerParent" min="1" step="0.01"
                            placeholder="e.g. 24 units per carton" class="w-full" />
                        @if($breakQtyPerParent)
                            <p class="text-[10px] text-primary-600 dark:text-primary-400 font-black mt-1">
                                Will create {{ $breakPreviewCount }} child LPNs of {{ $breakChildUom }}
                            </p>
                        @endif
                    </div>

                    <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <p class="text-[10px] font-black text-amber-700 dark:text-amber-400">
                            Parent LPN will be marked BROKEN. Child LPNs inherit the same bin, lot, and expiry. Print new labels after breaking.
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="$set('showBreakModal', false)"
                            class="flex-1 py-3 text-sm font-black text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <x-primary-button wire:click="confirmBreakBulk" class="flex-1 justify-center py-3 !bg-rose-600 hover:!bg-rose-700">
                            Break Down
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Relocate Modal ── --}}
    @if($showRelocateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase text-sm tracking-widest">Relocate LPN</h3>
                    <button wire:click="$set('showRelocateModal', false)" class="text-gray-400 hover:text-gray-600"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">New Bin / Rack Code</label>
                        <x-text-input type="text" wire:model="relocateNewBin" placeholder="Scan or type bin code..." class="w-full font-mono" />
                    </div>
                    <div class="flex gap-3">
                        <button wire:click="$set('showRelocateModal', false)"
                            class="flex-1 py-3 text-sm font-black text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <x-primary-button wire:click="confirmRelocate" class="flex-1 justify-center py-3">
                            Confirm Move
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
