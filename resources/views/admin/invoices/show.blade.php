<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Invoice Details') }} - {{ $invoice->customer_name }}
            </h2>
            <div class="flex space-x-2">
                @if($invoice->processing_status === 'completed' && $invoice->pdf_path)
                    <a href="{{ route('invoices.download', $invoice->id) }}" 
                       class="bg-primary-600 hover:bg-primary-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-download mr-2"></i>Download PDF
                    </a>
                @endif
                <a href="{{ route('invoices.index') }}" 
                   class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-2 px-4 rounded">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Invoices
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Invoice Header Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-16 w-16">
                                <div class="h-16 w-16 rounded-full bg-primary-600 flex items-center justify-center text-white font-bold text-xl">
                                    {{ substr($invoice->customer_code, 0, 2) }}
                                </div>
                            </div>
                            <div class="ml-6">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ $invoice->customer_name }}
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">Customer Code: {{ $invoice->customer_code }}</p>
                                @if($invoice->customer_phone)
                                    <p class="text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-phone mr-2"></i>Phone: {{ $invoice->customer_phone }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        
                        <div class="text-right">
                            @switch($invoice->processing_status)
                                @case('completed')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-check-circle mr-2"></i>Completed
                                    </span>
                                    @break
                                @case('processing')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <i class="fas fa-spinner fa-spin mr-2"></i>Processing
                                    </span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <i class="fas fa-exclamation-circle mr-2"></i>Failed
                                    </span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                        <i class="fas fa-clock mr-2"></i>Pending
                                    </span>
                            @endswitch
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Basic Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h4 class="text-lg font-semibold mb-4">
                            <i class="fas fa-info-circle mr-2"></i>Basic Information
                        </h4>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-600 dark:text-gray-400">Invoice Number:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ $invoice->invoice_number ?: 'N/A' }}</span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-600 dark:text-gray-400">Invoice Date:</span>
                                <span class="text-gray-900 dark:text-gray-100">
                                    {{ $invoice->invoice_date ? $invoice->invoice_date->format('M d, Y') : 'N/A' }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-600 dark:text-gray-400">Total Amount:</span>
                                <span class="text-gray-900 dark:text-gray-100 font-bold">
                                    {{ $invoice->total_amount ? number_format($invoice->total_amount, 2) : 'N/A' }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-600 dark:text-gray-400">Pages:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ $invoice->page_range ?: 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h4 class="text-lg font-semibold mb-4">
                            <i class="fas fa-file-pdf mr-2"></i>File Information
                        </h4>
                        
                        <div class="space-y-3">
                            <div>
                                <span class="font-medium text-gray-600 dark:text-gray-400">Original File:</span>
                                <p class="text-gray-900 dark:text-gray-100 break-all">{{ $invoice->original_filename }}</p>
                            </div>
                            
                            <div>
                                <span class="font-medium text-gray-600 dark:text-gray-400">Extracted Pages:</span>
                                <p class="text-gray-900 dark:text-gray-100">
                                    @if($invoice->extracted_pages)
                                        {{ implode(', ', $invoice->extracted_pages) }}
                                        ({{ count($invoice->extracted_pages) }} pages)
                                    @else
                                        N/A
                                    @endif
                                </p>
                            </div>
                            
                            <div>
                                <span class="font-medium text-gray-600 dark:text-gray-400">Processing Status:</span>
                                <p class="text-gray-900 dark:text-gray-100">{{ ucfirst($invoice->processing_status) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Information -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h4 class="text-lg font-semibold mb-4">
                        <i class="fas fa-upload mr-2"></i>Upload Information
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">Uploaded By:</span>
                            <p class="text-gray-900 dark:text-gray-100">{{ $invoice->uploader->name ?? 'Unknown' }}</p>
                        </div>
                        
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">Upload Date:</span>
                            <p class="text-gray-900 dark:text-gray-100">{{ $invoice->uploaded_at->format('M d, Y h:i A') }}</p>
                        </div>
                        
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">Created:</span>
                            <p class="text-gray-900 dark:text-gray-100">{{ $invoice->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            @if($invoice->notes)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h4 class="text-lg font-semibold mb-4">
                            <i class="fas fa-sticky-note mr-2"></i>Notes
                        </h4>
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $invoice->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h4 class="text-lg font-semibold mb-4">
                        <i class="fas fa-cog mr-2"></i>Actions
                    </h4>
                    
                    <div class="flex flex-wrap gap-3">
                        @if($invoice->processing_status === 'completed' && $invoice->pdf_path)
                            <a href="{{ route('invoices.download', $invoice->id) }}" 
                               class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <i class="fas fa-download mr-2"></i>Download PDF
                            </a>
                            
                            <button onclick="sendViaWhatsApp()" 
                                    class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-600 active:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <i class="fab fa-whatsapp mr-2"></i>Send via WhatsApp
                            </button>
                        @endif
                        
                        <a href="{{ route('invoices.customer', $invoice->customer_code) }}" 
                           class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <i class="fas fa-user mr-2"></i>View All Customer Invoices
                        </a>
                        
                        <form action="{{ route('invoices.destroy', $invoice->id) }}" 
                              method="POST" 
                              class="inline-block"
                              onsubmit="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <i class="fas fa-trash mr-2"></i>Delete Invoice
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Phone Number Modal -->
    <div id="phoneModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            <i class="fab fa-whatsapp text-green-500 mr-2"></i>Send Invoice via WhatsApp
                        </h3>
                        <button onclick="closePhoneModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form id="whatsappForm">
                        <div class="mb-4">
                            <label for="phoneInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Phone Number (with country code)
                            </label>
                            <input type="text" 
                                   id="phoneInput" 
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
                                    onclick="closePhoneModal()"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md">
                                Cancel
                            </button>
                            <button type="button"
                                    id="sendWhatsAppBtn"
                                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                                <i class="fab fa-whatsapp mr-1"></i>Send WhatsApp
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="whatsappAlert" class="fixed top-4 right-4 z-50 hidden">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span id="alertMessage"></span>
            </div>
        </div>
    </div>

    <script>
        const invoiceData = {
            id: {{ $invoice->id }},
            customer_name: '{{ $invoice->customer_name }}',
            customer_code: '{{ $invoice->customer_code }}',
            customer_phone: '{{ $invoice->customer_phone ?? "" }}',
            invoice_number: '{{ $invoice->invoice_number ?? "N/A" }}',
            total_amount: '{{ $invoice->total_amount ? number_format($invoice->total_amount, 2) : "N/A" }}'
        };

        function sendViaWhatsApp() {
            // Always show modal to let user confirm/modify phone number
            document.getElementById('phoneModal').classList.remove('hidden');
            
            // Pre-fill with existing phone number if available
            const phoneInput = document.getElementById('phoneInput');
            if (invoiceData.customer_phone && invoiceData.customer_phone.trim() !== '') {
                phoneInput.value = invoiceData.customer_phone;
            }
            phoneInput.focus();
        }

        function sendWhatsAppMessage(phoneNumber) {
            // Show loading state
            const sendBtn = document.getElementById('sendWhatsAppBtn');
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            sendBtn.disabled = true;

            // Build URL using Laravel route helper (same as customer.blade.php)
            const baseUrl = '{{ route("invoices.send-whatsapp", ":id") }}';
            const url = baseUrl.replace(':id', invoiceData.id);
            console.log('Requesting URL:', url);
            console.log('Invoice ID:', invoiceData.id);
            
            // Send request
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phoneNumber })
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
                // Reset button
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;

                if (data.success) {
                    showAlert(data.message, 'success');
                    
                    // Close modal if it was open
                    closePhoneModal();
                    
                    // Update invoice data if phone was updated
                    if (phoneNumber && phoneNumber !== invoiceData.customer_phone) {
                        invoiceData.customer_phone = phoneNumber;
                        // Reload page to show updated phone number
                        setTimeout(() => location.reload(), 2000);
                    }
                } else {
                    showAlert(data.message || 'Failed to send WhatsApp message', 'error');
                }
            })
            .catch(error => {
                console.error('WhatsApp send error:', error);
                
                // Reset button
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
                
                showAlert('Network error. Please try again.', 'error');
            });
        }

        function updateCustomerPhone(phoneNumber) {
            // Send AJAX request to update customer phone number
            fetch(`/admin/invoices/${invoiceData.id}/update-phone`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    phone: phoneNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    invoiceData.customer_phone = phoneNumber;
                    location.reload(); // Reload to show updated phone number
                }
            })
            .catch(error => {
                console.error('Error updating phone number:', error);
            });
        }

        function closePhoneModal() {
            document.getElementById('phoneModal').classList.add('hidden');
            document.getElementById('phoneInput').value = '';
        }

        function showAlert(message, type = 'success') {
            const alert = document.getElementById('whatsappAlert');
            const alertMessage = document.getElementById('alertMessage');
            
            alertMessage.textContent = message;
            
            if (type === 'error') {
                alert.querySelector('div').className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-lg';
                alert.querySelector('i').className = 'fas fa-exclamation-circle mr-2';
            } else {
                alert.querySelector('div').className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg';
                alert.querySelector('i').className = 'fas fa-check-circle mr-2';
            }
            
            alert.classList.remove('hidden');
            
            setTimeout(() => {
                alert.classList.add('hidden');
            }, 5000);
        }

        // Event listeners
        document.getElementById('sendWhatsAppBtn').addEventListener('click', function() {
            const phoneInput = document.getElementById('phoneInput');
            const phone = phoneInput.value.trim();
            
            if (!phone) {
                showAlert('Please enter a phone number', 'error');
                return;
            }
            
            // Basic phone validation
            const phoneRegex = /^[\+]?[0-9\s\-]{8,20}$/;
            if (!phoneRegex.test(phone)) {
                showAlert('Please enter a valid phone number', 'error');
                return;
            }
            
            sendWhatsAppMessage(phone);
        });
        
        // Enter key support in modal
        document.getElementById('phoneInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('sendWhatsAppBtn').click();
            }
        });
        
        // Close modal on outside click
        document.getElementById('phoneModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePhoneModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('phoneModal').classList.contains('hidden')) {
                closePhoneModal();
            }
        });
    </script>
</x-app-layout>