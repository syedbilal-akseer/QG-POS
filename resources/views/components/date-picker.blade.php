@props(['name', 'label', 'options' => '{}'])

<div x-data="{
    inputValue: $wire.entangle('{{ $name }}'), // Sync with Livewire model
    pickerOptions: {{ $options }},
    initPicker() {
        const input = $el.querySelector('input');

        window.addEventListener('date-selected', (event) => {
            // Check if the inputId matches this component's input ID
            if (event.detail.inputId === input.id) {
                this.inputValue = event.detail.date; // Update the Alpine value
                @this.set('{{ $name }}', this.inputValue); // Update the Livewire model
            }
        });
    }
}"
x-init="initPicker()"
class="mt-4 easepick-wrapper">
    <x-input-label :for="$attributes->get('id')" :value="$label" class="text-gray-700 dark:text-gray-300" />

    @php
        $isInvalid = $errors->has($name);
    @endphp

    <input type="text" id="{{ $attributes->get('id') }}" name="{{ $name }}" x-picker="pickerOptions"
           x-model="inputValue"
           {!! $attributes->merge([
               'class' =>
                   'form-control py-3 px-4 block w-full border-gray-200 rounded-lg text-sm ring-1 ring-inset ring-gray-300 dark:ring-neutral-700 focus:ring-2 sm:text-sm sm:leading-6 focus:border-primary-600 focus:ring-primary-600 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-primary-600' .
                   ($isInvalid ? ' border-red-500 dark:border-red-500' : ''),
           ]) !!}
           placeholder="Select date" />

    <x-input-error class="mt-2" :messages="$errors->get($name)" />
</div>
