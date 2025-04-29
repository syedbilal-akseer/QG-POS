@props(['url' => url('/')])

@php
    $classes = 'mx-auto h-16 w-auto';
@endphp

<!--begin::Logo-->
<a href="{{ $url }}">
    <img alt="Logo" src="{{ asset('logo.png') }}" {{ $attributes->merge(['class' => $classes]) }} />
</a>
<!--end::Logo-->
