@props(['name', 'label', 'minYear' => null])
<div x-data="{
    selectedDays: @this.get('{{ $name }}') ? @this.get('{{ $name }}').split(', ') : [],
    daysOfWeek: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    isOpen: false,
    init() {
        this.selectedDays = @this.get('{{ $name }}') ? @this.get('{{ $name }}').split(', ') : [];
    },
    toggleDropdown() {
        this.isOpen = !this.isOpen;
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
        @this.set('{{ $name }}', this.selectedDays.join(', '));
    },
    toggleDay(day) {
        if (this.selectedDays.includes(day)) {
            this.selectedDays = this.selectedDays.filter(d => d !== day);
        } else {
            this.selectedDays.push(day);
        }
        this.updateValue();
    }
}" x-init="init()" class="relative mt-4">

    <x-input-label :for="$attributes->get('id')" :value="$label" class="text-gray-700 dark:text-gray-300" />

    @php
        $isInvalid = $errors->has($name);
    @endphp

    <input type="text" name="{{ $name }}" x-bind:value="selectedDays.length > 0 ? selectedDays.join(', ') : ''"
        x-on:click="toggleDropdown()" x-on:blur="handleBlur($event)" x-on:input="updateValue()" {!! $attributes->merge([
            'class' =>
                'form-control py-3 px-4 block w-full border-gray-200 rounded-lg text-sm ring-1 ring-inset ring-gray-300 dark:ring-neutral-700 focus:ring-2 sm:text-sm sm:leading-6 focus:border-primary-600 focus:ring-primary-600 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-primary-600' .
                ($isInvalid ? ' border-red-500 dark:border-red-500' : ''),
        ]) !!}
        placeholder="Select off days" readonly />

    <div x-show="isOpen" x-on:blur.away="closeDropdown()" x-ref="dropdown"
        class="absolute z-10 mt-2 w-full bg-white border border-gray-300 rounded shadow-lg dark:bg-gray-800">
        <div class="grid grid-cols-3 gap-2 p-4">
            <template x-for="(day, index) in daysOfWeek" :key="index">
                <button type="button" x-text="day"
                    :class="selectedDays.includes(day) ? 'bg-primary-600 text-white' : 'text-gray-800 dark:text-gray-200'"
                    class="py-2 px-4 text-left hover:bg-primary-600 hover:text-white dark:hover:bg-primary-600"
                    x-on:click="toggleDay(day)"></button>
            </template>
        </div>
    </div>

    <x-input-error class="mt-2" :messages="$errors->get($name)" />
</div>
