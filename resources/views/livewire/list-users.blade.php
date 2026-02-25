<div>
    <!-- New User and Sync Buttons -->
    <div class="flex justify-end mb-4 gap-3">
        <x-secondary-button wire:click="syncOracleUsers" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="syncOracleUsers">{{ __('Sync Oracle Users') }}</span>
            <span wire:loading wire:target="syncOracleUsers">{{ __('Syncing...') }}</span>
        </x-secondary-button>
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
                        <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                            <strong>Important:</strong> Name should be exact as Oracle database otherwise it will cause error to fetch the data from Oracle.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_name')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_email" :value="__('Email')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_email" name="new_email" type="email"
                            class="mt-1"
                            wire:model='new_email' required autocomplete="username" />
                        <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                            <strong>Important:</strong> Email should be exact as Oracle database otherwise it will cause error to fetch the data from Oracle.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_email')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_role" :value="__('Role')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_role" name="new_role" wire:model="new_role"
                            :options="collect($roles)->map(fn($label, $value) => [
                                'value' => $value,
                                'label' => $label,
                            ])->values()->toArray()"
                            placeholder="Select Role"
                            class="mt-1"
                            required>
                        </x-select>
                        <x-input-error class="mt-2" :messages="$errors->get('new_role')" />
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
                        <x-input-label for="new_supply_chain_user_id" :value="__('Supply Chain User')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_supply_chain_user_id" name="new_supply_chain_user_id"
                            wire:model="new_supply_chain_user_id"
                            :options="$supplyChainUsers->map(fn($user) => [
                                'value' => $user->id,
                                'label' => $user->name,
                            ])->toArray()"
                            placeholder="Select Supply Chain User"
                            class="mt-1"
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('new_supply_chain_user_id')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_account_user_id" :value="__('Account User')" class="text-gray-700 dark:text-gray-300" />
                        <x-select id="new_account_user_id" name="new_account_user_id"
                            wire:model="new_account_user_id"
                            :options="$accountUsers->map(fn($user) => [
                                'value' => $user->id,
                                'label' => $user->name,
                            ])->toArray()"
                            placeholder="Select Account User"
                            class="mt-1"
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('new_account_user_id')" />
                    </div>

                    <!-- Oracle User Fields -->
                    <div class="mt-4">
                        <x-input-label for="new_oracle_user_id" :value="__('Oracle User ID')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_oracle_user_id" name="new_oracle_user_id" type="text"
                            class="mt-1"
                            wire:model="new_oracle_user_id" />
                        <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                            <strong>Optional:</strong> Oracle User ID from QG_SHIPPING_USERS table.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_oracle_user_id')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_oracle_user_name" :value="__('Oracle User Name')" class="text-gray-700 dark:text-gray-300" />
                        <x-text-input id="new_oracle_user_name" name="new_oracle_user_name" type="text"
                            class="mt-1"
                            wire:model="new_oracle_user_name" />
                        <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                            <strong>Optional:</strong> Oracle User Name from QG_SHIPPING_USERS table.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_oracle_user_name')" />
                    </div>

                    <!-- Oracle Organizations Selection -->
                    <div class="mt-4">
                        <x-input-label for="new_selected_organizations" :value="__('Oracle Organizations')" class="text-gray-700 dark:text-gray-300" />
                        <select id="new_selected_organizations" name="new_selected_organizations"
                            wire:model="new_selected_organizations" multiple
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @foreach($availableOrganizations as $org)
                                <option value="{{ $org['code'] }}">{{ $org['display'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-sm text-green-600 dark:text-green-400">
                            <strong>Optional:</strong> Select Oracle organizations this user can access. Hold Ctrl/Cmd to select multiple.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_selected_organizations')" />
                    </div>

                    <!-- Assigned Salespeople for CMD Roles -->
                    @if(in_array($new_role, ['cmd-khi', 'cmd-lhr']))
                    <div class="mt-4" x-data="{
                        search: '',
                        salespeople: {{ json_encode($salespeople->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values()) }},
                        get filteredSalespeople() {
                            if (!this.search) return this.salespeople;
                            return this.salespeople.filter(s =>
                                s.name.toLowerCase().includes(this.search.toLowerCase())
                            );
                        }
                    }">
                        <x-input-label for="new_assigned_salespeople" :value="__('Assigned Salespeople')" class="text-gray-700 dark:text-gray-300" />

                        <!-- Search Input -->
                        <input type="text"
                            x-model="search"
                            placeholder="Search salespeople..."
                            class="mt-1 mb-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">

                        <!-- Multi-select List -->
                        <select id="new_assigned_salespeople" name="new_assigned_salespeople"
                            wire:model="new_assigned_salespeople" multiple size="8"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <template x-for="salesperson in filteredSalespeople" :key="salesperson.id">
                                <option :value="salesperson.id" x-text="salesperson.name"></option>
                            </template>
                        </select>

                        <p class="mt-1 text-sm text-purple-600 dark:text-purple-400">
                            <strong>Optional:</strong> Leave empty to show receipts from ALL salespeople. Select specific salespeople to filter receipts. Hold Ctrl/Cmd to select multiple.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_assigned_salespeople')" />
                    </div>
                    @endif

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
                            <x-input-label for="role" :value="__('Role')" class="text-gray-700 dark:text-gray-300" />
                            <x-select id="role" name="role" wire:model="role"
                                :options="collect($roles)->map(fn($label, $value) => [
                                    'value' => $value,
                                    'label' => $label,
                                ])->values()->toArray()"
                                placeholder="Select Role"
                                class="mt-1"
                                required>
                            </x-select>
                            <x-input-error class="mt-2" :messages="$errors->get('role')" />
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
                            <x-input-label for="supply_chain_user_id" :value="__('Supply Chain User')" class="text-gray-700 dark:text-gray-300" />
                            <x-select id="supply_chain_user_id" name="supply_chain_user_id"
                                wire:model="supply_chain_user_id"
                                :options="$supplyChainUsers->map(fn($user) => [
                                    'value' => $user->id,
                                    'label' => $user->name,
                                ])->toArray()"
                                placeholder="Select Supply Chain User"
                                class="mt-1"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('supply_chain_user_id')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="account_user_id" :value="__('Account User')" class="text-gray-700 dark:text-gray-300" />
                            <x-select id="account_user_id" name="account_user_id"
                                wire:model="account_user_id"
                                :options="$accountUsers->map(fn($user) => [
                                    'value' => $user->id,
                                    'label' => $user->name,
                                ])->toArray()"
                                placeholder="Select Account User"
                                class="mt-1"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('account_user_id')" />
                        </div>

                        <!-- Oracle User Fields -->
                        <div class="mt-4">
                            <x-input-label for="oracle_user_id" :value="__('Oracle User ID')" class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="oracle_user_id" name="oracle_user_id" type="text"
                                class="mt-1"
                                wire:model="oracle_user_id" />
                            <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                <strong>Optional:</strong> Oracle User ID from QG_SHIPPING_USERS table.
                            </p>
                            <x-input-error class="mt-2" :messages="$errors->get('oracle_user_id')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="oracle_user_name" :value="__('Oracle User Name')" class="text-gray-700 dark:text-gray-300" />
                            <x-text-input id="oracle_user_name" name="oracle_user_name" type="text"
                                class="mt-1"
                                wire:model="oracle_user_name" />
                            <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                <strong>Optional:</strong> Oracle User Name from QG_SHIPPING_USERS table.
                            </p>
                            <x-input-error class="mt-2" :messages="$errors->get('oracle_user_name')" />
                        </div>

                        <!-- Oracle Organizations Selection -->
                        <div class="mt-4">
                            <x-input-label for="selected_organizations" :value="__('Oracle Organizations')" class="text-gray-700 dark:text-gray-300" />
                            <select id="selected_organizations" name="selected_organizations"
                                wire:model="selected_organizations" multiple
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @foreach($availableOrganizations as $org)
                                    <option value="{{ $org['code'] }}">{{ $org['display'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-green-600 dark:text-green-400">
                                <strong>Optional:</strong> Select Oracle organizations this user can access. Hold Ctrl/Cmd to select multiple.
                            </p>
                            <x-input-error class="mt-2" :messages="$errors->get('selected_organizations')" />
                        </div>

                        <!-- Assigned Salespeople for CMD Roles -->
                        @if(in_array($role, ['cmd-khi', 'cmd-lhr']))
                        <div class="mt-4" x-data="{
                            search: '',
                            salespeople: {{ json_encode($salespeople->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values()) }},
                            get filteredSalespeople() {
                                if (!this.search) return this.salespeople;
                                return this.salespeople.filter(s =>
                                    s.name.toLowerCase().includes(this.search.toLowerCase())
                                );
                            }
                        }">
                            <x-input-label for="assigned_salespeople" :value="__('Assigned Salespeople')" class="text-gray-700 dark:text-gray-300" />

                            <!-- Search Input -->
                            <input type="text"
                                x-model="search"
                                placeholder="Search salespeople..."
                                class="mt-1 mb-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">

                            <!-- Multi-select List -->
                            <select id="assigned_salespeople" name="assigned_salespeople"
                                wire:model="assigned_salespeople" multiple size="8"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <template x-for="salesperson in filteredSalespeople" :key="salesperson.id">
                                    <option :value="salesperson.id" x-text="salesperson.name"></option>
                                </template>
                            </select>

                            <p class="mt-1 text-sm text-purple-600 dark:text-purple-400">
                                <strong>Optional:</strong> Leave empty to show receipts from ALL salespeople. Select specific salespeople to filter receipts. Hold Ctrl/Cmd to select multiple.
                            </p>
                            <x-input-error class="mt-2" :messages="$errors->get('assigned_salespeople')" />
                        </div>
                        @endif

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
