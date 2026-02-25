<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Customer Invoices') }} - {{ $customerCode }}
            </h2>
            <a href="{{ route('invoices.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Back to All Invoices
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if(session('success'))
                        <div class="bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span>{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="bg-red-100 dark:bg-red-800 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span>{{ session('error') }}</span>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Customer Info Header -->
                    @if($invoices->isNotEmpty())
                        <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-16 w-16">
                                    <div class="h-16 w-16 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xl">
                                        {{ substr($customerCode, 0, 2) }}
                                    </div>
                                </div>
                                <div class="ml-6">
                                    <h3 class="text-2xl font-bold text-blue-800 dark:text-blue-200">
                                        {{ $invoices->first()->customer_name }}
                                    </h3>
                                    <p class="text-blue-600 dark:text-blue-400">Customer Code: {{ $customerCode }}</p>
                                    <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">
                                        {{ $invoices->count() }} invoice{{ $invoices->count() !== 1 ? 's' : '' }} found
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Invoices Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Invoice Details
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Original File
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Pages Extracted
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Uploaded
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                @forelse($invoices as $invoice)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-green-500 flex items-center justify-center text-white">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    @if($invoice->invoice_number)
                                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            Invoice #{{ $invoice->invoice_number }}
                                                        </div>
                                                    @endif
                                                    @if($invoice->total_amount)
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            Amount: <span class="font-medium">{{ number_format($invoice->total_amount, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    @if($invoice->invoice_date)
                                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                                            Date: {{ $invoice->invoice_date->format('M d, Y') }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 dark:text-gray-100 max-w-xs">
                                                <div class="font-medium truncate" title="{{ $invoice->original_filename }}">
                                                    {{ $invoice->original_filename }}
                                                </div>
                                                @if($invoice->notes)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ Str::limit($invoice->notes, 50) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    <i class="fas fa-file-alt mr-1"></i>
                                                    {{ $invoice->page_range ?: 'N/A' }}
                                                </span>
                                                @if($invoice->extracted_pages)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ count($invoice->extracted_pages) }} page{{ count($invoice->extracted_pages) !== 1 ? 's' : '' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @switch($invoice->processing_status)
                                                @case('completed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <i class="fas fa-check-circle mr-1"></i>Completed
                                                    </span>
                                                    @break
                                                @case('processing')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                        <i class="fas fa-spinner fa-spin mr-1"></i>Processing
                                                    </span>
                                                    @break
                                                @case('failed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>Failed
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                        <i class="fas fa-clock mr-1"></i>Pending
                                                    </span>
                                            @endswitch
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <div>{{ $invoice->uploaded_at->format('M d, Y') }}</div>
                                            <div class="text-xs">{{ $invoice->uploaded_at->format('h:i A') }}</div>
                                            <div class="text-xs">by {{ $invoice->uploader->name ?? 'Unknown' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-3">
                                                @if($invoice->processing_status === 'completed' && $invoice->pdf_path)
                                                    <a href="{{ route('invoices.download', $invoice->id) }}" 
                                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                       title="Download Customer PDF">
                                                        <i class="fas fa-download mr-1"></i>Download
                                                    </a>
                                                    <button type="button"
                                                            onclick="sendWhatsApp({{ $invoice->id }}, '{{ $invoice->customer_code }}')"
                                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                            title="Send via WhatsApp">
                                                        <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                                    </button>
                                                @endif
                                                <form action="{{ route('invoices.destroy', $invoice->id) }}" 
                                                      method="POST" 
                                                      class="inline-block"
                                                      onsubmit="return confirm('Are you sure you want to delete this invoice?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                            title="Delete Invoice">
                                                        <i class="fas fa-trash mr-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-folder-open text-4xl mb-4"></i>
                                                <p class="text-lg mb-2">No invoices found for customer {{ $customerCode }}</p>
                                                <a href="{{ route('invoices.upload') }}" 
                                                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                    Upload Invoice PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Customer Summary -->
                    @if($invoices->isNotEmpty())
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                                <div class="text-blue-800 dark:text-blue-200">
                                    <h4 class="text-sm font-medium">Total Invoices</h4>
                                    <p class="text-2xl font-bold">{{ $invoices->count() }}</p>
                                </div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <div class="text-green-800 dark:text-green-200">
                                    <h4 class="text-sm font-medium">Total Amount</h4>
                                    <p class="text-2xl font-bold">{{ number_format($invoices->sum('total_amount'), 2) }}</p>
                                </div>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                                <div class="text-purple-800 dark:text-purple-200">
                                    <h4 class="text-sm font-medium">Total Pages</h4>
                                    <p class="text-2xl font-bold">{{ $invoices->flatMap->extracted_pages->count() }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Phone Number Modal -->
    <div id="whatsappModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            <i class="fab fa-whatsapp text-green-500 mr-2"></i>Send Invoice via WhatsApp
                        </h3>
                        <button onclick="closeWhatsAppModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="whatsappForm">
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Phone Number (with country code)
                            </label>
                            <input type="text" 
                                   id="phone" 
                                   name="phone"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                   placeholder="923001234567"
                                   required>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Format: 92 for Pakistan, 1 for US, etc.
                            </p>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" 
                                    onclick="closeWhatsAppModal()"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                                <i class="fab fa-whatsapp mr-1"></i>Send WhatsApp
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentInvoiceId = null;
        let currentCustomerCode = null;

        function sendWhatsApp(invoiceId, customerCode) {
            currentInvoiceId = invoiceId;
            currentCustomerCode = customerCode;
            document.getElementById('whatsappModal').classList.remove('hidden');
            document.getElementById('phone').focus();
        }

        function closeWhatsAppModal() {
            document.getElementById('whatsappModal').classList.add('hidden');
            document.getElementById('phone').value = '';
            currentInvoiceId = null;
            currentCustomerCode = null;
        }

        document.getElementById('whatsappForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const phone = document.getElementById('phone').value;
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending...';
            
            // Build URL using Laravel route helper
            const baseUrl = '{{ route("invoices.send-whatsapp", ":id") }}';
            const url = baseUrl.replace(':id', currentInvoiceId);
            console.log('Requesting URL:', url);
            console.log('Invoice ID:', currentInvoiceId);
            
            // Send request
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    // Log the actual error response
                    return response.text().then(text => {
                        console.error('Error response body:', text);
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success message
                    showNotification('Invoice sent successfully via WhatsApp!', 'success');
                    closeWhatsAppModal();
                } else {
                    showNotification('Failed to send WhatsApp: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error: ' + error.message, 'error');
            })
            .finally(() => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg max-w-sm ${
                type === 'success' 
                    ? 'bg-green-100 border border-green-400 text-green-700' 
                    : 'bg-red-100 border border-red-400 text-red-700'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Close modal when clicking outside
        document.getElementById('whatsappModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeWhatsAppModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('whatsappModal').classList.contains('hidden')) {
                closeWhatsAppModal();
            }
        });
    </script>
</x-app-layout>