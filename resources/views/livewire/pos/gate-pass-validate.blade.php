<div class="w-full max-w-2xl mx-auto space-y-6">

    <div class="pb-6 border-b border-gray-200 dark:border-gray-700">
        <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">POS</span>
        <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Gate Pass Scan</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Scan the QR on the gate pass to verify it, then validate to log passage.</p>
    </div>

    @if ($message)
        <div class="p-4 rounded-xl flex items-start gap-3
            {{ $status === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300'
            : ($status === 'warning' ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300'
            : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300') }}">
            <div class="flex-shrink-0 mt-0.5">
                @if ($status === 'success') <x-heroicon-s-check-circle class="h-5 w-5" />
                @elseif ($status === 'warning') <x-heroicon-s-exclamation-triangle class="h-5 w-5" />
                @else <x-heroicon-s-x-circle class="h-5 w-5" /> @endif
            </div>
            <span class="font-bold text-sm">{{ $message }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="relative">
            <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 pointer-events-none" />
            <input type="text" wire:model.live="scannedToken" autofocus
                placeholder="Scan gate pass QR..."
                class="w-full h-12 pl-10 pr-4 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 transition-all text-center font-mono text-sm uppercase font-bold text-gray-800 dark:text-white" />
        </div>
    </div>

    @if ($pass)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="font-mono font-black text-lg text-gray-900 dark:text-white">{{ $pass['gate_pass_number'] }}</span>
                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded
                    {{ $pass['status'] === 'validated' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400'
                    : ($pass['status'] === 'cancelled' ? 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'
                    : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400') }}">
                    {{ strtoupper($pass['status']) }}
                </span>
            </div>

            @foreach ([
                ['Type', strtoupper($pass['type'])],
                ['Order #', $pass['order_number'] ?? '—'],
                ['Customer', $pass['customer_name'] ?? '—'],
                ['Items', $pass['item_count']],
                ['Amount', $pass['total_amount'] ? 'Rs ' . number_format($pass['total_amount'], 2) : '—'],
                ['Created By', $pass['created_by'] ?? '—'],
                ['Created At', $pass['created_at']],
            ] as [$label, $value])
                <div class="flex justify-between text-xs border-b border-gray-50 dark:border-gray-700/50 pb-2">
                    <span class="text-gray-400 font-black uppercase">{{ $label }}</span>
                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ $value }}</span>
                </div>
            @endforeach

            @if ($pass['status'] === 'pending')
                <x-primary-button wire:click="validatePass" class="w-full justify-center py-3 !bg-emerald-600 hover:!bg-emerald-700">
                    ✓ Validate &amp; Log Passage
                </x-primary-button>
            @endif

            <button wire:click="resetScan" class="w-full py-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-500">
                Clear &amp; Scan Next
            </button>
        </div>
    @endif
</div>
