@props(['active'])

@php
    $classes = $active ?? false
        ? 'bg-gray-50 text-primary-600 dark:bg-neutral-700 dark:text-primary-400 group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold'
        : 'text-gray-700 hover:text-primary-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:text-primary-400 dark:hover:bg-neutral-800 group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
