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
        <div class="w-full lg:ps-64">
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
        
        <script>
            // Keep-alive script to prevent 419 Page Expired errors
            (function() {
                const KEEP_ALIVE_INTERVAL = 2 * 60 * 1000; // 2 minutes (more frequent)
                const KEEP_ALIVE_URL = "{{ route('keep-alive') }}";
                const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                
                function pingServer() {
                    fetch(KEEP_ALIVE_URL, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        // If we get a 419, it means the session has expired
                        if (response.status === 419) {
                            if (confirm('Your session has expired. Would you like to refresh the page to log in again?')) {
                                window.location.reload();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Keep-alive ping failed:', error);
                    });
                }

                // Initial ping after 2 minutes
                setInterval(pingServer, KEEP_ALIVE_INTERVAL);
                
                // Also ping on visibility change (when user comes back to tab)
                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'visible') {
                        pingServer();
                    }
                });
            })();
        </script>
    </body>

</html>
