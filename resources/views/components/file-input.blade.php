@props([
    'name',
    'multiple' => false,
    'disabled' => false,
    'accept' => '' // Add the `accept` attribute
])

@php
    $isInvalid = $errors->has($name);
@endphp

<div>
    <input
        type="file"
        {{ $multiple ? 'multiple' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        {!! $attributes->merge([
            'class' =>
                'block w-full py-2 px-4 text-sm sm:text-sm sm:leading-6 rounded-lg border-gray-300 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 focus:ring-opacity-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:border-neutral-600 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-primary-600' .
                ($isInvalid ? ' border-red-500 dark:border-red-500' : ''),
        ]) !!}
        name="{{ $name }}"
        accept="{{ $accept }}"
    />

    @if ($isInvalid)
        <p class="mt-2 text-sm text-red-500">{{ $errors->first($name) }}</p>
    @endif
</div>
