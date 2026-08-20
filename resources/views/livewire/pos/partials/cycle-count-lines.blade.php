<div class="space-y-2">
    @if ($record->notes)
        <p class="text-xs text-gray-500 dark:text-gray-400 italic mb-3">{{ $record->notes }}</p>
    @endif
    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        @foreach ($record->lines as $line)
            @php $variance = (float) $line->variance; @endphp
            <div class="flex items-center justify-between py-2 text-xs">
                <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ $line->item_code }}</span>
                <span class="text-gray-400">Sys: {{ number_format($line->system_qty, 3) }}</span>
                <span class="text-gray-600 dark:text-gray-300">Counted: {{ number_format($line->counted_qty, 3) }}</span>
                <span class="font-black {{ $variance == 0 ? 'text-gray-300 dark:text-gray-600' : ($variance > 0 ? 'text-emerald-600' : 'text-rose-600') }}">
                    {{ $variance == 0 ? 'Match' : ($variance > 0 ? '+' . number_format($variance, 3) : number_format($variance, 3)) }}
                </span>
            </div>
        @endforeach
    </div>
</div>
