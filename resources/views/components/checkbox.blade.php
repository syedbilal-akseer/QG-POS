@props(['disabled' => false, 'name'])

@php
    $isInvalid = $errors->has($name);
@endphp

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge([
    'type' => 'checkbox',
    'class' =>
        'h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600' .
        ($isInvalid ? ' border-red-500' : '') .
        ' dark:border-gray-600 dark:bg-neutral-900 dark:checked:bg-primary-600 dark:checked:border-primary-600' .
        ($isInvalid ? ' dark:border-red-500' : ''),
]) !!} name="{{ $name }}">
