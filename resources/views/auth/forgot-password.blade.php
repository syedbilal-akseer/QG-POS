<x-guest-layout pageTitle="Forgot Password">

    <div class="flex min-h-[700px] flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <x-application-logo class="w-20 h-20" />
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white dark:bg-neutral-800 px-6 py-9 shadow sm:rounded-lg sm:px-12">
                <div class="text-center mb-4">
                    <h1 class="block text-2xl font-bold text-gray-800 dark:text-white">{{ config('app.name') }}
                    </h1>
                </div>

                <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
                </div>
                <form class="space-y-6" method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                            :value="old('email')" required autofocus />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button class="w-full">
                            {{ __('Email Password Reset Link') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-guest-layout>
