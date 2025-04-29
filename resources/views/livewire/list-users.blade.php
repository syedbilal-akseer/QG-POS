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
                <span x-on:click="$dispatch('close')"
                    class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </span>
            </div>
            <div class="mt-4">
                <form wire:submit.prevent="createUser">
                    @csrf

                    <!-- Form fields for creating user -->
                    <div class="mt-4">
                        <x-input-label for="new_name" :value="__('Name')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_name" name="new_name" type="text"
                            class="mt-1"
                            wire:model='new_name' required autocomplete="name" autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('new_name')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_email" :value="__('Email')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_email" name="new_email" type="email"
                            class="mt-1"
                            wire:model='new_email' required autocomplete="username" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_email')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_department_id" :value="__('Department')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_department_id" name="new_department_id" wire:model="new_department_id"
                            :options="$departments->map(fn($department) => [
                                'value' => $department->id,
                                'label' => $department->name,
                            ])->toArray()"
                            placeholder="Select Department"
                            class="mt-1"
                            required>
                        </x-select>
                        <x-input-error class="mt-2" :messages="$errors->get('new_department_id')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_role_id" :value="__('Role')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_role_id" name="new_role_id" wire:model="new_role_id"
                            :options="$roles->map(fn($role) => [
                                'value' => $role->id,
                                'label' => ucwords(str_replace('-', ' ', $role->name)),
                            ])->toArray()"
                            placeholder="Select Role"
                            class="mt-1"
                            required>
                        </x-select>
                        <x-input-error class="mt-2" :messages="$errors->get('new_role_id')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_reporting_to" :value="__('Reporting To')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_reporting_to" name="new_reporting_to"
                            wire:model="new_reporting_to"
                            :options="$users->map(fn($user) => [
                                'value' => $user->id,
                                'label' => $user->name,
                            ])->toArray()"
                            placeholder="Select Manager"
                            class="mt-1"
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('new_reporting_to')" />
                    </div>

                    <div class="mt-4">
                        <x-offdays-picker name="new_off_days" wire:model="new_off_days" label="Select Off Days" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_password" :value="__('Password')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_password" name="new_password" type="password"
                            class="mt-1"
                            wire:model='new_password' required autocomplete="new-password" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_password')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_password_confirmation" :value="__('Confirm Password')"
                            class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_password_confirmation" name="new_password_confirmation" type="password"
                            class="mt-1"
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
                    <span x-on:click="$dispatch('close')"
                        class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4">
                    <form wire:submit.prevent="updateUser" wire:key="{{ $user->id }}">
                        <!-- Form fields for editing user -->
                        <div class="mt-4">
                            <x-input-label for="name" :value="__('Name')" class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="name" name="name" type="text"
                                class="mt-1"
                                wire:model='name' required autocomplete="name" autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="email" name="email" type="email" readonly
                                class="mt-1"
                                wire:model='email' required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="department_id" :value="__('Department')" class="text-gray-700 dark:text-gray-300" />
                            <x-select id="department_id" name="department_id" wire:model="department_id"
                                :options="$departments->map(fn($departmentOption) => [
                                    'value' => $departmentOption->id,
                                    'label' => $departmentOption->name,
                                ])->toArray()"
                                placeholder="Select Department"
                                class="mt-1"
                                required>
                            </x-select>
                            <x-input-error class="mt-2" :messages="$errors->get('department_id')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="role_id" :value="__('Role')" class="text-gray-700 dark:text-gray-300" />
                            <x-select id="role_id" name="role_id" wire:model="role_id"
                                :options="$roles->map(fn($role) => [
                                    'value' => $role->id,
                                    'label' => ucwords(str_replace('-', ' ', $role->name)),
                                ])->toArray()"
                                placeholder="Select Role"
                                class="mt-1"
                                required>
                            </x-select>
                            <x-input-error class="mt-2" :messages="$errors->get('role_id')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="reporting_to" :value="__('Reporting To')" class="text-gray-700 dark:text-gray-300" />
                            <x-select id="reporting_to" name="reporting_to"
                                wire:model="reporting_to"
                                :options="$users->map(fn($user) => [
                                    'value' => $user->id,
                                    'label' => $user->name,
                                ])->toArray()"
                                placeholder="Select Manager"
                                class="mt-1"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('reporting_to')" />
                        </div>

                        <div class="mt-4">
                            <x-offdays-picker name="off_days" wire:model="off_days" label="Select Off Days" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="password" :value="__('New Password')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="password" name="password" type="password"
                                class="mt-1"
                                wire:model='password' autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Confirm New Password')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                class="mt-1"
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
