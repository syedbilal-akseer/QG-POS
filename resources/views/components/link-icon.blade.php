@props(['icon', 'active' => false])

@php
    $classes = $active
        ? 'h-6 w-6 shrink-0 text-primary-600 dark:text-primary-400'
        : 'h-6 w-6 shrink-0 text-gray-400 group-hover:text-primary-600 dark:text-gray-300 dark:group-hover:text-primary-500';
@endphp

<x-dynamic-component
    :component="'heroicon-' . $icon"
    class="{{ $classes }}"
/>
