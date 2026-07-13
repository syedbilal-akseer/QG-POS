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
        <form wire:submit.prevent="submitExpense">
            <div class="space-y-8">
                <div class="rounded-lg  bg-white dark:bg-neutral-800">
                    <div class="p-6" x-data="{ openIndex: 0 }">
                        @foreach ($expenseFormData['expenses'] as $expenseIndex => $expense)
                            <!-- Collapsible Expense Section -->
                            <div class="border rounded-lg p-4 my-6 bg-gray-50 dark:bg-neutral-900">
                                <div class="flex justify-between items-center cursor-pointer"
                                    @click="openIndex = openIndex === {{ $expenseIndex }} ? null : {{ $expenseIndex }}">
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                                        Expense {{ (int) $expenseIndex + 1 }}
                                    </h4>
                                    <span>
                                        <svg x-show="openIndex !== {{ $expenseIndex }}" class="w-6 h-6" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                        <svg x-show="openIndex === {{ $expenseIndex }}" class="w-6 h-6" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 15l7-7 7 7"></path>
                                        </svg>
                                    </span>
                                </div>

                                <!-- Expense Content -->
                                <div x-show="openIndex === {{ $expenseIndex }}" x-collapse x-cloak>
                                    <div class="mt-4">
                                        <x-input-label for="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            :value="__('Expense Type')" />
                                        <x-select id="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            name="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            required :options="$expenseTypes" />
                                        <x-input-error class="mt-2" :messages="$errors->get(
                                            'expenseFormData.expenses.' . $expenseIndex . '.expense_type',
                                        )" />
                                    </div>

                                    <!-- Loop through Expense Details -->
                                    <div class="mt-4">
                                        <h4 class="font-semibold mb-4">{{ __('Expense Details') }}</h4>
                                        @foreach ($expenseFormData['expenses'][$expenseIndex]['expense_details'] as $detailIndex => $detail)
                                            <div class="border p-4 rounded-lg mb-2">
                                                <div class="mt-4">
                                                    <x-date-picker
                                                        id="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        name="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        label="Date"
                                                        options="{
                                                            format: 'DD/MM/YYYY',
                                                            autoApply: true,
                                                            autoClose: true,
                                                            lang: 'en',
                                                            minDate: false,
                                                            lockDays: {!! json_encode(explode(', ', auth()->user()->off_days)) !!}
                                                        }" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.description"
                                                        :value="__('Description')" />
                                                    <x-text-input
                                                        id="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.description"
                                                        name="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.description"
                                                        wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.description"
                                                        required />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'expenseFormData.expenses.' .
                                                            $expenseIndex .
                                                            '.expense_details.' .
                                                            $detailIndex .
                                                            '.description',
                                                    )" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.amount"
                                                        :value="__('Amount')" />
                                                    <x-text-input type="number"
                                                        id="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.amount"
                                                        name="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.amount"
                                                        wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.amount"
                                                        required />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'expenseFormData.expenses.' .
                                                            $expenseIndex .
                                                            '.expense_details.' .
                                                            $detailIndex .
                                                            '.amount',
                                                    )" />
                                                </div>
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.details"
                                                        :value="__('Additional Details')" />
                                                    <x-textarea
                                                        id="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.details"
                                                        name="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.details"
                                                        wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.details" />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'expenseFormData.expenses.' .
                                                            $expenseIndex .
                                                            '.expense_details.' .
                                                            $detailIndex .
                                                            '.details',
                                                    )" />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-4">
                                        <x-input-label :value="__('Attachments')" />
                                        <x-file-input name="expenseAttachments.{{ $expenseIndex }}" multiple
                                            wire:model="expenseAttachments.{{ $expenseIndex }}"
                                            accept=".jpg,.jpeg,.png,.gif,.bmp,.pdf,.doc,.docx" />

                                        <x-input-error :messages="$errors->get('expenseAttachments.' . $expenseIndex)" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" wire:click="addExpense"
                            class="mt-6 w-full text-primary-500 hover:text-primary-700">
                            {{ __('+ Add Expense') }}
                        </button>
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
