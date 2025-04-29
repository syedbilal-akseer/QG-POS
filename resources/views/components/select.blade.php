@props(['disabled' => false, 'name', 'multiple' => false, 'options' => [], 'placeholder' => 'Select an option'])

@php
    $isInvalid = $errors->has($name);
@endphp

<div x-data="{
    name: '{{ $name }}',
    options: {{ json_encode($options) }},
    multiple: {{ $multiple ? 'true' : 'false' }},
    selected: @this.get('{{ $name }}') ? @this.get('{{ $name }}') : {{ $multiple ? json_encode(old($name, [])) : json_encode(old($name, null)) }},
    isOpen: false,
    search: '',
    placeholder: '{{ $placeholder }}',
    filteredOptions() {
        return this.options.filter(option =>
            option.label.toLowerCase().includes(this.search.toLowerCase())
        );
    },
    getDisplayText() {
        if (this.multiple) {
            // If there are selected options, return the labels of the selected options
            return this.selected.length ?
                this.selected
                    .map(value => this.options.find(option => option.value === value)?.label)
                    .join(', ')
                : this.placeholder;
        }
        // For single selection, return the label of the selected option 
        return this.selected ?
            this.options.find(option => option.value == this.selected)?.label
            : this.placeholder;
    },
    toggle() {
        this.isOpen = !this.isOpen;
    },
    close() {
        this.isOpen = false;
    },
    isSelected(value) {
        return this.multiple ? this.selected.includes(value) : this.selected === value;
    },
    selectOption(value) {
        if (this.multiple) {
            // For multiple selections, toggle the value in the selected array
            if (this.selected.includes(value)) {
                this.selected = this.selected.filter(item => item !== value);
            } else {
                this.selected.push(value);
            }
        } else {
            // For single selection, set the selected value directly (not an array)
            this.selected = value;
            this.close();
        }

        // Send the correct value to Livewire:
        @this.set('{{ $name }}', this.multiple ? this.selected : this.selected); // Ensure it's a single value for single selection
        $dispatch('input', { value: this.multiple ? this.selected.join(',') : this.selected });
    }

}"


x-init="
    $watch('selected', value => {
        // Update Livewire's state whenever 'selected' changes
        @this.set(name, multiple ? value : value);
    });

    // Listen for the 'input' event to handle external updates
    $el.addEventListener('input', event => {
        selected = event.detail.value;

        // If multiple selections, ensure 'selected' is an array
        if (multiple && typeof selected === 'string') {
            selected = selected.split(',');
        }

        // Update Livewire's state with the new value
        @this.set(name, selected);
    });
"
{{ $attributes->merge(['class' => 'relative w-full ' . ($isInvalid ? 'border-red-500 dark:border-red-500' : '')]) }}
>
    <!-- Hidden Input -->
    <input type="hidden" name="{{ $name }}" x-bind:value="multiple ? selected.join(',') : selected" />

    <!-- Select Box -->
    <div x-on:click="toggle"
        class="form-control block w-full flex justify-between items-center py-3 px-4 border border-gray-300 rounded-lg text-sm ring-1 ring-inset ring-gray-300 dark:ring-neutral-700 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:border-neutral-600 dark:text-neutral-400 dark:placeholder-neutral-500
            {{ $isInvalid ? 'border-red-500 dark:border-red-500' : '' }}">
        <span x-text="getDisplayText()" class="truncate"></span>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </div>

    <!-- Dropdown -->
    <div x-show="isOpen" x-on:click.outside="close" x-transition:enter="transition ease-out duration-50"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100"
        class="absolute mt-1 w-full bg-white dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700 rounded-lg shadow-lg z-10 max-h-60 overflow-y-auto">
        <!-- Search -->
        <div class="p-2">
            <input type="text" x-model="search" placeholder="Search..."
                class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-primary-500 focus:border-primary-500
                    dark:bg-neutral-900 dark:text-neutral-400 dark:border-neutral-700" />
        </div>

        <!-- Options -->
        <ul>
            <template x-for="(option, index) in filteredOptions()" :key="index">
                <li x-on:click="selectOption(option.value); $event.target.dispatchEvent(new CustomEvent('input-select', { value: option.value }))"
                    class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-700"
                    :class="isSelected(option.value) ? 'bg-primary-100 dark:bg-primary-700 text-primary-600 dark:text-neutral-400' : ''">
                    <span x-text="option.label"></span>
                </li>
            </template>
        </ul>
    </div>
</div>
