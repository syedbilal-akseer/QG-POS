@push('title')
    Create New Receipt{{ isset($fromEdit) && $fromEdit ? ' - Continue from Previous' : '' }}
@endpush

<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Create New Receipt{{ isset($fromEdit) && $fromEdit ? ' - Continue from Previous' : '' }}</h4>
                        <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Back to List
                        </a>
                    </div>

                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(isset($fromEdit) && $fromEdit)
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Continuing from previous receipt:</strong> Customer has been pre-selected. You can create another receipt for the same customer.
                            </div>
                        @endif

                        <form action="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.store') : route('reciepts.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <!-- Oracle Basic Information -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Oracle Receipt Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="ou_id" class="form-label">Operating Unit ID *</label>
                                            <input type="text" name="ou_id" id="ou_id" class="form-control" 
                                                value="{{ old('ou_id') }}" 
                                                placeholder="Auto-filled from customer" readonly>
                                            <small class="text-muted">Automatically filled when customer is selected</small>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="customer_id" class="form-label">Customer *</label>
                                            <select name="customer_id" id="customer_id" class="form-select" required>
                                                <option value="">Select a customer</option>
                                                @foreach($customers as $customer)
                                                    <option value="{{ $customer->customer_id }}" 
                                                        {{ old('customer_id', $preSelectedCustomerId ?? '') == $customer->customer_id ? 'selected' : '' }}
                                                        data-ou-id="{{ $customer->ou_id }}" 
                                                        data-ou-name="{{ $customer->ou_name }}"
                                                        data-credit-limit="{{ $customer->overall_credit_limit }}">
                                                        {{ $customer->customer_name }} ({{ $customer->customer_id }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="ou_name" class="form-label">Operating Unit Name *</label>
                                            <input type="text" name="ou_name" id="ou_name" class="form-control" 
                                                value="{{ old('ou_name') }}" 
                                                placeholder="Auto-filled from customer" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="description" class="form-label">Comments *</label>
                                            <textarea name="description" id="description" class="form-control" rows="4" required 
                                                placeholder="Enter receipt description/comments">{{ old('description') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Receipt Details -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Receipt Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="receipt_number" class="form-label">Receipt Number *</label>
                                            <input type="text" name="receipt_number" id="receipt_number" class="form-control" 
                                                value="{{ old('receipt_number') }}" required 
                                                placeholder="Enter unique receipt number">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="receipt_date" class="form-label">Receipt Date *</label>
                                            <input type="date" name="receipt_date" id="receipt_date" class="form-control" 
                                                value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="receipt_amount" class="form-label">Receipt Amount *</label>
                                            <input type="number" name="receipt_amount" id="receipt_amount" class="form-control" 
                                                value="{{ old('receipt_amount') }}" step="0.01" min="0" required 
                                                placeholder="Enter amount">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="currency" class="form-label">Currency *</label>
                                            <select name="currency" id="currency" class="form-select" required>
                                                <option value="PKR" {{ old('currency', 'PKR') == 'PKR' ? 'selected' : '' }}>PKR</option>
                                                <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                                                <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR</option>
                                                <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="receipt_method" class="form-label">Receipt Method *</label>
                                            <select name="receipt_method" id="receipt_method" class="form-select" required>
                                                <option value="">Select method</option>
                                                <option value="Check" {{ old('receipt_method') == 'Check' ? 'selected' : '' }}>Check</option>
                                                <option value="Cash" {{ old('receipt_method') == 'Cash' ? 'selected' : '' }}>Cash</option>
                                                <option value="Credit Card" {{ old('receipt_method') == 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                                                <option value="Automatic Receipt" {{ old('receipt_method') == 'Automatic Receipt' ? 'selected' : '' }}>Automatic Receipt</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Details -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Payment Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="cheque_no" class="form-label">Cheque Number</label>
                                            <input type="text" name="cheque_no" id="cheque_no" class="form-control" 
                                                value="{{ old('cheque_no') }}" 
                                                placeholder="Enter cheque number">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="cheque_date" class="form-label">Cheque Date</label>
                                            <input type="date" name="cheque_date" id="cheque_date" class="form-control" 
                                                value="{{ old('cheque_date') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="bank_account_id" class="form-label">Bank Account</label>
                                            <select name="bank_account_id" id="bank_account_id" class="form-select">
                                                <option value="">Select bank account</option>
                                                @foreach($oracleBanks as $bank)
                                                    <option value="{{ $bank->bank_account_id }}" 
                                                        {{ old('bank_account_id') == $bank->bank_account_id ? 'selected' : '' }}
                                                        data-ou-id="{{ $bank->ou_id }}">
                                                        {{ $bank->bank_name }} - {{ $bank->bank_account_name }} ({{ $bank->bank_account_num }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="cheque_image" class="form-label">Cheque Image</label>
                                            <input type="file" name="cheque_image" id="cheque_image" class="form-control" accept="image/*">
                                            <small class="text-muted">Optional: Upload image of the cheque</small>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Create Receipt
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle customer selection to auto-fill OU fields
        document.getElementById('customer_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const ouId = selectedOption.getAttribute('data-ou-id');
            const ouName = selectedOption.getAttribute('data-ou-name');

            document.getElementById('ou_id').value = ouId || '';
            document.getElementById('ou_name').value = ouName || '';

            // Filter banks by OU ID
            filterBanksByOU(ouId);
        });

        // Filter banks based on OU ID
        function filterBanksByOU(ouId) {
            const bankSelect = document.getElementById('bank_account_id');
            const allOptions = bankSelect.querySelectorAll('option');

            allOptions.forEach(option => {
                if (option.value === '') {
                    return; // Keep the "Select bank account" option
                }

                const bankOuId = option.getAttribute('data-ou-id');
                if (!ouId || bankOuId === ouId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                    // Deselect if currently selected
                    if (option.selected) {
                        bankSelect.value = '';
                    }
                }
            });
        }

        // Auto-trigger customer selection if pre-selected (for "Add More Receipt" flow)
        document.addEventListener('DOMContentLoaded', function() {
            const customerSelect = document.getElementById('customer_id');
            if (customerSelect.value) {
                customerSelect.dispatchEvent(new Event('change'));
            }
        });

        // Handle payment method changes
        document.getElementById('receipt_method').addEventListener('change', function() {
            const chequeFields = ['cheque_no', 'cheque_date', 'bank_account_id', 'cheque_image'];
            const isCheck = this.value === 'Check';

            chequeFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const container = field.closest('.col-md-4, .col-md-6');
                
                if (isCheck) {
                    container.style.display = '';
                    if (fieldId === 'cheque_no') {
                        field.setAttribute('required', 'required');
                    }
                } else {
                    container.style.display = isCheck ? '' : 'none';
                    field.removeAttribute('required');
                    field.value = '';
                }
            });
        });

        // Trigger payment method change on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('receipt_method').dispatchEvent(new Event('change'));
        });
    </script>

</x-app-layout>