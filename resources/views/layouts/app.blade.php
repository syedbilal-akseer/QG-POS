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
        <div class="w-full lg:ps-64">
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
    </body>

</html>
