@props(['pageTitle'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - {{ $pageTitle ? $pageTitle : null }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        @vite('resources/css/app.css')

        @stack('styles')
    </head>

    <body class="h-full bg-gray-50 dark:bg-neutral-900 dark:text-gray-300" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
        x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))" :class="{ 'dark': darkMode }">
        <main id="content" class="flex min-h-full flex-col bg-gray-50 dark:bg-neutral-900 dark:text-gray-300">
            {{ $slot }}
        </main>

        <!-- Scripts -->
        @vite('resources/js/app.js')

        @stack('scripts')
    </body>

</html>
