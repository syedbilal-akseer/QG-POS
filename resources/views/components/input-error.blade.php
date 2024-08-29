@props(['messages'])

@if ($messages)
    <p class="text-sm text-red-600 dark:text-red-400 space-y-1 mt-2">
        @foreach ((array) $messages as $message)
            <span>{{ $message }}</span>
        @endforeach
    </p>
@endif
