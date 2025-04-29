<x-guest-layout pageTitle="Login">

    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <x-application-logo class="w-20 h-20" />
            <h2 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900 dark:text-gray-100">
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
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                                required autocomplete="current-password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                    </div>
                    <!-- End Form Group -->

                    {{-- <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <x-checkbox id="remember-me" name="remember-me" />
                            <x-input-label for="remember-me"
                                class="ml-2 mt-2 block text-sm leading-6 text-gray-900 font-normal" :value="__('Remember me')" />
                        </div>

                        <div class="text-sm leading-6">
                            <x-link href="{{ route('password.request') }}">Forgot
                                password?</x-link>
                        </div>
                    </div> --}}

                    <div>
                        <x-primary-button class="w-full">
                            {{ __('Log in') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-guest-layout>
