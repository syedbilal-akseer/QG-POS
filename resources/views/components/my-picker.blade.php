@props(['name', 'options' => '{}'])

<div x-data="{ pickerOptions: {{ json_encode($options) }} }" class="easepick-wrapper">
    <input
        type="text"
        x-ref="input"
        x-picker="pickerOptions"
        name="{{ $name }}"
        id="{{ $attributes->get('id') }}"
        placeholder="Select date"
        {!! $attributes->merge([
            'class' => 'form-control py-3 px-4 block w-full border-gray-200 rounded-lg text-sm ring-1 ring-inset ring-gray-300 dark:ring-neutral-700 focus:ring-2 sm:text-sm sm:leading-6 focus:border-primary-600 focus:ring-primary-600 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-primary-600',
        ]) !!}
    />
</div>
