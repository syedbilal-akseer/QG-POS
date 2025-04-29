<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full"
    x-data="{
        isSidebarOpen: false,
        isDropdownOpen: false,
        darkMode: localStorage.getItem('darkMode') === 'true' || localStorage.getItem('darkMode') === null
    }"
    x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))"
    :class="{ 'dark': darkMode }">

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

            /* Target the div immediately following the fi-modal-close-overlay element */
            .fi-modal-close-overlay+div {
                z-index: 9999 !important;
            }

            select:not(.choices) {
                background-image: none !important;
            }
        </style>

        @livewireStyles
        @filamentStyles
        @vite('resources/css/app.css')

        @stack('styles')
    </head>

    <body class="font-sans antialiased h-full bg-white dark:bg-neutral-900 dark:text-gray-300"
        @keydown.escape.window="isSidebarOpen = false">

        <div>
            <!-- Static sidebar for desktop -->
            <x-responsive-sidebar />

            <!-- Static sidebar for desktop -->
            <x-sidebar />

            <div class="lg:pl-72">
                <!-- Toast -->
                <x-toast />

                <!-- Header -->
                <x-header :pageTitle="$pageTitle" />

                <!-- Main content -->
                <main class="py-5 dark:bg-neutral-900">
                    <div class="px-4 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>

            @livewire('notifications')

            @livewire('database-notifications')

            <!-- Scripts -->
            @livewireScriptConfig
            @filamentScripts
            @vite('resources/js/app.js')

            @stack('scripts')
    </body>

</html>
