<x-guest-layout pageTitle="Password Confirm">

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
                    {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
                </div>
                <form class="space-y-6" method="POST" action="{{ route('password.confirm') }}">
                    @csrf
                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Password')" />

                        <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required
                            autocomplete="current-password" />

                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex justify-end mt-4">
                        <x-primary-button>
                            {{ __('Confirm') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
