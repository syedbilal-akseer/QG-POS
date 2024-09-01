@php
    $classes = 'text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-500 group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
