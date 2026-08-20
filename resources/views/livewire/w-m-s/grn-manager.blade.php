<div class="w-full space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">Warehouse (WMS)</span>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Goods Receipt Note (GRN)</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Record inward goods, assign system sub-lots, auto-generate LPN pallets and print labels.</p>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="p-4 text-sm font-medium text-green-700 bg-green-100 dark:bg-green-900/40 dark:text-green-300 rounded-lg border border-green-200 dark:border-green-800">
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="p-4 text-sm font-medium text-red-700 bg-red-100 dark:bg-red-900/40 dark:text-red-300 rounded-lg border border-red-200 dark:border-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- GRN Form --}}
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 h-fit space-y-4">
            <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Inward Receipt Form</h3>

            <form wire:submit.prevent="saveGrn" class="space-y-4">

                {{-- WH / OU --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Warehouse OU</label>
                        <x-text-input type="text" name="ou_id" wire:model="ou_id" placeholder="e.g. 102" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">WH Name</label>
                        <x-text-input type="text" name="warehouse_name" wire:model="warehouse_name" placeholder="Mega WH" class="w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">PO Number <span class="text-red-500">*</span></label>
                    <x-text-input type="text" name="po_number" wire:model="po_number" placeholder="PO-2026-..." class="w-full" />
                    @error('po_number') <span class="text-xs text-red-500 font-bold block mt-0.5">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Supplier Name</label>
                    <x-text-input type="text" name="supplier_name" wire:model="supplier_name" class="w-full" />
                </div>

                <div class="pt-3 border-t border-gray-100 dark:border-gray-700 space-y-4">

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Item / SKU Code <span class="text-red-500">*</span></label>
                        <x-text-input type="text" name="item_code" wire:model="item_code" class="w-full" />
                        @error('item_code') <span class="text-xs text-red-500 font-bold block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Description</label>
                        <x-text-input type="text" name="description" wire:model="description" placeholder="Optional item description" class="w-full" />
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Supplier Lot #</label>
                        <x-text-input type="text" name="supplier_lot_number" wire:model="supplier_lot_number" placeholder="Original lot if exists" class="w-full" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Mfg Date</label>
                            <x-text-input type="date" name="mfg_date" wire:model="mfg_date" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Expiry Date</label>
                            <x-text-input type="date" name="expiry_date" wire:model="expiry_date" class="w-full" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Received Qty <span class="text-red-500">*</span></label>
                        <x-text-input type="number" name="received_qty" wire:model.live="received_qty" min="1" class="w-full" />
                        @error('received_qty') <span class="text-xs text-red-500 font-bold block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Landed Cost / Unit</label>
                        <x-text-input type="number" name="cost_price" wire:model="cost_price" step="0.0001" min="0" placeholder="e.g. 125.50" class="w-full" />
                        <p class="text-[10px] text-gray-400 mt-0.5">Stored for costing reference only.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Pallet / Carton Size</label>
                        <x-text-input type="number" name="pallet_size" wire:model.live="pallet_size" min="1" placeholder="Units per pallet — leave blank if not needed" class="w-full" />
                        <p class="text-[10px] text-gray-400 mt-0.5">One LPN auto-generated per pallet.</p>
                    </div>

                    {{-- LPN Preview --}}
                    @if(count($lpn_preview))
                        <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                            <p class="text-[10px] font-black uppercase text-amber-700 dark:text-amber-400 mb-2">LPN Preview</p>
                            @foreach($lpn_preview as $p)
                                <div class="flex justify-between text-[10px] font-mono text-amber-800 dark:text-amber-300">
                                    <span>#{{ $p['seq'] }}</span>
                                    <span>{{ $p['qty'] }} {{ $p['uom'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <x-primary-button type="submit" class="w-full justify-center py-2.5">
                    Save GRN &amp; Generate LPNs
                </x-primary-button>
            </form>
        </div>

        {{-- GRN List --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Recent Receipts</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 text-left">GRN # / WH</th>
                            <th class="px-4 py-3 text-left">Item / Lot</th>
                            <th class="px-4 py-3 text-center">Qty / Cost</th>
                            <th class="px-4 py-3 text-center">LPNs</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($grns as $grn)
                            @foreach($grn->lines as $line)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3 align-middle whitespace-nowrap">
                                    <div class="font-black text-gray-900 dark:text-gray-50">{{ $grn->grn_number }}</div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">{{ $grn->po_number }}</div>
                                    <div class="text-[10px] text-primary-600 dark:text-primary-400 font-bold">{{ $grn->warehouse_name ?: $grn->ou_id }}</div>
                                    <div class="text-[9px] text-gray-400">{{ $grn->received_date?->format('d M Y') }}</div>
                                </td>
                                <td class="px-4 py-3 align-middle whitespace-nowrap">
                                    <div class="font-bold text-gray-900 dark:text-gray-50">{{ $line->item_code }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">{{ $line->system_sub_lot }}</div>
                                    @if($line->expiry_date)
                                        <div class="text-[10px] text-rose-500 font-black">EXP: {{ $line->expiry_date }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-center whitespace-nowrap">
                                    <div class="font-black text-gray-900 dark:text-white">{{ number_format($line->received_qty) }}</div>
                                    @if($line->cost_price)
                                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">₨{{ number_format($line->cost_price, 2) }}/unit</div>
                                    @endif
                                    @if($line->pallet_size)
                                        <div class="text-[9px] text-amber-600 dark:text-amber-400">{{ $line->pallet_size }} units/pallet</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-center whitespace-nowrap">
                                    @if($line->lpns_generated)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            ✓ {{ $line->lpns->count() }} LPNs
                                        </span>
                                    @elseif($line->pallet_size)
                                        <button wire:click="generateLpnsManual({{ $line->id }})"
                                            class="text-[10px] font-black uppercase text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/20 px-2 py-1 rounded hover:bg-amber-200 transition-colors">
                                            Generate
                                        </button>
                                    @else
                                        <span class="text-[10px] text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($line->lpns_generated)
                                            <a href="{{ route('wms.labels.batch', $line->id) }}" target="_blank"
                                               class="inline-flex items-center gap-1 h-7 px-2.5 text-[10px] font-black uppercase text-white bg-primary-600 hover:bg-primary-700 rounded transition-colors">
                                                <x-heroicon-o-printer class="h-3 w-3" />
                                                Print
                                            </a>
                                        @endif
                                        <a href="{{ route('wms.labels.grn-qr', $line->id) }}" target="_blank">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode(json_encode(['item'=>$line->item_code,'lot'=>$line->system_sub_lot,'exp'=>$line->expiry_date,'grn'=>$grn->grn_number])) }}"
                                                 class="h-9 w-9 rounded border border-gray-200 dark:border-gray-600 p-0.5 bg-white hover:shadow-md transition-shadow" alt="QR" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400 italic text-sm">No GRNs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
