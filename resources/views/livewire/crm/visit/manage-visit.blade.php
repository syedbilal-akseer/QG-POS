<div>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2 table-crm">

    <!-- Header with Search and Refresh -->
    <div class="d-flex justify-content-between mb-3">
        <div class="d-flex gap-2">
            <button class="btn btn-secondary" wire:click="refreshVisitReports" @if($loading) disabled @endif>
                <i class="fa fa-refresh @if($loading) fa-spin @endif"></i> Refresh
            </button>
        </div>

        <div class="relative w-full max-w-xs">
            <!-- Search Input -->
            <input type="search" 
                   wire:model.live.debounce.500ms="search" 
                   placeholder="Search"
                   autocomplete="off"
                   class="block w-full bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded-lg focus:ring-gray-500 focus:border-gray-500 pr-10 p-2.5"/>
        
            <!-- Search Icon (Positioned on Right) -->
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none search-icon">
                <i class="fa fa-search text-gray-400"></i>
            </div>
        </div>
        
    </div>

    <!-- Loading State -->
    @if($loading)
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading visit reports...</p>
        </div>
    @endif

    <!-- Table List Visit Reports -->
    <div class="table-responsive" @if($loading) style="opacity: 0.5;" @endif>
        <table class="table table-dark table-hover align-middle">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Salesperson</th>
                    <th>Total Visits</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($visitReports as $report)
                    <tr>
                        <td>{{ $report['month'] }}</td>
                        <td>{{ $report['salesperson']['name'] ?? 'N/A' }}</td>
                        <td>{{ count($report['visits']) }}</td>
                        <td class="text-end">
                            <!-- Direct Button for Viewing Visit Details -->
                            <button wire:click="viewDetails({{ $report['id'] }})" 
                                    class="btn btn-sm btn-primary me-2 bg-primary-600 hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600 text-white">
                                <i class="fa fa-eye"></i> View Details
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">
                            @if($loading)
                                Loading visit reports...
                            @else
                                No visit reports found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center">
        <div>
            Showing {{ count($visitReports) }} results
        </div>
    </div>
</div>

    @if ($visits)
        <x-modal name="visit_details_modal" focusable>
            <div class="p-6 dark:bg-neutral-800 rounded-lg shadow-lg">
                <!-- Modal Header -->
                <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Visit Details') }}
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

                <!-- Modal Content -->
                <div class="mt-4">
                    @foreach ($visits as $visitIndex => $visit)
                        <!-- Collapsible Visit Section -->
                        <div x-data="{ isOpen: {{ $visitIndex === 0 ? 'true' : 'false' }} }" class="border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900 p-4 my-4 rounded-lg">
                            <!-- Section Header -->
                            <div class="flex justify-between items-center cursor-pointer" @click="isOpen = !isOpen">
                                <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Visit') }} {{ $visitIndex + 1 }}
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

                            <!-- Collapsible Content -->
                            <div x-show="isOpen" x-collapse x-cloak>
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <h5 class="font-semibold">{{ __('Customer Name') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->customer_name }}</p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Area') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->area }}</p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Contact Person') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->contact_person }}</p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Contact No') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->contact_no }}</p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Outlet Type') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->outlet_type }}</p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Shop Category') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->shop_category }}</p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Tour Plan Date') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">
                                            {{ optional($visit->dayTourPlan)->date ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold">{{ __('Day') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">
                                            {{ optional($visit->dayTourPlan)->day ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="col-span-2">
                                        <h5 class="font-semibold">{{ __('Visit Details') }}:</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $visit->visit_details }}</p>
                                    </div>
                                </div>

                                <!-- Status and Approval Section -->
                                <div class="mt-6">
                                    <h5 class="font-semibold">{{ __('Status') }}:</h5>
                                    <p class="text-gray-700 dark:text-gray-300">{{ ucwords($visit->status) }}</p>

                                    <div class="mt-4 flex space-x-4">
                                        {{-- @if ($visit['status'] === 'pending')
                                            <x-primary-button type="button"
                                                wire:click="approveLineManager({{ $visit->id }})" class="me-3">
                                                Approve by Line Manager
                                            </x-primary-button>
                                            <x-primary-button type="button"
                                                wire:click="approveHod({{ $visit->id }})" class="me-3">
                                                Approve by HOD
                                            </x-primary-button>
                                        @endif --}}
                                        <!-- <x-primary-button type="button"
                                            wire:click="openAddExpenseModal({{ $visit->id }})" class="me-3">
                                            Add Expense
                                        </x-primary-button> -->
                                    </div>
                                </div>

                                <!-- Competitors Section -->
                                @if (!empty($visit->competitors))
                                    <h4 class="font-semibold mt-6 mb-4">{{ __('Competitors') }}</h4>
                                    @foreach ($visit->competitors as $competitorIndex => $competitor)
                                        <div class="border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900 p-4 rounded-lg mb-2">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <h5 class="font-semibold">{{ __('Competitor Name') }}:</h5>
                                                    <p class="text-gray-700 dark:text-gray-300">
                                                        {{ $competitor['name'] }}</p>
                                                </div>
                                                <div>
                                                    <h5 class="font-semibold">{{ __('Product') }}:</h5>
                                                    <p class="text-gray-700 dark:text-gray-300">
                                                        {{ $competitor['product'] }}</p>
                                                </div>
                                                <div>
                                                    <h5 class="font-semibold">{{ __('Size') }}:</h5>
                                                    <p class="text-gray-700 dark:text-gray-300">
                                                        {{ $competitor['size'] }}</p>
                                                </div>
                                                <div class="col-span-2">
                                                    <h5 class="font-semibold">{{ __('Details') }}:</h5>
                                                    <p class="text-gray-700 dark:text-gray-300">
                                                        {{ $competitor['details'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-modal>
    @endif

    <x-modal name="expense_add_modal" focusable>
        <div class="p-6 bg-white dark:bg-neutral-800 rounded-lg shadow-lg">
            <div class="flex justify-between items-center border-b pb-4 border-gray-200 dark:border-neutral-700">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('Add Expenses') }}
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
                        @foreach ($expenseFormData['expenses'] as $expenseIndex => $expense)
                            <!-- Collapsible expense Section -->
                            <div x-data="{ isOpen: {{ $expenseIndex === 0 ? 'true' : 'false' }} }" class="border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900 p-4 my-4 rounded-lg">
                                <div class="flex justify-between items-center cursor-pointer"
                                    @click="isOpen = !isOpen">
                                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300">
                                        Expense {{ (int) $expenseIndex + 1 }}
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

                                <!-- Expense Content -->
                                <div x-show="isOpen" x-collapse x-cloak>
                                    <div class="mt-4">
                                        <x-input-label for="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            :value="__('Expense Type')" />
                                        <x-select id="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            name="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_type"
                                            required>
                                            <option value="">{{ __('Select Expense Type') }}</option>
                                            <option value="business_meal">{{ __('Business Meal') }}</option>
                                            <option value="fuel">{{ __('Fuel') }}</option>
                                            <option value="tools">{{ __('Tools') }}</option>
                                            <option value="travel">{{ __('Travel') }}</option>
                                            <option value="license_fee">{{ __('License Fee') }}</option>
                                            <option value="mobile_cards">{{ __('Mobile Cards') }}</option>
                                            <option value="courier">{{ __('Courier') }}</option>
                                            <option value="stationery">{{ __('Stationery') }}</option>
                                            <option value="legal_fees">{{ __('Legal Fees') }}</option>
                                            <option value="other">{{ __('Other') }}</option>
                                        </x-select>
                                        <x-input-error class="mt-2" :messages="$errors->get(
                                            'expenseFormData.expenses.' . $expenseIndex . '.expense_type',
                                        )" />
                                    </div>

                                    <!-- Loop through expense details -->
                                    <div class="mt-4">
                                        <h4 class="font-semibold mb-4">{{ __('Expense Details') }}</h4>
                                        @foreach ($expenseFormData['expenses'][$expenseIndex]['expense_details'] as $detailIndex => $detail)
                                            <div class="border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900 p-4 rounded-lg mb-2">
                                                <div class="mt-4">
                                                    <x-input-label
                                                        for="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        :value="__('Date')" />
                                                    <x-text-input type="text"
                                                        id="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        name="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        wire:model="expenseFormData.expenses.{{ $expenseIndex }}.expense_details.{{ $detailIndex }}.date"
                                                        readonly required />
                                                    <x-input-error class="mt-2" :messages="$errors->get(
                                                        'expenseFormData.expenses.' .
                                                            $expenseIndex .
                                                            '.expense_details.' .
                                                            $detailIndex .
                                                            '.date',
                                                    )" />
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


                                </div>

                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addExpense"
                        class="mt-2 text-primary-500 hover:text-primary-700">
                        {{ __('+ Add Expense') }}
                    </button>
                </form>
            </div>

        </div>
        <div
            class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
            <x-secondary-button x-on:click="$dispatch('close');"
                class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                {{ __('Cancel') }}
            </x-secondary-button>
            <x-primary-button wire:click="submitExpense"
                class="bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600">
                {{ __('Claim Expenses') }}
            </x-primary-button>
        </div>
    </x-modal>
    
    <script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </div>
</div>
