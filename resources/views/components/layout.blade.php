<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - {{ $pageTitle ? $pageTitle : null }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        @livewireStyles
        @filamentStyles
        @vite('resources/css/app.css')

        @stack('styles')
    </head>

    <body class="font-sans antialiased" x-data="{ isSidebarOpen: false, isDropdownOpen: false, darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))"
        :class="{ 'dark': darkMode }">
        <x-toast />
        <div class="flex h-screen bg-gray-100 dark:bg-neutral-900">
            <!-- Sidebar -->
            <x-sidebar />

            <!-- Content -->
            <div class="flex-1 flex flex-col">
                <!-- Header -->
                <x-header :$pageTitle />

                <!-- Main content -->
                <main class="flex-1 p-6 overflow-y-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <!-- Scripts -->
        @livewireScripts
        @filamentScripts
        @vite('resources/js/app.js')

        @stack('scripts')
    </body>

</html>
