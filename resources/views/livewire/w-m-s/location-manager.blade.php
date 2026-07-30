<div class="w-full space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">Warehouse (WMS)</span>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Locations &amp; Racking</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Map warehouse zones, aisles, racks and bins — each location auto-generates a scannable QR.</p>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="p-4 text-sm font-medium text-green-700 bg-green-100 dark:bg-green-900/40 dark:text-green-300 rounded-lg border border-green-200 dark:border-green-800">
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Create Location Form --}}
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 h-fit">
            <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-5">Create Location</h3>
            <form wire:submit.prevent="saveLocation" class="space-y-4">

                <div>
                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Location Type</label>
                    <select wire:model.live="type"
                        class="w-full h-9 text-sm border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm px-3">
                        <option value="zone">Zone (Macro)</option>
                        <option value="aisle">Aisle</option>
                        <option value="rack">Rack</option>
                        <option value="bin">Bin (Pick Face)</option>
                        <option value="logical_bin">Logical Bin (Floor)</option>
                    </select>
                </div>

                @if($type !== 'zone')
                <div>
                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Parent Location</label>
                    <select wire:model="parent_id"
                        class="w-full h-9 text-sm border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm px-3">
                        <option value="">Select Parent...</option>
                        @foreach($locations as $loc)
                            @if($loc->type !== 'bin' && $loc->type !== 'logical_bin')
                                <option value="{{ $loc->id }}">{{ $loc->location_code }} ({{ strtoupper($loc->type) }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Location Code</label>
                    <x-text-input name="location_code" type="text" wire:model="location_code" placeholder="e.g. Z01-A12-R03-B04" class="w-full" />
                    @error('location_code') <span class="text-xs text-red-500 mt-1 font-bold block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-black text-gray-700 dark:text-gray-300 uppercase mb-1">Description</label>
                    <textarea wire:model="description" rows="2"
                        class="w-full text-sm border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm px-3 py-2 resize-none"></textarea>
                </div>

                <x-primary-button type="submit" class="w-full justify-center py-2.5">
                    Save &amp; Generate QR
                </x-primary-button>
            </form>
        </div>

        {{-- Mapped Locations Table --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Mapped Locations</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3 text-left w-28">Type</th>
                            <th class="px-5 py-3 text-left">Code</th>
                            <th class="px-5 py-3 text-left">Parent</th>
                            <th class="px-5 py-3 text-center w-20">QR</th>
                            <th class="px-5 py-3 text-right w-20">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($locations as $loc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-5 py-3 align-middle whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase
                                        {{ $loc->type === 'bin' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                           ($loc->type === 'rack' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                                           ($loc->type === 'aisle' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                                           'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300')) }}">
                                        {{ $loc->type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 align-middle whitespace-nowrap">
                                    <span class="font-black text-gray-900 dark:text-gray-50">{{ $loc->location_code }}</span>
                                    @if($loc->description)
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $loc->description }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 align-middle whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ $locations->firstWhere('id', $loc->parent_id)?->location_code ?? '—' }}
                                </td>
                                <td class="px-5 py-3 align-middle text-center">
                                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($loc->qr_code) }}" target="_blank">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode($loc->qr_code) }}"
                                             class="h-9 w-9 rounded border border-gray-200 dark:border-gray-600 p-0.5 bg-white mx-auto hover:shadow-md transition-shadow"
                                             alt="QR {{ $loc->location_code }}" />
                                    </a>
                                </td>
                                <td class="px-5 py-3 align-middle text-right whitespace-nowrap">
                                    <button wire:click="deleteLocation({{ $loc->id }})"
                                        wire:confirm="Delete {{ $loc->location_code }}?"
                                        class="text-[10px] font-black uppercase text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 transition-colors">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500 italic text-sm">
                                    No locations mapped yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
