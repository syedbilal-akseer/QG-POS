<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - @yield('title', 'Dashboard')</title>

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

        @filamentStyles
        @vite('resources/css/app.css')

        @stack('styles')
    </head>

    <body class="font-sans antialiased bg-gray-100">
        <!-- Header -->
        <x-header :$pageTitle />
        <!-- Sidebar -->
        <x-sidebar />
        <!-- ========== MAIN CONTENT ========== -->
        <!-- Content -->
        <div class="w-full lg:ps-20">
            @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                {{ $slot }}
            </div>
        </div>
        <!-- End Content -->
        <!-- ========== END MAIN CONTENT ========== -->

        <!-- Scripts -->
        @filamentScripts
        @vite('resources/js/app.js')
        

        @stack('scripts')

        {{-- Keep-alive: refreshes the CSRF token transparently so users don't
             get the "Your session has expired" confirm() popup. The component
             also hooks Livewire/Filament requests to use the freshest token,
             so they stop tripping the "Page Expired" modal on stale-token
             POSTs. The legacy inline script that used to live here captured
             the token ONCE at page load and never refreshed it, then showed
             a Chrome confirm() dialog the first time it hit 419 — that was
             the source of the popup users were complaining about. --}}
        <x-keep-alive />
    </body>

</html>
