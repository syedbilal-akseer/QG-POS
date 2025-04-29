<div>
    <!-- New User Button -->
    <div class="flex justify-end mb-4">
        <x-primary-button wire:click="openNewTeamModal">
            {{ __('New Sales Team') }}
        </x-primary-button>
    </div>

    {{ $this->table }}

    <x-modal name="new_sales_team" focusable>
        <div class="p-6 bg-white dark:bg-neutral-800">
            <!-- Modal Header -->
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Add New Sales Team') }}</h2>
                <span x-on:click="$dispatch('close')"
                    class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </span>
            </div>

            <!-- Modal Body -->
            <div class="mt-4">
                <form wire:submit.prevent="createSalesTeam">
                    @csrf

                    <!-- Team Name -->
                    <div class="mt-4">
                        <x-input-label for="new_team_name" :value="__('Team Name')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_team_name" name="new_team_name" type="text"
                            class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            wire:model="new_team_name" required autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_team_name')" />
                    </div>

                    <!-- Category -->
                    <div class="mt-4">
                        <x-input-label for="new_category_id" :value="__('Category')"
                            class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_category_id" name="new_category_id" :options="$categories"
                            wire:model="new_category_id" class="mt-1 w-full" required>
                        </x-select>

                        <x-input-error class="mt-2" :messages="$errors->get('new_category_id')" />
                    </div>

                    <!-- Dynamic User and Role Assignment -->
                    <div class="mt-4">
                        <x-input-label :value="__('Assign Members with Roles')" class="text-gray-700 dark:text-gray-300" />

                        @foreach ($assignments as $index => $assignment)
                            <div class="flex items-center mt-2 space-x-2">
                                <!-- User Select -->
                                <div class="w-full">
                                    <x-select id="assignments.{{ $index }}.user_id"
                                        name="assignments.{{ $index }}.user_id"
                                        wire:model="assignments.{{ $index }}.user_id" :options="$users"
                                        placeholder="Select User" class="w-1/2" />
                                    <!-- Validation Error -->
                                    <x-input-error class="mt-2" :messages="$errors->get('assignments.' . $index . '.user_id')" />
                                </div>

                                <!-- Role Select -->
                                <div class="w-full">
                                    <x-select id="assignments.{{ $index }}.role_id"
                                        name="assignments.{{ $index }}.role_id"
                                        wire:model="assignments.{{ $index }}.role_id" :options="$roles"
                                        placeholder="Select Role" class="w-1/2" />

                                    <!-- Validation Error -->
                                    <x-input-error class="mt-2" :messages="$errors->get('assignments.' . $index . '.role_id')" />
                                </div>

                                <!-- Remove Button -->
                                <button type="button" class="text-red-500 hover:text-red-700"
                                    wire:click="removeAssignment({{ $index }})">
                                    Remove
                                </button>
                            </div>
                        @endforeach

                        <!-- Add New Assignment Button -->
                        <div class="mt-4">
                            <x-primary-button type="button" wire:click="addAssignment">
                                {{ __('Add Member') }}
                            </x-primary-button>
                        </div>
                    </div>

                </form>

            </div>
        </div>

        <!-- Modal Footer -->
        <div
            class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
            <x-secondary-button x-on:click="$dispatch('close')"
                class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                {{ __('Cancel') }}
            </x-secondary-button>
            <x-primary-button wire:click="createSalesTeam">
                {{ __('Create') }}
            </x-primary-button>
        </div>
    </x-modal>

    @if ($salesTeam)
        <x-modal name="edit_sales_team_modal" focusable>
            <div class="p-6 bg-white dark:bg-neutral-800">
                <!-- Modal Header -->
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Edit Sales Team') }}</h2>
                    <span x-on:click="$dispatch('close')"
                        class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>

                <!-- Modal Body -->
                <div class="mt-4">
                    <form wire:submit.prevent="updateSalesTeam">
                        @csrf

                        <!-- Team Name -->
                        <div class="mt-4">
                            <x-input-label for="team_name" :value="__('Team Name')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="team_name" name="team_name" type="text"
                                class="mt-1 block w-full bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                wire:model="team_name" required autocomplete="off" />
                            <x-input-error class="mt-2" :messages="$errors->get('team_name')" />
                        </div>

                        <!-- Category -->
                        <div class="mt-4">
                            <x-input-label for="category_id" :value="__('Category')"
                                class="text-gray-700 dark:text-gray-300" />
                            <x-select id="category_id" name="category_id" :options="$categories" wire:model="category_id"
                                class="mt-1 w-full" required>
                            </x-select>
                            <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
                        </div>

                        <!-- Members -->
                        <div class="mt-4">
                            <x-input-label :value="__('Assign Members with Roles')" class="text-gray-700 dark:text-gray-300" />

                            @foreach ($members as $index => $member)
                                <div class="flex items-center mt-2 space-x-2">
                                    <!-- User Select -->
                                    <div class="w-full">
                                        <x-select id="members.{{ $index }}.user_id"
                                            name="members.{{ $index }}.user_id" :options="$users"
                                            x-model="members[{{ $index }}].user_id" class="w-1/2" />
                                        <x-input-error class="mt-2" :messages="$errors->get('members.' . $index . '.user_id')" />
                                    </div>

                                    <!-- Role Select -->
                                    <div class="w-full">
                                        <x-select id="members.{{ $index }}.role_id"
                                            name="members.{{ $index }}.role_id" :options="$roles"
                                            x-model="members[{{ $index }}].role_id" class="w-1/2" />
                                        <x-input-error class="mt-2" :messages="$errors->get('members.' . $index . '.role_id')" />
                                    </div>

                                    <!-- Remove Button -->
                                    <button type="button" class="text-red-500 hover:text-red-700"
                                        wire:click="removeMember({{ $index }})">
                                        Remove
                                    </button>
                                </div>
                            @endforeach

                            <!-- Add New Member Button -->
                            <div class="mt-4">
                                <x-primary-button type="button" wire:click="addMember">
                                    {{ __('Add Member') }}
                                </x-primary-button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Modal Footer -->
            <div
                class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
                <x-secondary-button x-on:click="$dispatch('close')"
                    class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-primary-button wire:click="updateSalesTeam">
                    {{ __('Update') }}
                </x-primary-button>
            </div>
        </x-modal>
    @endif

</div>
