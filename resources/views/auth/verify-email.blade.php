<x-guest-layout pageTitle="Verify Email">

    <div class="flex min-h-[700px] flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Session Status -->
        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <x-application-logo class="w-20 h-20" />
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white dark:bg-neutral-800 px-6 py-9 shadow sm:rounded-lg sm:px-12">
                <div class="text-center mb-4">
                    <h1 class="block text-2xl font-bold text-gray-800 dark:text-white">{{ config('app.name') }}
                    </h1>
                </div>
                <form class="space-y-6" method="POST" action="{{ route('verification.send') }}">
                    @csrf

                    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-primary-button class="w-full">
                            {{ __('Resend Verification Email') }}
                        </x-primary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit"
                        class="underline text-sm mt-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-800">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
