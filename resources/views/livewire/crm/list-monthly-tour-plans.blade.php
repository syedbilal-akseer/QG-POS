<div>

    <!-- Add New Plan Button -->
    <div class="flex justify-end mb-4">
        <x-primary-button wire:click="addNewPlan">
            {{ __('Add New Plan') }}
        </x-primary-button>
    </div>

    <!-- Tour Plans Table -->
    {{ $this->table }}

    <!-- Custom Modal for Creating/Editing Tour Plan -->
    <x-modal name="tour_plan_modal" focusable>
        <div class="p-6 bg-white dark:bg-neutral-800">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $tourPlanId ? 'Edit Tour Plan' : 'Add New Tour Plan' }}
                </h2>
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
                <form wire:submit.prevent="save">
                    @csrf

                    <!-- Month Picker -->
                    <x-month-picker id="formData.month" name="formData.month" wire:model="formData.month"
                        label="Select Month" :minYear="2024" />

                    <!-- Day Tour Plans -->
                    <div class="mt-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Tour Plans') }}</h3>

                        @foreach ($formData['day_plans'] as $index => $dayPlan)
                            <!-- Collapsible Day Plan Section -->
                            <div x-data="{ isOpen: {{ $index === 0 ? 'true' : 'false' }} }" class="border p-4 my-4">
                                <div class="flex justify-between items-center cursor-pointer" @click="isOpen = !isOpen">
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                                        Plan {{ (int) $index + 1 }}
                                    </h4>
                                    <span>
                                        <svg x-show="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                        <svg x-show="isOpen" class="w-6 h-6" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 15l7-7 7 7"></path>
                                        </svg>
                                    </span>
                                </div>

                                <!-- Day Plan Content -->
                                <div x-show="isOpen" x-collapse x-cloak>
                                    <!-- Day Plan Date -->
                                    <div class="mt-4">
                                        <x-date-picker id="day_plans[{{ $index }}].date"
                                            name="formData.day_plans.{{ $index }}.date"
                                            wire:model="formData.day_plans.{{ $index }}.date" label="Date"
                                            options="{
                                                format: 'DD/MM/YYYY',
                                                autoApply: true,
                                                autoClose: true,
                                                lang: 'en',
                                                lockDays: ['Sunday']
                                            }" />
                                    </div>

                                    <!-- From Location -->
                                    <div class="mt-4">
                                        <x-input-label for="formData.day_plans.{{ $index }}.from_location"
                                            :value="__('From Location')" class="text-gray-700 dark:text-gray-300" />
                                        <x-select id="formData.day_plans.{{ $index }}.from_location"
                                            name="formData.day_plans.{{ $index }}.from_location"
                                            wire:model="formData.day_plans.{{ $index }}.from_location"
                                            class="bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                                            <option value="">{{ __('Select From Location') }}</option>
                                            @foreach ($warehouseLocations as $location)
                                                <option value="{{ $location->organization_id }}">
                                                    {{ $location->organization_code }}
                                                </option>
                                            @endforeach
                                        </x-select>
                                        <x-input-error class="mt-2" :messages="$errors->get('formData.day_plans.' . $index . '.from_location')" />
                                    </div>

                                    <!-- To Location -->
                                    <div class="mt-4">
                                        <x-input-label for="formData.day_plans.{{ $index }}.to_location"
                                            :value="__('To Location')" class="text-gray-700 dark:text-gray-300" />
                                        <x-select id="formData.day_plans.{{ $index }}.to_location"
                                            name="formData.day_plans.{{ $index }}.to_location"
                                            wire:model="formData.day_plans.{{ $index }}.to_location"
                                            class="bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                                            <option value="">{{ __('Select To Location') }}</option>
                                            @foreach ($warehouseLocations as $location)
                                                <option value="{{ $location->organization_id }}">
                                                    {{ $location->organization_code }}
                                                </option>
                                            @endforeach
                                        </x-select>
                                        <x-input-error class="mt-2" :messages="$errors->get('formData.day_plans.' . $index . '.to_location')" />
                                    </div>

                                    <!-- Night Stay -->
                                    <div class="mt-4">
                                        <x-toggle wire:model="formData.day_plans.{{ $index }}.is_night_stay"
                                            label="Night Stay" />
                                        <x-input-error class="mt-2" :messages="$errors->get(
                                            'formData.day_plans.{{ $index }}.is_night_stay',
                                        )" />
                                    </div>

                                    <!-- Day Plan Tasks -->
                                    <div class="mt-4">
                                        <x-input-label for="key_tasks" :value="__('Key Tasks')"
                                            class="text-gray-700 dark:text-gray-300" />
                                        @foreach ($dayPlan['key_tasks'] as $taskIndex => $task)
                                            <div class="flex items-center mt-2">
                                                <x-text-input
                                                    id="day_plans[{{ $index }}].key_tasks.{{ $index }}"
                                                    name="key_tasks[]" type="text" class="mt-1 block w-full"
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

                                    <!-- First Day Plan Cannot Be Removed -->
                                    @if ($index > 0)
                                        <button type="button" wire:click="removeDayPlan({{ $index }})"
                                            class="mt-2 text-red-500 hover:text-red-700">- Remove Plan</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <!-- Add Day Plan Button -->
                        <button type="button" wire:click="addDayPlan"
                            class="mt-4 text-primary-500 hover:text-primary-700">+ Add Plan</button>
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
            <x-primary-button wire:click="save"
                class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                {{ __('Save') }}
            </x-primary-button>
        </div>
    </x-modal>

    @if ($tourPlan)
        <x-modal name="day_tour_plan_modal" :show="true" focusable maxWidth="4xl">
            <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-lg">
                <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Tour Plan Details for ') . $tourPlan->month }}
                    </h2>

                    <span x-on:click="$dispatch('close')"
                        class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>

                <div class="mt-6 space-y-6">
                    <!-- Order Information -->
                    <div class="flex items-start">
                        <!-- Customer Information -->
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300"> </h3>
                            <p class="text-lg text-gray-900 dark:text-gray-100">
                            </p>
                        </div>

                        <!-- Status and Date Information -->
                        <div class="flex items-end ml-6">
                            <div class="text-left me-4 flex items-center">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 me-2">Status:</h3>
                                <p class="text-lg text-gray-900 dark:text-gray-100"><span
                                        class="inline-flex items-center px-2 py-1 text-xs font-semibold leading-5
                                {{ $tourPlan->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}
                                rounded-full">
                                        {{ ucfirst($tourPlan->status) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- Tour Plan Details Table -->
                    <div class="overflow-x-auto mt-4">
                        <table class="min-w-full border border-gray-200 dark:border-neutral-700 rounded-md">
                            <thead class="bg-gray-50 dark:bg-neutral-900">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Date
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Day
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        From
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        To
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Night Stay
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Key Tasks
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white dark:bg-neutral-800 divide-y divide-gray-200 dark:divide-neutral-700">
                                @foreach ($dayTourPlans as $dayPlan)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $dayPlan['date'] }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $dayPlan['day'] }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $dayPlan['from_location'] }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $dayPlan['to_location'] }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ $dayPlan['is_night_stay'] ? 'Yes' : 'No' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            {{ implode(', ', $dayPlan['key_tasks']) }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                            <x-primary-button type="button"
                                                wire:click="addNewVisit({{ $dayPlan['id'] }})"
                                                class="bg-blue-500 text-white hover:bg-blue-600 transition duration-150 ease-in-out">
                                                {{ __('Add Visit Report') }}
                                            </x-primary-button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                class="flex justify-end items-center gap-x-4 py-4 px-6 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
                <x-secondary-button x-on:click="$dispatch('close');"
                    class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800 transition duration-150 ease-in-out">
                    {{ __('Close') }}
                </x-secondary-button>
            </div>
        </x-modal>
    @endif

    <x-modal name="visit_add_modal" focusable>
        <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-lg">
            <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('Add Visits') }}
                </h2>

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
                <form wire:submit.prevent="submitVisit">

                    <div class="mt-4">
                        @foreach ($visitFormData['visits'] as $visitIndex => $visit)
                            <!-- Collapsible Visit Section -->
                            <div x-data="{ isOpen: {{ $visitIndex === 0 ? 'true' : 'false' }} }" class="border p-4 my-4">
                                <div class="flex justify-between items-center cursor-pointer"
                                    @click="isOpen = !isOpen">
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                                        Visit {{ (int) $visitIndex + 1 }}
                                    </h4>
                                    <span>
                                        <svg x-show="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                        <svg x-show="isOpen" class="w-6 h-6" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 15l7-7 7 7"></path>
                                        </svg>
                                    </span>
                                </div>

                                <!-- Visit Content -->
                                <div x-show="isOpen" x-collapse x-cloak>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.customer_name"
                                            :value="__('Customer Name')" />
                                        <x-text-input id="visitFormData.visits.{{ $visitIndex }}.customer_name"
                                            name="visitFormData.visits.{{ $visitIndex }}.customer_name"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.customer_name" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.customer_name')" />
                                    </div>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.area"
                                            :value="__('Area')" />
                                        <x-text-input id="visitFormData.visits.{{ $visitIndex }}.area"
                                            name="visitFormData.visits.{{ $visitIndex }}.area"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.area" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.area')" />
                                    </div>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.contact_person"
                                            :value="__('Contact Person')" />
                                        <x-text-input id="visitFormData.visits.{{ $visitIndex }}.contact_person"
                                            name="visitFormData.visits.{{ $visitIndex }}.contact_person"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.contact_person" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.contact_person')" />
                                    </div>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.contact_no"
                                            :value="__('Contact No')" />
                                        <x-text-input id="visitFormData.visits.{{ $visitIndex }}.contact_no"
                                            name="visitFormData.visits.{{ $visitIndex }}.contact_no"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.contact_no" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.contact_no')" />
                                    </div>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                            :value="__('Outlet Type')" />
                                        <x-text-input id="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                            name="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.outlet_type" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.outlet_type')" />
                                    </div>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                            :value="__('Shop Category')" />
                                        <x-text-input id="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                            name="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.shop_category" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.shop_category')" />
                                    </div>
                                    <div class="mt-4">
                                        <x-input-label for="visitFormData.visits.{{ $visitIndex }}.visit_details"
                                            :value="__('Visit Details')" />
                                        <x-textarea id="visitFormData.visits.{{ $visitIndex }}.visit_details"
                                            name="visitFormData.visits.{{ $visitIndex }}.visit_details"
                                            wire:model="visitFormData.visits.{{ $visitIndex }}.visit_details" />
                                        <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.visit_details')" />
                                    </div>
                                    <h4 class="font-semibold mt-4 mb-4">{{ __('Competitors') }}</h4>
                                    @foreach ($visit['competitors'] as $competitorIndex => $competitor)
                                        <div class="border p-4 rounded-lg mb-2">
                                            <div class="mt-4">
                                                <x-input-label
                                                    for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.name"
                                                    :value="__('Competitor Name')" />
                                                <x-text-input
                                                    id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.name"
                                                    name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.name"
                                                    wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.name" />
                                                <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.competitors.' . $competitorIndex . '.name')" />
                                            </div>
                                            <div class="mt-4">
                                                <x-input-label
                                                    for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product"
                                                    :value="__('Product')" />
                                                <x-text-input
                                                    id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product"
                                                    name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product"
                                                    wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product" />
                                                <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.competitors.' . $competitorIndex . '.product')" />
                                            </div>
                                            <div class="mt-4">
                                                <x-input-label
                                                    for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size"
                                                    :value="__('Size')" />
                                                <x-text-input
                                                    id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size"
                                                    name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size"
                                                    wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size" />
                                                <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.competitors.' . $competitorIndex . '.size')" />
                                            </div>
                                            <div class="mt-4">
                                                <x-input-label
                                                    for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details"
                                                    :value="__('Details')" />
                                                <x-textarea
                                                    id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details"
                                                    name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details"
                                                    wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details" />
                                                <x-input-error class="mt-2" :messages="$errors->get('visitFormData.visits.' . $visitIndex . '.competitors.' . $competitorIndex . '.details')" />
                                            </div>
                                            <button type="button"
                                                wire:click="removeCompetitor({{ $visitIndex }}, {{ $competitorIndex }})"
                                                class="mt-2 text-red-500 hover:text-red-700">
                                                {{ __('Remove Competitor') }}
                                            </button>
                                        </div>
                                    @endforeach

                                    <button type="button" wire:click="addCompetitor({{ $visitIndex }})"
                                        class="mt-2 text-primary-500 hover:text-primary-700">
                                        {{ __('+ Add Competitor') }}
                                    </button>

                                    <button type="button" wire:click="removeVisit({{ $visitIndex }})"
                                        class="mt-2 me-3 text-red-500 hover:text-red-700">
                                        {{ __('- Remove Visit') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <x-primary-button type="button" wire:click="addVisit">
                        {{ __('Add Visit') }}
                    </x-primary-button>

                </form>
            </div>

        </div>
        <div
            class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
            <x-secondary-button x-on:click="$dispatch('close');"
                class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                {{ __('Cancel') }}
            </x-secondary-button>
            <x-primary-button wire:click="submitVisit"
                class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                {{ __('Save Visits') }}
            </x-primary-button>
        </div>
    </x-modal>

</div>
