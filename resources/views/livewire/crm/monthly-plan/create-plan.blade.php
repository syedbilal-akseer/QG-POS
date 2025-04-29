<div class=" mx-auto mt-2">
    <!-- Go Back Button -->
    <div class="mb-6">
        <x-secondary-button onclick="window.history.back();"
            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-neutral-700 hover:bg-gray-300 dark:hover:bg-neutral-600 transition duration-150 ease-in-out rounded-lg">
            {{ __('Go Back') }}
        </x-secondary-button>
    </div>

    <!-- Main Container -->
    <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-md">
        <form wire:submit.prevent="save">
            @csrf
            <div class="space-y-8">
                <div class="rounded-lg  bg-white dark:bg-neutral-800">
                    <div class="p-6">
                        <div class="flex justify-between items-center border-b pb-4 mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ $tourPlanId ? 'Edit Tour Plan' : 'Add New Tour Plan' }}
                            </h2>
                        </div>

                        <div>

                            <!-- Month Picker -->
                            <x-month-picker id="formData.month" name="formData.month" wire:model="formData.month"
                                label="Select Month" :minYear="2024" class="mb-6" />

                            <!-- Day Tour Plans -->
                            <div x-data="{ openIndex: 0 }">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Tour Plans') }}
                                </h3>
                                @foreach ($formData['day_plans'] as $index => $dayPlan)
                                    <div class="border rounded-lg p-4 my-6 bg-gray-50 dark:bg-neutral-900">
                                        <div class="flex justify-between items-center cursor-pointer"
                                            @click="openIndex = openIndex === {{ $index }} ? null : {{ $index }}">
                                            <h4 class="text-md font-medium text-gray-800 dark:text-gray-200">
                                                Day {{ (int) $index + 1 }}
                                            </h4>
                                            <span>
                                                <svg x-show="openIndex !== {{ $index }}"
                                                    class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                                <svg x-show="openIndex === {{ $index }}"
                                                    class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                </svg>
                                            </span>
                                        </div>
                                        <!-- Day Plan Content -->
                                        <div x-show="openIndex === {{ $index }}" x-collapse x-cloak
                                            class="mt-4 space-y-4">
                                            <div>
                                                <x-date-picker id="day_plans[{{ $index }}].date"
                                                    name="formData.day_plans.{{ $index }}.date"
                                                    wire:model="formData.day_plans.{{ $index }}.date"
                                                    label="Date"
                                                    options="{
                                                        format: 'DD/MM/YYYY',
                                                        autoApply: true,
                                                        autoClose: true,
                                                        lang: 'en',
                                                        lockDays: {!! json_encode(explode(', ', auth()->user()->off_days)) !!}
                                                    }" />
                                            </div>

                                            <!-- From Location -->
                                            <div>
                                                <x-input-label
                                                    for="formData.day_plans.{{ $index }}.from_location"
                                                    :value="__('From Location')" class="text-gray-700 dark:text-gray-300" />
                                                <x-text-input id="formData.day_plans.{{ $index }}.from_location"
                                                    name="formData.day_plans.{{ $index }}.from_location"
                                                    type="text" class="w-full rounded-md"
                                                    wire:model="formData.day_plans.{{ $index }}.from_location" />
                                                <x-input-error class="mt-2" :messages="$errors->get(
                                                    'formData.day_plans.' . $index . '.from_location',
                                                )" />
                                            </div>

                                            <!-- To Location -->
                                            <div>
                                                <x-input-label for="formData.day_plans.{{ $index }}.to_location"
                                                    :value="__('To Location')" class="text-gray-700 dark:text-gray-300" />
                                                <x-text-input id="formData.day_plans.{{ $index }}.to_location"
                                                    name="formData.day_plans.{{ $index }}.to_location"
                                                    type="text" class="w-full rounded-md"
                                                    wire:model="formData.day_plans.{{ $index }}.to_location" />
                                                <x-input-error class="mt-2" :messages="$errors->get(
                                                    'formData.day_plans.' . $index . '.to_location',
                                                )" />
                                            </div>

                                            <!-- Night Stay -->
                                            <div>
                                                <x-toggle
                                                    wire:model="formData.day_plans.{{ $index }}.is_night_stay"
                                                    label="Night Stay" />
                                                <x-input-error class="mt-2" :messages="$errors->get(
                                                    'formData.day_plans.' . $index . '.is_night_stay',
                                                )" />
                                            </div>

                                            <!-- Day Plan Tasks -->
                                            <div>
                                                <x-input-label for="key_tasks" :value="__('Key Tasks')"
                                                    class="text-gray-700 dark:text-gray-300" />
                                                @foreach ($dayPlan['key_tasks'] as $taskIndex => $task)
                                                    <div class="flex items-center mt-2">
                                                        <x-text-input
                                                            id="day_plans[{{ $index }}].key_tasks.{{ $taskIndex }}"
                                                            name="key_tasks[]" type="text" class="w-full rounded-md"
                                                            wire:model="formData.day_plans.{{ $index }}.key_tasks.{{ $taskIndex }}" />
                                                        <button type="button"
                                                            wire:click="removeTask({{ $index }},{{ $taskIndex }})"
                                                            class="ml-2 text-red-500 hover:text-red-700">
                                                            &times;
                                                        </button>
                                                    </div>
                                                @endforeach
                                                <button type="button" wire:click="addTask({{ $index }})"
                                                    class="mt-2 text-primary-500 hover:text-primary-700">
                                                    + Add Key Task
                                                </button>
                                                <x-input-error class="mt-2" :messages="$errors->get('formData.day_plans.' . $index . '.key_tasks')" />
                                            </div>

                                            @if ($index > 0)
                                                <button type="button" wire:click="removeDayPlan({{ $index }})"
                                                    class="mt-4 text-red-500 hover:text-red-700">- Remove Plan</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="addDayPlan"
                                    class="mt-6 w-full text-primary-500 hover:text-primary-700">+ Add Plan</button>
                            </div>

                        </div>
                    </div>

                    <div class="flex justify-end items-center gap-4 py-4 px-6 rounded-b-lg">
                        <x-secondary-button x-on:click="$dispatch('close');"
                            class="text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-neutral-800">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                        <x-primary-button
                            class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                            {{ __('Save') }}
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
