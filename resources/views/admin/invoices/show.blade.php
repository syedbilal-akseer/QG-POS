<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Main Content Container -->
    <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mb-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-14 w-14 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xl font-bold">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                                {{ $invoice->customer_name }}
                            </h1>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-sm font-semibold text-gray-500">ID: {{ $invoice->customer_code }}</span>
                                <span class="text-gray-300">|</span>
                                <span class="text-sm text-gray-500">Invoice: #{{ $invoice->invoice_number ?: '---' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @if($invoice->processing_status === 'completed')
                            <div class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800">
                                <i class="fas fa-check-circle mr-2"></i>Ready
                            </div>
                        @else
                            <div class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                <i class="fas fa-clock mr-2"></i>{{ ucfirst($invoice->processing_status) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6 mb-6">
                <!-- WhatsApp Delivery -->
                <div class="md:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-6 flex items-center">
                            <i class="fab fa-whatsapp text-blue-600 dark:text-blue-500 mr-2 text-xl"></i>WhatsApp Delivery
                        </h3>
                        
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 border border-gray-100 dark:border-gray-800 mb-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Phone Number</label>
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl font-bold text-gray-800 dark:text-gray-100 italic">
                                            {{ $invoice->customer_phone ?: 'No number set' }}
                                        </span>
                                        <button onclick="openPhoneModal()" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors underline decoration-dotted">
                                            Change Number
                                        </button>
                                    </div>
                                </div>
                                <button onclick="sendViaWhatsApp()" 
                                        id="mainSendBtn"
                                        class="w-full sm:w-auto px-8 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-bold shadow-md shadow-primary-500/10 transition-all flex items-center justify-center gap-2">
                                    <i class="fab fa-whatsapp text-lg text-white"></i>
                                    <span id="sendBtnLabel" class="text-white">Send Invoice</span>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-900/40">
                                <p class="text-[13px] text-blue-700 dark:text-blue-300 leading-relaxed flex items-center">
                                    <i class="fas fa-file-pdf mr-2 opacity-70"></i> 
                                    <span>Includes PDF attachment</span>
                                </p>
                            </div>
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-100 dark:border-indigo-900/40">
                                <p class="text-[13px] text-indigo-700 dark:text-indigo-300 leading-relaxed flex items-center">
                                    <i class="fas fa-check-double mr-2 opacity-70"></i>
                                    <span>Official Business Template</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Snapshot Summary -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-6 border-b dark:border-gray-700 pb-2">Summary</h3>
                    
                    <div class="space-y-6">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">Amount</span>
                            <div class="text-3xl font-black text-gray-900 dark:text-white mt-1">
                                {{ $invoice->total_amount > 0 ? number_format($invoice->total_amount, 2) : '----' }}
                            </div>
                        </div>

                        <div class="pt-4 space-y-3 border-t dark:border-gray-700">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Range:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ $invoice->page_range ?: '---' }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Dates:</span>
                                @php
                                    $sDate = $invoice->start_date ? $invoice->start_date->format('d-M-Y') : ($invoice->invoice_date ? $invoice->invoice_date->format('d-M-Y') : 'N/A');
                                    $eDate = $invoice->end_date ? $invoice->end_date->format('d-M-Y') : $sDate;
                                    $dateStr = $sDate === $eDate ? $sDate : $sDate . ' to ' . $eDate;
                                @endphp
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ $dateStr }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Pages:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ count($invoice->extracted_pages ?? []) }} extracted</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Metadata -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-6 flex items-center">
                    <i class="fas fa-database text-blue-500 mr-2"></i>Metadata & Logs
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <div class="flex justify-between pb-2 border-b dark:border-gray-700">
                            <span class="text-sm text-gray-500">Original File</span>
                            <span class="text-sm font-semibold truncate max-w-[200px]" title="{{ $invoice->original_filename }}">{{ $invoice->original_filename }}</span>
                        </div>
                        <div class="flex justify-between pb-2 border-b dark:border-gray-700">
                            <span class="text-sm text-gray-500">Upload Date</span>
                            <span class="text-sm font-semibold">{{ $invoice->created_at->format('M d, Y - H:i') }}</span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        @if($invoice->whatsapp_status)
                            <div class="flex justify-between pb-2 border-b dark:border-gray-700">
                                <span class="text-sm text-gray-500">Last Delivery</span>
                                <span class="text-sm font-bold {{ $invoice->whatsapp_status === 'sent' ? 'text-green-600' : 'text-red-500' }}">
                                    {{ strtoupper($invoice->whatsapp_status) }}
                                </span>
                            </div>
                            <div class="flex justify-between pb-2 border-b dark:border-gray-700">
                                <span class="text-sm text-gray-500">Sent At</span>
                                <span class="text-sm font-semibold">{{ $invoice->whatsapp_sent_at ? $invoice->whatsapp_sent_at->format('H:i') : 'N/A' }}</span>
                            </div>
                        @else
                            <div class="h-full flex items-center justify-center bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-dashed dark:border-gray-700">
                                <span class="text-xs text-gray-400 italic">No delivery history found.</span>
                            </div>
                        @endif
                    </div>
                </div>
                
                @if($invoice->whatsapp_message_id)
                    <div class="mt-6 pt-4 border-t dark:border-gray-700">
                        <span class="text-[10px] font-bold text-gray-400 uppercase">Meta Message ID</span>
                        <div class="mt-1 text-[10px] font-mono text-gray-400 break-all bg-gray-50 dark:bg-gray-900 p-2 rounded">
                            {{ $invoice->whatsapp_message_id }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Actions Footer -->
            <div class="flex items-center justify-between py-4">
                <a href="{{ route('invoices.customer', $invoice->customer_code) }}" class="text-sm font-bold text-blue-600 hover:underline">
                    <i class="fas fa-history mr-1"></i> Customer History
                </a>
                
                <form action="{{ route('invoices.destroy', $invoice->id) }}" method="POST" onsubmit="return confirm('Permanently delete this record?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-bold text-red-500 hover:text-red-700">
                        <i class="fas fa-trash-alt mr-1"></i> Delete Record
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Phone Update Modal -->
    <div id="phoneModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-gray-800 dark:text-white font-bold text-lg">
                    <i class="fab fa-whatsapp text-blue-600 mr-2"></i>Update Phone Number
                </h3>
                <button onclick="closePhoneModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">WhatsApp Number (with Country Code)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">+</span>
                        <input type="text" id="phoneInput" 
                               class="w-full pl-8 pr-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg font-bold text-gray-700 dark:text-gray-200"
                               placeholder="923001234567" required>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">
                        Example: 923001234567 (No spaces or dashes)
                    </p>
                </div>
                
                <div class="flex gap-2">
                    <button onclick="closePhoneModal()" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all text-xs">
                        Cancel
                    </button>
                    <button onclick="savePhoneNumber()" id="saveOnlyBtn" class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-all text-xs">
                        Update Only
                    </button>
                    <button onclick="saveAndSendWhatsApp()" id="saveSendBtn" class="flex-[1.5] px-4 py-2 bg-primary-600 text-white font-bold rounded-xl shadow-lg hover:bg-primary-700 transition-all text-xs">
                        Update & Send
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Toast Notifications -->
    <div id="toastContainer" class="fixed top-8 right-8 z-[100] flex flex-col gap-4 pointer-events-none"></div>

    <script>
        const invoiceData = {
            id: {{ $invoice->id }},
            customer_name: '{{ $invoice->customer_name }}',
            customer_code: '{{ $invoice->customer_code }}',
            customer_phone: '{{ $invoice->customer_phone ?? "" }}'
        };

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
            const bgColor = type === 'success' ? 'bg-green-500 shadow-green-500/20' : 'bg-red-500 shadow-red-500/20';
            
            toast.className = `flex items-center p-4 min-w-[320px] ${bgColor} text-white rounded-2xl shadow-2xl transform transition-all translate-x-[120%] opacity-0 pointer-events-auto border border-white/20`;
            toast.innerHTML = `
                <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas ${icon} text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="font-bold text-sm leading-tight">${type.toUpperCase()}</p>
                    <p class="text-sm text-white/90">${message}</p>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Animate In
            setTimeout(() => {
                toast.classList.remove('translate-x-[120%]', 'opacity-0');
            }, 100);
            
            // Animate Out
            setTimeout(() => {
                toast.classList.add('translate-x-[120%]', 'opacity-0');
                setTimeout(() => toast.remove(), 500);
            }, 6000);
        }

        function sendViaWhatsApp() {
            if (!invoiceData.customer_phone) {
                openPhoneModal();
                return;
            }
            
            triggerWhatsAppProcess(invoiceData.customer_phone);
        }

        function saveAndSendWhatsApp() {
            const phone = document.getElementById('phoneInput').value.trim();
            if (!phone || phone.length < 8) {
                showToast('Please enter a valid phone number with country code.', 'error');
                return;
            }
            
            triggerWhatsAppProcess(phone);
        }

        function savePhoneNumber() {
            const phone = document.getElementById('phoneInput').value.trim();
            const btn = document.getElementById('saveOnlyBtn');
            
            if (!phone || phone.length < 8) {
                showToast('Please enter a valid phone number with country code.', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';

            const baseUrl = '{{ route("invoices.update-phone", ":id") }}';
            const url = baseUrl.replace(':id', invoiceData.id);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to update phone number.', 'error');
                    btn.disabled = false;
                    btn.innerText = 'Update Only';
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Update failure. Check network.', 'error');
                btn.disabled = false;
                btn.innerText = 'Update Only';
            });
        }

        function triggerWhatsAppProcess(phone) {
            const btn = document.getElementById('mainSendBtn');
            const saveBtn = document.getElementById('saveSendBtn');
            const label = document.getElementById('sendBtnLabel');
            
            // Set loading
            btn.disabled = true;
            saveBtn.disabled = true;
            const originalLabel = label.innerText;
            label.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2 text-white opacity-100"></i><span class="text-white italic">Delivering...</span>';
            
            const baseUrl = '{{ route("invoices.send-whatsapp", ":id") }}';
            const url = baseUrl.replace(':id', invoiceData.id);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closePhoneModal();
                    
                    // Success state on button
                    label.innerHTML = '<i class="fas fa-check-circle mr-2 opacity-100 italic"></i>Success!';
                    btn.classList.replace('bg-primary-600', 'bg-green-600');
                    
                    // Reset after feedback
                    if (phone !== invoiceData.customer_phone) {
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        setTimeout(() => {
                            btn.disabled = false;
                            label.innerHTML = '<i class="fab fa-whatsapp text-lg mr-2 italic"></i>Send Invoice';
                            btn.classList.replace('bg-green-600', 'bg-primary-600');
                        }, 5000);
                    }
                } else {
                    showToast(data.message || 'Meta API failed to deliver message.', 'error');
                    btn.disabled = false;
                    label.innerHTML = originalLabel;
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Critical delivery failure. Check network.', 'error');
                btn.disabled = false;
                label.innerHTML = originalLabel;
            })
            .finally(() => {
                saveBtn.disabled = false;
            });
        }

        function openPhoneModal() {
            document.getElementById('phoneModal').classList.remove('hidden');
            const input = document.getElementById('phoneInput');
            input.value = invoiceData.customer_phone;
            input.focus();
        }

        function closePhoneModal() {
            document.getElementById('phoneModal').classList.add('hidden');
        }

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePhoneModal();
        });
    </script>
</x-app-layout>