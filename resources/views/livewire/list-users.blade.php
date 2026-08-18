@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--multiple {
            min-height: 2.375rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.125rem 0.25rem;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #6366f1;
            box-shadow: 0 0 0 1px #6366f1;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            border-radius: 0.25rem;
        }
        .dark .select2-container--default .select2-selection--multiple {
            background-color: #111827;
            border-color: #374151;
        }
        .dark .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #4b5563;
            border-color: #6b7280;
            color: #e5e7eb;
        }
        .dark .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #e5e7eb;
        }
        .dark .select2-dropdown {
            background-color: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }
        .dark .select2-search__field {
            background-color: #111827;
            color: #e5e7eb;
        }
        .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #4f46e5;
            color: #fff;
        }
        .dark .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #374151;
        }
    </style>
@endpush

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
                        <x-input-label :value="__('Additional Roles (optional, read-only access)')" class="text-gray-700 dark:text-gray-300" />
                        <div class="mt-1 space-y-2 p-3 border border-gray-300 dark:border-neutral-600 rounded bg-gray-50 dark:bg-neutral-900/40">
                            @foreach($availableAdditionalRoles as $value => $label)
                                <label class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" value="{{ $value }}" wire:model.live="new_additional_roles" class="rounded">
                                    <span class="ml-2"><span class="font-mono text-xs">{{ $value }}</span> — {{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Grant the user read-only access to additional locations. Does not affect the primary role above.</p>
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
                        <div wire:ignore x-data x-init="
                            let $sel = $($refs.new_selected_organizations);
                            $sel.select2({ width: '100%', placeholder: 'Select organizations...', allowClear: true });
                            $sel.on('change', () => @this.set('new_selected_organizations', $sel.val() || []));
                            let sync = () => $sel.val(@this.get('new_selected_organizations') || []).trigger('change.select2');
                            sync();
                            window.addEventListener('open-modal', (e) => { if (e.detail == 'new_user') sync(); });
                        " class="mt-1">
                            <select x-ref="new_selected_organizations" id="new_selected_organizations" name="new_selected_organizations" multiple>
                                @foreach($availableOrganizations as $org)
                                    <option value="{{ $org['code'] }}">{{ $org['display'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-1 text-sm text-green-600 dark:text-green-400">
                            <strong>Optional:</strong> Select Oracle organizations this user can access.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('new_selected_organizations')" />
                    </div>

                    <!-- Assigned Salespeople for CMD Roles -->
                    @if(in_array($new_role, ['cmd-khi', 'cmd-lhr']))
                    <div class="mt-4">
                        <x-input-label for="new_assigned_salespeople" :value="__('Assigned Salespeople')" class="text-gray-700 dark:text-gray-300" />

                        <!-- Options rendered server-side so Livewire can mark previously-assigned
                             salespeople as selected on load; Select2 provides the search box. -->
                        <div wire:ignore x-data x-init="
                            let $sel = $($refs.new_assigned_salespeople);
                            $sel.select2({ width: '100%', placeholder: 'Search salespeople...', allowClear: true });
                            $sel.on('change', () => @this.set('new_assigned_salespeople', $sel.val() || []));
                            let sync = () => $sel.val(@this.get('new_assigned_salespeople') || []).trigger('change.select2');
                            sync();
                            window.addEventListener('open-modal', (e) => { if (e.detail == 'new_user') sync(); });
                        " class="mt-1">
                            <select x-ref="new_assigned_salespeople" id="new_assigned_salespeople" name="new_assigned_salespeople" multiple>
                                @foreach($salespeople as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <p class="mt-1 text-sm text-purple-600 dark:text-purple-400">
                            <strong>Optional:</strong> Leave empty to show receipts from ALL salespeople. Select specific salespeople to filter receipts.
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
                            <x-input-label :value="__('Additional Roles (optional, read-only access)')" class="text-gray-700 dark:text-gray-300" />
                            <div class="mt-1 space-y-2 p-3 border border-gray-300 dark:border-neutral-600 rounded bg-gray-50 dark:bg-neutral-900/40">
                                @foreach($availableAdditionalRoles as $value => $label)
                                    <label class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" value="{{ $value }}" wire:model.live="additional_roles" class="rounded">
                                        <span class="ml-2"><span class="font-mono text-xs">{{ $value }}</span> — {{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Grant additional read-only access on top of the primary role.</p>
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

                        <!-- Salesperson / Segment — synced from Oracle qg_all_users, read-only -->
                        <div class="mt-4">
                            <x-input-label :value="__('Salesperson Name (Oracle)')" class="text-gray-700 dark:text-gray-300" />
                            <x-text-input name="salesperson_name_display" type="text" class="mt-1 bg-gray-100 dark:bg-neutral-800"
                                :value="$user?->salesperson_name ?? '—'" disabled readonly />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Read-only. Synced from Oracle qg_all_users by <code>sync:oracle-users</code>.
                            </p>
                        </div>

                        <div class="mt-4">
                            <x-input-label :value="__('Segment (Oracle)')" class="text-gray-700 dark:text-gray-300" />
                            <x-text-input name="segment_display" type="text" class="mt-1 bg-gray-100 dark:bg-neutral-800"
                                :value="$user?->segment ?? '—'" disabled readonly />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Read-only. Synced from Oracle qg_all_users by <code>sync:oracle-users</code>.
                            </p>
                        </div>

                        <!-- Oracle Organizations Selection -->
                        <div class="mt-4">
                            <x-input-label for="selected_organizations" :value="__('Oracle Organizations')" class="text-gray-700 dark:text-gray-300" />
                            <div wire:ignore x-data x-init="
                                let $sel = $($refs.selected_organizations);
                                $sel.select2({ width: '100%', placeholder: 'Select organizations...', allowClear: true });
                                $sel.on('change', () => @this.set('selected_organizations', $sel.val() || []));
                                let sync = () => $sel.val(@this.get('selected_organizations') || []).trigger('change.select2');
                                sync();
                                window.addEventListener('open-modal', (e) => { if (e.detail == 'edit_user_modal') sync(); });
                            " class="mt-1">
                                <select x-ref="selected_organizations" id="selected_organizations" name="selected_organizations" multiple>
                                    @foreach($availableOrganizations as $org)
                                        <option value="{{ $org['code'] }}">{{ $org['display'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="mt-1 text-sm text-green-600 dark:text-green-400">
                                <strong>Optional:</strong> Select Oracle organizations this user can access.
                            </p>
                            <x-input-error class="mt-2" :messages="$errors->get('selected_organizations')" />
                        </div>

                        <!-- Assigned Salespeople for CMD Roles -->
                        @if(in_array($role, ['cmd-khi', 'cmd-lhr']))
                        <div class="mt-4">
                            <x-input-label for="assigned_salespeople" :value="__('Assigned Salespeople')" class="text-gray-700 dark:text-gray-300" />

                            <!-- Options rendered server-side so Livewire can mark previously-assigned
                                 salespeople as selected on load; Select2 provides the search box. -->
                            <div wire:ignore x-data x-init="
                                let $sel = $($refs.assigned_salespeople);
                                $sel.select2({ width: '100%', placeholder: 'Search salespeople...', allowClear: true });
                                $sel.on('change', () => @this.set('assigned_salespeople', $sel.val() || []));
                                let sync = () => $sel.val(@this.get('assigned_salespeople') || []).trigger('change.select2');
                                sync();
                                window.addEventListener('open-modal', (e) => { if (e.detail == 'edit_user_modal') sync(); });
                            " class="mt-1">
                                <select x-ref="assigned_salespeople" id="assigned_salespeople" name="assigned_salespeople" multiple>
                                    @foreach($salespeople as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <p class="mt-1 text-sm text-purple-600 dark:text-purple-400">
                                <strong>Optional:</strong> Leave empty to show receipts from ALL salespeople. Select specific salespeople to filter receipts.
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

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush
