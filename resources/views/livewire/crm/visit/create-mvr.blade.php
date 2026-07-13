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
        <form wire:submit.prevent="submitVisit">
            <div class="space-y-8">
                <div class="rounded-lg  bg-white dark:bg-neutral-800">
                    <div class="p-6">
                        <div x-data="{ openIndex: 0 }">
                            @foreach ($visitFormData['visits'] as $visitIndex => $visit)
                                <!-- Collapsible Visit Section -->
                                <div class="border rounded-lg p-4 my-6 bg-gray-50 dark:bg-neutral-900">
                                    <div class="flex justify-between items-center cursor-pointer"
                                        @click="openIndex = openIndex === {{ $visitIndex }} ? null : {{ $visitIndex }}">
                                        <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                                            Visit {{ (int) $visitIndex + 1 }}
                                        </h4>
                                        <span>
                                            <svg x-show="openIndex !== {{ $visitIndex }}" class="w-6 h-6"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                            <svg x-show="openIndex === {{ $visitIndex }}" class="w-6 h-6"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7"></path>
                                            </svg>
                                        </span>
                                    </div>

                                    <!-- Visit Content -->
                                    <div x-show="openIndex === {{ $visitIndex }}" x-collapse x-cloak>
                                        <div class="mt-4">
                                            <x-input-label for="visitFormData.visits.{{ $visitIndex }}.customer_name"
                                                :value="__('Customer Name')" />
                                            <x-text-input id="visitFormData.visits.{{ $visitIndex }}.customer_name"
                                                name="visitFormData.visits.{{ $visitIndex }}.customer_name"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.customer_name" />
                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.customer_name',
                                            )" />
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
                                            <x-input-label
                                                for="visitFormData.visits.{{ $visitIndex }}.contact_person"
                                                :value="__('Contact Person')" />
                                            <x-text-input id="visitFormData.visits.{{ $visitIndex }}.contact_person"
                                                name="visitFormData.visits.{{ $visitIndex }}.contact_person"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.contact_person" />
                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.contact_person',
                                            )" />
                                        </div>
                                        <div class="mt-4">
                                            <x-input-label for="visitFormData.visits.{{ $visitIndex }}.contact_no"
                                                :value="__('Contact No')" />
                                            <x-text-input id="visitFormData.visits.{{ $visitIndex }}.contact_no"
                                                name="visitFormData.visits.{{ $visitIndex }}.contact_no"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.contact_no" />
                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.contact_no',
                                            )" />
                                        </div>
                                        <div class="mt-4">
                                            <x-input-label for="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                                :value="__('Outlet Type')" />

                                            <x-select id="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                                name="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.outlet_type"
                                                class="bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                                :options="$outletTypes" placeholder="Select Outlet Type" />


                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.outlet_type',
                                            )" />
                                        </div>
                                        <div class="mt-4">
                                            <x-input-label for="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                                :value="__('Shop Category')" />

                                            <x-select id="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                                name="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.shop_category"
                                                class="bg-gray-100 dark:bg-neutral-700 border border-gray-300 dark:border-neutral-600 text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                                :options="$shopCategories" placeholder="Select Shop Category" />

                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.shop_category',
                                            )" />
                                        </div>
                                        <div class="mt-4">
                                            <x-input-label for="visitFormData.visits.{{ $visitIndex }}.visit_details"
                                                :value="__('Visit Details')" />
                                            <x-textarea id="visitFormData.visits.{{ $visitIndex }}.visit_details"
                                                name="visitFormData.visits.{{ $visitIndex }}.visit_details"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.visit_details" />
                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.visit_details',
                                            )" />
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
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'visitFormData.visits.' .
                                                            $visitIndex .
                                                            '.competitors.' .
                                                            $competitorIndex .
                                                            '.name',
                                                    )" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product"
                                                        :value="__('Product')" />
                                                    <x-text-input
                                                        id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product"
                                                        name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product"
                                                        wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.product" />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'visitFormData.visits.' .
                                                            $visitIndex .
                                                            '.competitors.' .
                                                            $competitorIndex .
                                                            '.product',
                                                    )" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size"
                                                        :value="__('Size')" />
                                                    <x-text-input
                                                        id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size"
                                                        name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size"
                                                        wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.size" />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'visitFormData.visits.' .
                                                            $visitIndex .
                                                            '.competitors.' .
                                                            $competitorIndex .
                                                            '.size',
                                                    )" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details"
                                                        :value="__('Details')" />
                                                    <x-textarea
                                                        id="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details"
                                                        name="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details"
                                                        wire:model="visitFormData.visits.{{ $visitIndex }}.competitors.{{ $competitorIndex }}.details" />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'visitFormData.visits.' .
                                                            $visitIndex .
                                                            '.competitors.' .
                                                            $competitorIndex .
                                                            '.details',
                                                    )" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label :value="__('Competitor Attachments')" />
                                                    <x-file-input name="competitorAttachments.{{ $competitorIndex }}"
                                                        multiple
                                                        wire:model="competitorAttachments.{{ $competitorIndex }}"
                                                        accept=".jpg,.jpeg,.png,.gif,.bmp,.pdf,.doc,.docx" />

                                                    <x-input-error :messages="$errors->get(
                                                        'competitorAttachments.' .
                                                            $visitIndex .
                                                            '.' .
                                                            $competitorIndex .
                                                            '.*',
                                                    )" />
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

                                        <div class="mt-4">
                                            <x-input-label
                                                for="visitFormData.visits.{{ $visitIndex }}.visit_findings_of_the_day"
                                                :value="__('Findings Of The Day')" />
                                            <x-textarea
                                                id="visitFormData.visits.{{ $visitIndex }}.visit_findings_of_the_day"
                                                name="visitFormData.visits.{{ $visitIndex }}.visit_findings_of_the_day"
                                                wire:model="visitFormData.visits.{{ $visitIndex }}.visit_findings_of_the_day" />
                                            <x-input-error class="mt-2" :messages="$errors->get(
                                                'visitFormData.visits.' . $visitIndex . '.visit_findings_of_the_day',
                                            )" />
                                        </div>
                                        <div class="mt-4">
                                            <x-input-label :value="__('Visit Attachments')" />
                                            <x-file-input name="visitAttachments.{{ $visitIndex }}" multiple
                                                wire:model="visitAttachments.{{ $visitIndex }}"
                                                accept=".jpg,.jpeg,.png,.gif,.bmp,.pdf,.doc,.docx" />

                                            <x-input-error :messages="$errors->get('visitAttachments.' . $visitIndex)" />
                                        </div>
                                        @if ($visitIndex >= 1)
                                            <button type="button" wire:click="removeVisit({{ $visitIndex }})"
                                                class="mt-2 me-3 text-red-500 hover:text-red-700">
                                                {{ __('- Remove Visit') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="addVisit"
                            class="mt-6 w-full text-primary-500 hover:text-primary-700">+
                            {{ __('Add Visit') }}</button>
                    </div>

                    <div class="flex justify-end items-center gap-4 py-4 px-6 rounded-b-lg">
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
</div>
