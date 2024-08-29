<div>
    <!-- New User Button -->
    <div class="flex justify-end mb-4">
        <x-primary-button wire:click="openNewUserModal">
            {{ __('Add New User') }}
        </x-primary-button>
    </div>

    {{ $this->table }}

    <x-modal name="new_user" focusable>
        <div class="p-6 bg-white dark:bg-neutral-800">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Add New User</h2>
                <button x-on:click="$dispatch('close')"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-4">
                <form wire:submit.prevent="createUser">
                    @csrf

                    <!-- Form fields for creating user -->
                    <div class="mt-4">
                        <x-input-label for="new_name" :value="__('Name')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_name" name="new_name" type="text"
                            class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            wire:model='new_name' required autocomplete="name" autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('new_name')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_email" :value="__('Email')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_email" name="new_email" type="email"
                            class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            wire:model='new_email' required autocomplete="username" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_email')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_role" :value="__('Role')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_role" name="new_role" wire:model="new_role" required
                            class="bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                            <option value="">{{ __('Select a Role') }}</option>
                            @foreach (\App\Enums\RoleEnum::getValues() as $roleOption)
                                <option value="{{ $roleOption->id }}">
                                    {{ $roleOption->name }}
                                </option>
                            @endforeach
                        </x-select>
                        <x-input-error class="mt-2" :messages="$errors->get('new_role')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_password" :value="__('Password')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_password" name="new_password" type="password"
                            class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            wire:model='new_password' required autocomplete="new-password" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_password')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_password_confirmation" :value="__('Confirm Password')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_password_confirmation" name="new_password_confirmation" type="password"
                            class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            wire:model='new_password_confirmation' required autocomplete="new-password" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_password_confirmation')" />
                    </div>
                </form>
            </div>
        </div>
        <div
            class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
            <x-secondary-button x-on:click="$dispatch('close')"
                class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                {{ __('Cancel') }}
            </x-secondary-button>
            <x-primary-button wire:click="createUser"
                class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                {{ __('Create') }}
            </x-primary-button>
        </div>
    </x-modal>



    @if ($user)
        <x-modal name="edit_user_modal" :show="true" focusable>
            <div class="p-6 bg-white dark:bg-neutral-800">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Edit User</h2>
                    <button x-on:click="$dispatch('close'); @this.closeEditModal()"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="mt-4">
                    <form wire:submit.prevent="updateUser">
                        <!-- Form fields for editing user -->
                        <div class="mt-4">
                            <x-input-label for="name" :value="__('Name')" class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="name" name="name" type="text"
                                class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                wire:model='name' required autocomplete="name" autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="email" name="email" type="email" readonly
                                class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm"
                                wire:model='email' required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="role" :value="__('Role')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-select id="role" name="role" wire:model="role" required
                                class="bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                                @foreach (\App\Enums\RoleEnum::getValues() as $roleOption)
                                    <option value="{{ $roleOption->id }}"
                                        {{ $roleOption->id === $role ? 'selected' : '' }}>
                                        {{ $roleOption->name }}
                                    </option>
                                @endforeach
                            </x-select>
                            <x-input-error class="mt-2" :messages="$errors->get('role')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="password" :value="__('New Password')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="password" name="password" type="password"
                                class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm"
                                wire:model='password' autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Confirm New Password')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm"
                                wire:model='password_confirmation' autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>
                    </form>
                </div>
            </div>
            <div
                class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
                <x-secondary-button x-on:click="$dispatch('close');"
                    class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-primary-button wire:click="updateUser"
                    class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                    {{ __('Save') }}
                </x-primary-button>
            </div>
        </x-modal>
    @endif
</div>
