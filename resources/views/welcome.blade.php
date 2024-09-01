<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ isSidebarOpen: false, isDropdownOpen: false, darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))"
    :class="{ 'dark': darkMode }">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

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

    <body class="h-full bg-gray-50 dark:bg-neutral-900 dark:text-gray-300">

        <div class="flex min-h-full flex-col bg-gray-50 dark:bg-neutral-900 dark:text-gray-300">
            <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div class="sm:mx-auto sm:w-full sm:max-w-md">
                    <img class="mx-auto h-10 w-auto"
                        src="https://tailwindui.com/img/logos/mark.svg?color=orange&amp;shade=600" alt="Your Company">
                    <h2
                        class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900 dark:text-gray-100">
                        Sign in to
                        your account</h2>
                </div>

                <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
                    <div class="bg-white dark:bg-neutral-800 px-6 py-9 shadow sm:rounded-lg sm:px-12">
                        <div class="text-center mb-4">
                            <h1 class="block text-2xl font-bold text-gray-800 dark:text-white">{{ config('app.name') }}
                            </h1>
                        </div>
                        <form class="space-y-6" method="POST" action="{{ route('login') }}">
                            @csrf
                            <!-- Form Group -->
                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <div class="relative">
                                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                                        :value="old('email')" required autofocus autocomplete="username" />
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>
                            </div>
                            <!-- End Form Group -->

                            <!-- Form Group -->
                            <div>
                                <div class="flex justify-between items-center">
                                    <x-input-label for="password" :value="__('Password')" />
                                </div>
                                <div class="relative">
                                    <x-text-input id="password" class="block mt-1 w-full" type="password"
                                        name="password" required autocomplete="current-password" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>
                            </div>
                            <!-- End Form Group -->

                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <x-checkbox id="remember-me" name="remember-me" />
                                    <x-input-label for="remember-me"
                                        class="ml-2 mt-2 block text-sm leading-6 text-gray-900 font-normal"
                                        :value="__('Remember me')" />
                                </div>

                                <div class="text-sm leading-6">
                                    <x-link href="">Forgot
                                        password?</x-link>
                                </div>
                            </div>

                            <div>
                                <x-primary-button class="w-full">
                                    {{ __('Log in') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Scripts -->
        @livewireScripts
        @filamentScripts
        @vite('resources/js/app.js')

        @stack('scripts')
    </body>

</html>
