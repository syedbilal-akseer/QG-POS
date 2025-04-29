<div>
    {{ $this->table }}


    @if ($customer)
        <x-modal name="customer_detail" :show="true">
            <div class="p-6 bg-white dark:bg-neutral-800">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Customer Details</h2>
                    <span x-on:click="$dispatch('close')"
                        class="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Customer ID:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->customer_id }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">OU Name:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->ou_name }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">OU ID:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->ou_id }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Customer Name:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->customer_name }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Account Number:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->customer_number }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Customer Site ID:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->customer_site_id }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Salesperson:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->salesperson }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">City:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->city }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Area:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->area }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Address:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->address1 }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Contact Number:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->contact_number }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Email Address:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->email_address }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">NIC:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->nic }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">NTN:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->ntn }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Price List ID:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->price_list_id }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Price List Name:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->price_list_name }}</span>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-medium text-gray-700 dark:text-gray-300">Creation Date:</label>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customer->creation_date }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-end items-center gap-x-2 py-3 px-4 bg-gray-50 dark:bg-neutral-950 border-t border-gray-200 dark:border-neutral-800">
                <x-secondary-button x-on:click="$dispatch('close');"
                    class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-800">
                    {{ __('Cancel') }}
                </x-secondary-button>
            </div>
        </x-modal>
    @endif

</div>
