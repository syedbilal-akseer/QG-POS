@props(['name', 'label', 'minYear' => null])
<div x-data="{
    month: @this.get('{{ $name }}').split(' ')[0] || 'January',
    year: @this.get('{{ $name }}').split(' ')[1] || new Date().getFullYear(),
    minYear: {{ $minYear ? $minYear : 'new Date().getFullYear()' }},
    currentMonth: new Date().toLocaleString('default', { month: 'long' }), // Get current month
    currentYear: new Date().getFullYear(),
    months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    isOpen: false,
    toggleDropdown() {
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            const value = $wire.{{ $name }}.split(' ');
            this.month = value[0] || new Date().toLocaleString('default', { month: 'long' });
            this.year = value[1] || new Date().getFullYear();
        }
    },
    closeDropdown() {
        this.isOpen = false;
    },
    openDropdown() {
        this.isOpen = true;
    },
    handleBlur(event) {
        if (!this.$refs.dropdown.contains(event.relatedTarget)) {
            this.closeDropdown();
        }
    },
    updateValue() {
        @this.set('{{ $name }}', this.month + ' ' + this.year);
    },
    isDisabled(monthIndex) {
        // Disable months before the current month when year is the same as current year or minYear
        const currentMonthIndex = this.months.indexOf(this.currentMonth);
        return this.year == this.minYear && this.year == this.currentYear && monthIndex < currentMonthIndex;
    }
}" class="relative mt-4">

    <x-input-label :for="$attributes->get('id')" :value="$label" class="text-gray-700 dark:text-gray-300" />

    @php
        $isInvalid = $errors->has($name);
    @endphp

    <input type="text" name="{{ $name }}" x-bind:value="month ? month + ' ' + year : ''"
        x-on:click="toggleDropdown()" x-on:blur="handleBlur($event)" x-on:input="updateValue()" {!! $attributes->merge([
            'class' =>
                'form-control py-3 px-4 block w-full border-gray-200 rounded-lg text-sm ring-1 ring-inset ring-gray-300 dark:ring-neutral-700 focus:ring-2 sm:text-sm sm:leading-6 focus:border-primary-600 focus:ring-primary-600 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-primary-600' .
                ($isInvalid ? ' border-red-500 dark:border-red-500' : ''),
        ]) !!}
        placeholder="Select month" readonly />

    <div x-show="isOpen" x-on:blur.away="closeDropdown()" x-ref="dropdown"
        class="absolute z-10 mt-2 w-full bg-white border border-gray-300 rounded shadow-lg dark:bg-gray-800">
        <div class="grid grid-cols-3 gap-2 p-4">
            <template x-for="(m, index) in months" :key="index">
                <button type="button" x-text="m"
                    :disabled="isDisabled(index)"
                    x-bind:class="month === m ? 'bg-primary-600 text-white' : 'text-gray-800 dark:text-gray-200'"
                    class="py-2 px-4 text-left hover:bg-primary-600 hover:text-white dark:hover:bg-primary-600 disabled:opacity-50"
                    x-on:click="month = m;
                            updateValue();
                            closeDropdown();
                            $dispatch('month-selected', { month: month, year: year }); // Emit event
                    "></button>
            </template>
        </div>
        <div class="flex justify-between items-center p-2">
            <!-- Hide the minus button if the current year is equal to the minYear -->
            <button type="button" x-show="year > minYear"
                class="px-2 py-1 text-sm bg-gray-200 rounded hover:bg-primary-600 dark:bg-gray-700 dark:hover:bg-primary-600"
                x-on:click.prevent="year--; updateValue(); $event.stopPropagation()">-</button>
            <span x-text="year" class="text-lg font-bold"></span>
            <button type="button"
                class="px-2 py-1 text-sm bg-gray-200 rounded hover:bg-primary-600 dark:bg-gray-700 dark:hover:bg-primary-600"
                x-on:click.prevent="year++; updateValue(); $event.stopPropagation()">+</button>
        </div>
    </div>

    <x-input-error class="mt-2" :messages="$errors->get($name)" />
</div>
