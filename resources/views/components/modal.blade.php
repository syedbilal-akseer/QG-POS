@props(['name', 'show' => false, 'maxWidth' => '2xl'])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
        '6xl' => 'sm:max-w-6xl',
        '7xl' => 'sm:max-w-7xl',
        'full'   => 'sm:max-w-full',
        // Screen-size modal: 80% of viewport with a 10% gutter on every side
        // (i.e. 20% total horizontal/vertical spacing). Used by full-screen
        // detail views like the order view modal.
        'screen' => '',
    ][$maxWidth] ?? 'sm:max-w-2xl';

    // Provide inline styles for new sizes that might not be compiled by JIT yet
    $customStyles = [
        '5xl' => 'max-width: 64rem;',
        '6xl' => 'max-width: 72rem;',
        '7xl' => 'max-width: 80rem;',
        // For 'screen' the wrapper is centred by the outer flex container so
        // an 80vw × 80vh box auto-positions with 10vw / 10vh gutters. We also
        // bump max-height so the 80vh wins over the default 90vh cap.
        'screen' => 'width: 80vw; height: 80vh; max-width: 80vw; max-height: 80vh;',
    ][$maxWidth] ?? '';
@endphp

<div x-data="{
    show: @js($show),
    focusables() {
        let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
        return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'))
    },
    firstFocusable() { return this.focusables()[0] },
    lastFocusable() { return this.focusables().slice(-1)[0] },
    nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
    prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
    nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
    prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    resetValidation() {
        this.$nextTick(() => {
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(el => {
                if (el.classList.contains('border-red-500')) { el.classList.remove('border-red-500'); }
                if (el.classList.contains('dark:border-red-500')) { el.classList.remove('dark:border-red-500'); }
                el.offsetHeight;
            });
        });
    }
}" x-init="$watch('show', value => {
    if (value) {
        document.body.classList.add('overflow-y-hidden');
        {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
    } else {
        document.body.classList.remove('overflow-y-hidden');
        resetValidation();
    }
})"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null" x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false" x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()" x-show="show"
    class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6 overflow-y-auto" style="display: {{ $show ? 'flex' : 'none' }}; min-h-screen;">
    
    <!-- Background overlay -->
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false"
        x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
    </div>

    <!-- Modal window -->
    <div x-show="show" x-trap.noscroll="show"
        class="bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all w-full {{ $maxWidthClass }} mx-auto flex flex-col"
        style="max-height: 90vh; {{ $customStyles }}"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="w-full flex-1" style="overflow-y: auto; min-height: 0;">
            {{ $slot }}
        </div>
    </div>
</div>
