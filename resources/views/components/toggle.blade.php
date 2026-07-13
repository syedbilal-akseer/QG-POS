@props(['label'])

<div x-data="{ enabled: $wire.entangle('{{ $attributes->get('wire:model') }}') }"
     class="flex items-center cursor-pointer"
     @click="enabled = !enabled; $dispatch('input', enabled)">
    <button
        type="button"
        class="relative inline-flex items-center h-8 rounded-full w-14 focus:outline-none transition-colors duration-200 ease-in-out"
        :class="enabled ? 'bg-primary-600' : 'bg-gray-200'"
    >
        <span
            class="inline-block w-5 h-5 rounded-full bg-white transition-transform duration-200 ease-in-out"
            :class="enabled ? 'translate-x-7' : 'translate-x-1'"
        ></span>
    </button>
    <span class="ms-3 text-sm text-gray-600 dark:text-gray-300">{{ $label }}</span>
</div>
