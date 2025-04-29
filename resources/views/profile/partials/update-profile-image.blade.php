<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Image') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Update your profile image.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update.image') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="profile_photo" :value="__('Profile Image')" />
            <x-text-input id="profile_photo" name="profile_photo" type="file"
                class="block w-full shadow-sm rounded-lg text-sm
                focus:outline-primary-500
            file:bg-gray-50 file:border-0
            file:me-4
            file:py-3 file:px-4
            dark:file:bg-neutral-700 dark:file:text-neutral-400"
                :value="old('profile_photo')" required />
            <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />

            @if ($user->profile_photo)
                <div class="mt-4">
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-full">
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-image-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
