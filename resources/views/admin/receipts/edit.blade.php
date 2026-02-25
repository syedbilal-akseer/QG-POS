    @push('title')
        Edit Receipt #{{ $receipt->receipt_number }}
    @endpush

    <x-app-layout>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
        <style>
            .clickable-image {
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .clickable-image:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }
        </style>

        <div class="container mt-2">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Edit Receipt #{{ $receipt->receipt_number }}</h4>
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

                            <form id="receiptForm" action="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.update', $receipt->id) : route('reciepts.update', $receipt->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <!-- Oracle Basic Information -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Oracle Receipt Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-2">
                                                <label for="ou_id" class="form-label">Operating Unit ID *</label>
                                                <input type="text" name="ou_id" id="ou_id" class="form-control" 
                                                    value="{{ old('ou_id', $receipt->ou_id) }}" 
                                                    placeholder="Auto-filled from customer" readonly>
                                                <small class="text-muted">Automatically filled when customer is selected</small>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="customer_id" class="form-label">Customer *</label>
                                                <select name="customer_id" id="customer_id" class="form-select" required>
                                                    <option value="">Select Customer</option>
                                                    @foreach($customers as $customer)
                                                        <option value="{{ $customer->customer_id }}" 
                                                            {{ old('customer_id', $receipt->customer_id) == $customer->customer_id ? 'selected' : '' }}
                                                            data-credit-limit="{{ $customer->overall_credit_limit ?? '' }}"
                                                            data-ou-id="{{ $customer->ou_id ?? '' }}"
                                                            data-all-data="{{ json_encode($customer->toArray()) }}">
                                                            {{ $customer->customer_name }} ({{ $customer->customer_id }})
                                                            | OU_ID: "{{ $customer->ou_id }}" | OU_NAME: "{{ $customer->ou_name }}"
                                                            | Credit: {{ $customer->overall_credit_limit }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Filter Banks by OU</label>
                                                <select class="form-select ou-filter-select" data-target="bank_account_id">
                                                    <option value="">All OUs</option>
                                                    @foreach($operatingUnits as $ou)
                                                        <option value="{{ $ou }}">{{ $ou }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="bank_account_id" class="form-label">Instrument *</label>
                                                <select name="bank_account_id" id="bank_account_id" class="form-select">
                                                    <option value="">Select Instrument</option>
                                                    @forelse($oracleBanks ?? [] as $bank)
                                                        <option value="{{ $bank->bank_account_id }}" 
                                                            {{ old('bank_account_id', $receipt->bank_account_id) == $bank->bank_account_id ? 'selected' : '' }}
                                                            data-ou-id="{{ $bank->ou_id }}"
                                                            data-debug-info="Bank: {{ $bank->bank_name }} | OU: {{ $bank->ou_id }} | Account: {{ $bank->bank_account_id }}">
                                                            {{ \Illuminate\Support\Str::limit($bank->bank_name, 25) }} - {{ \Illuminate\Support\Str::limit($bank->bank_account_name, 20) }} - {{ substr($bank->bank_account_num, -4) }} ({{ $bank->bank_account_id }})
                                                        </option>
                                                    @empty
                                                        <option value="" disabled>No banks available</option>
                                                    @endforelse
                                                </select>
                                                
                                                <!-- Debug banks info -->
                                                <div class="mt-2">
                                                    @if(isset($oracleBanks))
                                                        <small class="text-muted">Total banks loaded: {{ $oracleBanks->count() }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="receipt_amount" class="form-label">Total Receipt Amount *</label>
                                                <input type="number" name="receipt_amount" id="receipt_amount" class="form-control" 
                                                    step="0.01" min="0.01" value="{{ old('receipt_amount', $receipt->receipt_amount) }}" 
                                                    placeholder="0.00" required readonly>
                                                <small class="text-muted">Auto-calculated from cash and cheque amounts</small>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="receipt_date" class="form-label">Receipt Date *</label>
                                                <input type="date" name="receipt_date" id="receipt_date" class="form-control" 
                                                    value="{{ old('receipt_date', $receipt->receipt_date?->format('Y-m-d')) ?? date('Y-m-d') }}" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="currency" class="form-label">Currency *</label>
                                                <select name="currency" id="currency" class="form-select" required>
                                                    <option value="PKR" {{ old('currency', $receipt->currency) == 'PKR' ? 'selected' : '' }}>PKR</option>
                                                    <option value="USD" {{ old('currency', $receipt->currency) == 'USD' ? 'selected' : '' }}>USD</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <label for="description" class="form-label">Comments *</label>
                                                <textarea name="description" id="description" class="form-control" rows="4" required 
                                                    placeholder="Enter receipt description/comments">{{ old('description', $receipt->description) }}</textarea>
                                            </div>
                                        </div>

                                        <!-- Hidden fields for Oracle -->
                                        <input type="hidden" name="comments" id="hidden_comments" value="{{ old('comments', $receipt->comments) }}">
                                        <input type="hidden" name="status" value="">
                                        <input type="hidden" name="creation_date" value="{{ date('Y-m-d H:i:s') }}">
                                    </div>
                                </div>

                                <!-- Cash Payment Section -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Cash Payment</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="cash_amount" class="form-label">Cash Amount</label>
                                                <input type="number" name="cash_amount" id="cash_amount" class="form-control receipt-amount-input" 
                                                    step="0.01" min="0" value="{{ old('cash_amount', $receipt->cash_amount) }}" placeholder="0.00">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="cash_maturity_date" class="form-label">Cash Maturity Date</label>
                                                <input type="date" name="cash_maturity_date" id="cash_maturity_date" class="form-control" 
                                                    value="{{ old('cash_maturity_date', $receipt->cash_maturity_date?->format('Y-m-d')) }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cheque Payment Section -->
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Cheque Payment</h5>
                                        <button type="button" class="btn btn-sm btn-primary" id="addChequeBtn">
                                            <i class="fas fa-plus"></i> Add Cheque
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="chequesContainer">
                                            @if($receipt->cheques && $receipt->cheques->count() > 0)
                                                @foreach($receipt->cheques as $index => $cheque)
                                                    <div class="cheque-item border rounded p-3 mb-3" data-cheque-index="{{ $index }}">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <h6 class="mb-0">Cheque #{{ $index + 1 }}</h6>
                                                            @if($index > 0)
                                                                <button type="button" class="btn btn-sm btn-danger remove-cheque-btn">
                                                                    <i class="fas fa-trash"></i> Remove
                                                                </button>
                                                            @endif
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Cheque Number</label>
                                                                <input type="text" name="cheques[{{ $index }}][cheque_no]" class="form-control" 
                                                                    value="{{ old("cheques.{$index}.cheque_no", $cheque->cheque_no) }}" placeholder="Enter cheque number">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Cheque Amount</label>
                                                                <input type="number" name="cheques[{{ $index }}][cheque_amount]" class="form-control receipt-amount-input" 
                                                                    step="0.01" min="0" value="{{ old("cheques.{$index}.cheque_amount", $cheque->cheque_amount) }}" placeholder="0.00">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Bank Name</label>
                                                                <input type="text" name="cheques[{{ $index }}][bank_name]" class="form-control bank-name-input" 
                                                                    value="{{ old("cheques.{$index}.bank_name", $cheque->bank_name) }}" placeholder="Enter bank name">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-2">
                                                                <label class="form-label">Filter OU</label>
                                                                <select class="form-select ou-filter-select" data-target="cheques-{{ $index }}-instrument">
                                                                    <option value="">All OUs</option>
                                                                    @foreach($operatingUnits as $ou)
                                                                        <option value="{{ $ou }}">{{ $ou }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Instrument *</label>
                                                                <select name="cheques[{{ $index }}][instrument_id]" id="cheques-{{ $index }}-instrument" class="form-select instrument-select" required>
                                                                    <option value="">Select Instrument</option>
                                                                    @forelse($oracleBanks ?? [] as $bank)
                                                                        <option value="{{ $bank->bank_account_id }}" 
                                                                            {{ old("cheques.{$index}.instrument_id", $cheque->instrument_id) == $bank->bank_account_id ? 'selected' : '' }}
                                                                            data-ou-id="{{ $bank->ou_id }}"
                                                                            data-bank-name="{{ $bank->bank_name }}"
                                                                            data-account-name="{{ $bank->bank_account_name }}"
                                                                            data-account-num="{{ $bank->bank_account_num }}"
                                                                            data-org-id="{{ $bank->org_id }}"
                                                                            data-debug-info="Bank: {{ $bank->bank_name }} | OU: {{ $bank->ou_id }} | Account: {{ $bank->bank_account_id }}">
                                                                            {{ \Illuminate\Support\Str::limit($bank->bank_name, 25) }} - {{ \Illuminate\Support\Str::limit($bank->bank_account_name, 20) }} - {{ substr($bank->bank_account_num, -4) }} ({{ $bank->bank_account_id }})
                                                                        </option>
                                                                    @empty
                                                                        <option value="" disabled>No banks available</option>
                                                                    @endforelse
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Cheque Date *</label>
                                                                <input type="date" name="cheques[{{ $index }}][cheque_date]" class="form-control" 
                                                                    value="{{ old("cheques.{$index}.cheque_date", $cheque->cheque_date?->format('Y-m-d')) }}" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Maturity Date *</label>
                                                                <input type="date" name="cheques[{{ $index }}][maturity_date]" class="form-control" 
                                                                    value="{{ old("cheques.{$index}.maturity_date", $cheque->maturity_date?->format('Y-m-d')) }}" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-8">
                                                                <label class="form-label">Cheque Comments</label>
                                                                <textarea name="cheques[{{ $index }}][comments]" class="form-control" rows="2" 
                                                                    placeholder="Enter comments about the cheque">{{ old("cheques.{$index}.comments", $cheque->comments) }}</textarea>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-check mt-4">
                                                                    <input type="checkbox" name="cheques[{{ $index }}][is_third_party_cheque]" 
                                                                        class="form-check-input" value="1" 
                                                                        {{ old("cheques.{$index}.is_third_party_cheque", $cheque->is_third_party_cheque) ? 'checked' : '' }}>
                                                                    <label class="form-check-label">Third Party Cheque</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <label class="form-label">Cheque Images</label>
                                                                <input type="file" name="cheques[{{ $index }}][cheque_images][]" class="form-control" 
                                                                    accept="image/*" multiple>
                                                                @if($cheque->cheque_images && is_array($cheque->cheque_images))
                                                                    <div class="mt-2">
                                                                        <small class="text-muted">Current images:</small><br>
                                                                        @foreach($cheque->cheque_images as $imageIndex => $image)
                                                                            <img src="{{ $image }}" alt="Cheque image" 
                                                                                class="img-thumbnail me-2 clickable-image" 
                                                                                style="max-height: 100px; cursor: pointer;" 
                                                                                onclick="viewImage('{{ $image }}', 'Cheque #{{ $index + 1 }} - Image {{ $imageIndex + 1 }}')">
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Hidden fields for instrument data -->
                                                        <input type="hidden" name="cheques[{{ $index }}][instrument_name]" value="{{ old("cheques.{$index}.instrument_name", $cheque->instrument_name) }}">
                                                        <input type="hidden" name="cheques[{{ $index }}][instrument_account_name]" value="{{ old("cheques.{$index}.instrument_account_name", $cheque->instrument_account_name) }}">
                                                        <input type="hidden" name="cheques[{{ $index }}][instrument_account_num]" value="{{ old("cheques.{$index}.instrument_account_num", $cheque->instrument_account_num) }}">
                                                        <input type="hidden" name="cheques[{{ $index }}][org_id]" value="{{ old("cheques.{$index}.org_id", $cheque->org_id) }}">
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="cheque-item border rounded p-3 mb-3" data-cheque-index="0">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="mb-0">Cheque #1</h6>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">Cheque Number</label>
                                                            <input type="text" name="cheques[0][cheque_no]" class="form-control" placeholder="Enter cheque number">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Cheque Amount</label>
                                                            <input type="number" name="cheques[0][cheque_amount]" class="form-control receipt-amount-input" 
                                                                step="0.01" min="0" placeholder="0.00">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Bank Name</label>
                                                            <input type="text" name="cheques[0][bank_name]" class="form-control bank-name-input" placeholder="Enter bank name">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">Instrument *</label>
                                                            <select name="cheques[0][instrument_id]" class="form-select instrument-select" required>
                                                                <option value="">Select Instrument</option>
                                                                @if(isset($oracleBanks) && $oracleBanks->count() > 0)
                                                                    @foreach($oracleBanks as $bank)
                                                                        <option value="{{ $bank->bank_account_id }}" 
                                                                            data-bank-name="{{ $bank->bank_name }}"
                                                                            data-account-name="{{ $bank->bank_account_name }}"
                                                                            data-account-num="{{ $bank->bank_account_num }}"
                                                                            data-org-id="{{ $bank->org_id }}">
                                                                            {{ $bank->bank_name }} - {{ $bank->bank_account_name }}
                                                                        </option>
                                                                    @endforeach
                                                                @endif
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Cheque Date *</label>
                                                            <input type="date" name="cheques[0][cheque_date]" class="form-control" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Maturity Date *</label>
                                                            <input type="date" name="cheques[0][maturity_date]" class="form-control" required>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-8">
                                                            <label class="form-label">Cheque Comments</label>
                                                            <textarea name="cheques[0][comments]" class="form-control" rows="2" 
                                                                placeholder="Enter comments about the cheque"></textarea>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-check mt-4">
                                                                <input type="checkbox" name="cheques[0][is_third_party_cheque]" class="form-check-input" value="1">
                                                                <label class="form-check-label">Third Party Cheque</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <label class="form-label">Cheque Images</label>
                                                            <input type="file" name="cheques[0][cheque_images][]" class="form-control" 
                                                                accept="image/*" multiple>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Hidden fields for instrument data -->
                                                    <input type="hidden" name="cheques[0][instrument_name]" value="">
                                                    <input type="hidden" name="cheques[0][instrument_account_name]" value="">
                                                    <input type="hidden" name="cheques[0][instrument_account_num]" value="">
                                                    <input type="hidden" name="cheques[0][org_id]" value="">
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>


                                <!-- Submit Buttons -->
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> Update Receipt
                                    </button>
                                    @php
                                        // Check if receipt has required Instrument data
                                        $readyForOracle = !empty($receipt->bank_account_id);
                                        
                                        // If main instrument is present, check specific cheque instruments
                                        if ($readyForOracle && $receipt->cheques && $receipt->cheques->count() > 0) {
                                            foreach($receipt->cheques as $chk) {
                                                // If valid cheque exists (has amount or number) but no instrument
                                                if (($chk->cheque_amount > 0 || !empty($chk->cheque_no)) && empty($chk->instrument_id)) {
                                                    $readyForOracle = false;
                                                    break;
                                                }
                                            }
                                        }
                                    @endphp

                                    @if($receipt->oracle_receipt_number)
                                        <button type="button" id="enterToOracleBtn" class="btn btn-secondary" disabled>
                                            <i class="fas fa-check-circle"></i> Already Entered to Oracle (#{{ $receipt->oracle_receipt_number }})
                                        </button>
                                    @elseif(!$readyForOracle)
                                        <button type="button" class="btn btn-secondary" disabled title="Please update Instrument details (update receipt) before entering to Oracle">
                                            <i class="fas fa-cloud-upload-alt"></i> Enter to Oracle
                                        </button>
                                    @else
                                        <button type="button" id="enterToOracleBtn" class="btn btn-success">
                                            <i class="fas fa-cloud-upload-alt"></i> Enter to Oracle
                                        </button>
                                    @endif
                                </div>
                            </form>

                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Viewing Modal -->
        <div class="modal fade" id="imageViewModal" tabindex="-1" aria-labelledby="imageViewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageViewModalLabel">Cheque Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="modalImage" src="" alt="Cheque image" class="img-fluid" style="max-width: 100%; max-height: 70vh;">
                    </div>
                    <div class="modal-footer">
                        <a id="downloadImageBtn" href="" download class="btn btn-primary">
                            <i class="fas fa-download"></i> Download Image
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Auto-fill OU_ID when customer changes and filter banks accordingly
            function handleCustomerChange() {
                const customerSelect = document.getElementById('customer_id');
                const selectedOption = customerSelect.options[customerSelect.selectedIndex];
                const customerOuId = selectedOption.getAttribute('data-ou-id') || '';
                const ouInput = document.getElementById('ou_id');
                
                console.log('=== End Debug ===');
                
                // Set OU_ID from customer data (or fallback)
                if (customerOuId) {
                    ouInput.value = customerOuId;
                    
                    // Also auto-select this OU in the main bank filter if it exists
                    const mainOuFilter = document.querySelector('.ou-filter-select[data-target="bank_account_id"]');
                    if (mainOuFilter) {
                        mainOuFilter.value = customerOuId;
                        // Trigger change to filter banks
                        handleOuFilterChange({ target: mainOuFilter });
                    }
                } else {
                    ouInput.value = '';
                }
            }
            
            // Handle OU Filter Change
            function handleOuFilterChange(event) {
                const select = event.target;
                const targetId = select.getAttribute('data-target');
                const selectedOu = select.value;
                const targetSelect = document.getElementById(targetId);
                
                if (!targetSelect) return;
                
                const options = targetSelect.querySelectorAll('option');
                let firstVisible = null;
                
                options.forEach(option => {
                    if (option.value === '') return; // Skip placeholder
                    
                    const optionOu = option.getAttribute('data-ou-id') || option.getAttribute('data-org-id');
                    
                    if (!selectedOu || optionOu == selectedOu) {
                        option.style.display = '';
                        if (!firstVisible) firstVisible = option;
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Reset selection if current selection is hidden
                if (targetSelect.value) {
                    const currentOption = targetSelect.querySelector(`option[value="${targetSelect.value}"]`);
                    if (currentOption && currentOption.style.display === 'none') {
                        targetSelect.value = '';
                    }
                }
            }
            
            // Attach event delegation for OU filters (static and dynamic)
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('ou-filter-select')) {
                    handleOuFilterChange(e);
                }
            });
            
            // Attach event listener
            document.getElementById('customer_id').addEventListener('change', handleCustomerChange);
            
            // Initialize OU_ID and bank filtering on page load if customer is already selected
            document.addEventListener('DOMContentLoaded', function() {
                const customerSelect = document.getElementById('customer_id');
                if (customerSelect.value) {
                    // Trigger customer change event to set OU_ID and filter banks
                    handleCustomerChange();
                }
            });

            // Validate Oracle required fields for form submission
            document.querySelector('form').addEventListener('submit', function(e) {
                const ouInputEl = document.getElementById('ou_id');
                const customerIdEl = document.getElementById('customer_id');
                const bankSelectEl = document.getElementById('bank_account_id');
                const receiptAmountEl = document.getElementById('receipt_amount');
                const receiptDateEl = document.getElementById('receipt_date');
                
                const ouInput = ouInputEl;
                const customerId = customerIdEl ? customerIdEl.value : '';
                const bankSelect = bankSelectEl;
                const receiptAmount = receiptAmountEl ? parseFloat(receiptAmountEl.value) || 0 : 0;
                const receiptDate = receiptDateEl ? receiptDateEl.value : '';

                // Always validate customer selection first
                if (!customerId) {
                    e.preventDefault();
                    alert('Customer selection is required.');
                    return false;
                }

                // Check if Oracle integration is available (if we have Oracle banks)
                const oracleAvailable = bankSelect && bankSelect.options.length > 1 && 
                                    !bankSelect.options[1].disabled;

                // Validate Oracle required fields only if Oracle is available
                if (oracleAvailable) {
                    if (!ouInput || !ouInput.value) {
                        e.preventDefault();
                        alert('Operating Unit ID is required. Please select a customer first.');
                        return false;
                    }

                    if (!bankSelect || !bankSelect.value) {
                        e.preventDefault();
                        alert('Bank Account selection is required for Oracle integration.');
                        return false;
                    }

                    if (receiptAmount <= 0) {
                        e.preventDefault();
                        alert('Receipt Amount must be greater than 0.');
                        return false;
                    }

                    if (!receiptDate) {
                        e.preventDefault();
                        alert('Receipt Date is required for Oracle integration.');
                        return false;
                    }
                }

                // Validate payment methods (multi-cheque system)
                const cashAmountEl = document.getElementById('cash_amount');
                const cashAmount = cashAmountEl ? parseFloat(cashAmountEl.value) || 0 : 0;
                
                // Check if any cheques have number but no amount
                const chequeValidationFailed = Array.from(document.querySelectorAll('input[name*="[cheque_no]"]')).some((chequeNoInput, index) => {
                    const chequeAmountInput = document.querySelector(`input[name*="[cheque_amount]"]:nth-of-type(${index + 1})`);
                    const chequeNo = chequeNoInput.value.trim();
                    const chequeAmount = chequeAmountInput ? parseFloat(chequeAmountInput.value) || 0 : 0;
                    
                    return chequeNo && chequeAmount <= 0;
                });

                if (chequeValidationFailed) {
                    e.preventDefault();
                    alert('Cheque amount is required when cheque number is provided.');
                    return false;
                }
            });

            // Function to validate Oracle requirements
            function validateOracleRequirements() {
                const customerIdEl = document.getElementById('customer_id');
                const bankSelectEl = document.getElementById('bank_account_id');
                const ouInputEl = document.getElementById('ou_id');
                const receiptAmountEl = document.getElementById('receipt_amount');
                const receiptDateEl = document.getElementById('receipt_date');
                
                const customerId = customerIdEl ? customerIdEl.value : '';
                const bankSelect = bankSelectEl;
                const ouInput = ouInputEl;
                const receiptAmount = receiptAmountEl ? parseFloat(receiptAmountEl.value) || 0 : 0;
                const receiptDate = receiptDateEl ? receiptDateEl.value : '';

                // Validate customer selection
                if (!customerId) {
                    alert('Please select a customer before entering to Oracle.');
                    if (customerIdEl) customerIdEl.focus();
                    return false;
                }

                // Validate OU ID
                if (!ouInput || !ouInput.value) {
                    alert('Operating Unit ID is required. Please select a customer first.');
                    if (customerIdEl) customerIdEl.focus();
                    return false;
                }

                // Validate bank selection
                if (!bankSelect || !bankSelect.value) {
                    alert('Bank Account selection is required for Oracle integration. Please select a bank account.');
                    if (bankSelect) bankSelect.focus();
                    return false;
                }

                // Validate receipt amount
                if (receiptAmount <= 0) {
                    alert('Receipt Amount must be greater than 0.');
                    if (receiptAmountEl) receiptAmountEl.focus();
                    return false;
                }

                // Validate receipt date
                if (!receiptDate) {
                    alert('Receipt Date is required for Oracle integration.');
                    if (receiptDateEl) receiptDateEl.focus();
                    return false;
                }

                return true;
            }

            // Enter to Oracle button functionality
            document.getElementById('enterToOracleBtn').addEventListener('click', function() {
                // Check if button is disabled (already entered to Oracle)
                if (this.disabled) {
                    alert('This receipt has already been entered to Oracle.');
                    return;
                }

                // Validate all required fields
                if (!validateOracleRequirements()) {
                    return;
                }

                // Confirm Oracle submission
                const customerId = document.getElementById('customer_id').value;
                const bankSelect = document.getElementById('bank_account_id');
                const bankText = bankSelect.options[bankSelect.selectedIndex].text;
                const receiptAmount = document.getElementById('receipt_amount').value;

                if (!confirm(`Are you sure you want to enter this receipt to Oracle?\n\nCustomer: ${customerId}\nBank: ${bankText}\nAmount: ${receiptAmount}`)) {
                    return;
                }

                // Disable button and show loading
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entering to Oracle...';

                // Prepare data for Oracle submission
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('receipt_id', {{ $receipt->id }});
                
                // Explicitly add the required fields to ensure they're captured
                const customerIdEl = document.getElementById('customer_id');
                const ouIdEl = document.getElementById('ou_id');
                const bankAccountIdEl = document.getElementById('bank_account_id');
                const receiptAmountEl = document.getElementById('receipt_amount');
                const receiptDateEl = document.getElementById('receipt_date');
                const currencyEl = document.getElementById('currency');
                const descriptionEl = document.getElementById('description');
                const cashAmountEl = document.getElementById('cash_amount');
                
                formData.append('customer_id', customerIdEl ? customerIdEl.value : '');
                formData.append('ou_id', ouIdEl ? ouIdEl.value : '');
                formData.append('bank_account_id', bankAccountIdEl ? bankAccountIdEl.value : '');
                formData.append('receipt_amount', receiptAmountEl ? receiptAmountEl.value : '');
                formData.append('receipt_date', receiptDateEl ? receiptDateEl.value : '');
                formData.append('currency', currencyEl ? currencyEl.value : '');
                formData.append('comments', descriptionEl ? descriptionEl.value : '');
                formData.append('description', descriptionEl ? descriptionEl.value : '');
                formData.append('cash_amount', cashAmountEl ? cashAmountEl.value : '');
                
                // Add cheque data from the new multi-cheque system
                const chequeNos = [];
                const chequeAmounts = [];
                const chequeMaturityDates = []; // New array for maturity dates

                document.querySelectorAll('input[name*="[cheque_no]"]').forEach(input => {
                    if (input.value.trim()) chequeNos.push(input.value.trim());
                });
                document.querySelectorAll('input[name*="[cheque_amount]"]').forEach(input => {
                    if (input.value && parseFloat(input.value) > 0) chequeAmounts.push(input.value);
                });
                document.querySelectorAll('input[name*="[maturity_date]"]').forEach(input => {
                    if (input.value) chequeMaturityDates.push(input.value); 
                });
                
                formData.append('cheque_no', chequeNos.join(', ') || '');
                formData.append('cheque_amount', chequeAmounts.reduce((sum, amount) => sum + parseFloat(amount), 0) || '');
                formData.append('cheque_maturity_date', chequeMaturityDates.join(', ') || ''); // Append maturity dates
                
                // Debug: Log what we're sending
                console.log('Sending to Oracle:', {
                    customer_id: formData.get('customer_id'),
                    ou_id: formData.get('ou_id'),
                    bank_account_id: formData.get('bank_account_id'),
                    receipt_amount: formData.get('receipt_amount'),
                    receipt_date: formData.get('receipt_date'),
                    cheque_maturity_dates: formData.get('cheque_maturity_date')
                });

                // Submit to Oracle
                fetch('{{ route("reciepts.enter-to-oracle", $receipt->id) }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Receipt successfully entered to Oracle!');
                        
                        // Update button state to disabled/already entered
                        btn.disabled = true;
                        btn.className = 'btn btn-secondary';
                        btn.innerHTML = `<i class="fas fa-check-circle"></i> Already Entered to Oracle (#${data.oracle_receipt_number})`;
                        
                        // Optionally redirect or refresh after a delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert(data.message || 'Failed to enter receipt to Oracle. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while entering to Oracle. Please try again.');
                })
                .finally(() => {
                    // Re-enable button
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });

            // Handle multiple cheques functionality
            let chequeIndex = {{ $receipt->cheques->count() > 0 ? $receipt->cheques->count() : 1 }};
            
            // Add new cheque
            document.getElementById('addChequeBtn').addEventListener('click', function() {
                const chequesContainer = document.getElementById('chequesContainer');
                const newChequeHtml = `
                    <div class="cheque-item border rounded p-3 mb-3" data-cheque-index="${chequeIndex}">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Cheque #${chequeIndex + 1}</h6>
                            <button type="button" class="btn btn-sm btn-danger remove-cheque-btn">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Cheque Number</label>
                                <input type="text" name="cheques[${chequeIndex}][cheque_no]" class="form-control" placeholder="Enter cheque number">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cheque Amount</label>
                                <input type="number" name="cheques[${chequeIndex}][cheque_amount]" class="form-control receipt-amount-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="cheques[${chequeIndex}][bank_name]" class="form-control bank-name-input" placeholder="Enter bank name">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label class="form-label">Filter OU</label>
                                <select class="form-select ou-filter-select" data-target="cheques-${chequeIndex}-instrument">
                                    <option value="">All OUs</option>
                                    @foreach($operatingUnits as $ou)
                                        <option value="{{ $ou }}">{{ $ou }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Instrument *</label>
                                <select name="cheques[${chequeIndex}][instrument_id]" id="cheques-${chequeIndex}-instrument" class="form-select instrument-select" required>
                                    <option value="">Select Instrument</option>
                                    @forelse($oracleBanks ?? [] as $bank)
                                        <option value="{{ $bank->bank_account_id }}" 
                                            data-ou-id="{{ $bank->ou_id }}"
                                            data-bank-name="{{ $bank->bank_name }}"
                                            data-account-name="{{ $bank->bank_account_name }}"
                                            data-account-num="{{ $bank->bank_account_num }}"
                                            data-org-id="{{ $bank->org_id }}"
                                            data-debug-info="Bank: {{ $bank->bank_name }} | OU: {{ $bank->ou_id }} | Account: {{ $bank->bank_account_id }}">
                                            {{ \Illuminate\Support\Str::limit($bank->bank_name, 25) }} - {{ \Illuminate\Support\Str::limit($bank->bank_account_name, 20) }} - {{ substr($bank->bank_account_num, -4) }} ({{ $bank->bank_account_id }})
                                        </option>
                                    @empty
                                        <option value="" disabled>No banks available</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cheque Date *</label>
                                <input type="date" name="cheques[${chequeIndex}][cheque_date]" class="form-control cheque-amount-input" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maturity Date *</label>
                                <input type="date" name="cheques[${chequeIndex}][maturity_date]" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Cheque Comments</label>
                                <textarea name="cheques[${chequeIndex}][comments]" class="form-control" rows="2" placeholder="Enter comments about the cheque"></textarea>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="cheques[${chequeIndex}][is_third_party_cheque]" class="form-check-input" value="1">
                                    <label class="form-check-label">Third Party Cheque</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label">Cheque Images</label>
                                <input type="file" name="cheques[${chequeIndex}][cheque_images][]" class="form-control" accept="image/*" multiple>
                            </div>
                        </div>
                        
                        <!-- Hidden fields for instrument data -->
                        <input type="hidden" name="cheques[${chequeIndex}][instrument_name]" value="">
                        <input type="hidden" name="cheques[${chequeIndex}][instrument_account_name]" value="">
                        <input type="hidden" name="cheques[${chequeIndex}][instrument_account_num]" value="">
                        <input type="hidden" name="cheques[${chequeIndex}][org_id]" value="">
                    </div>
                `;
                
                chequesContainer.insertAdjacentHTML('beforeend', newChequeHtml);
                chequeIndex++;
                
                // Re-bind event listeners for new elements
                bindInstrumentSelectors();
                bindRemoveButtons();
                bindAmountCalculation();
            });
            
            // Remove cheque functionality
            function bindRemoveButtons() {
                document.querySelectorAll('.remove-cheque-btn').forEach(btn => {
                    btn.replaceWith(btn.cloneNode(true)); // Remove existing listeners
                });
                
                document.querySelectorAll('.remove-cheque-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        this.closest('.cheque-item').remove();
                        updateChequeNumbers();
                        calculateTotalReceiptAmount(); // Recalculate after removal
                    });
                });
            }
            
            // Update cheque numbers after removal
            function updateChequeNumbers() {
                document.querySelectorAll('.cheque-item').forEach((item, index) => {
                    const title = item.querySelector('h6');
                    if (title) {
                        title.textContent = `Cheque #${index + 1}`;
                    }
                });
            }
            
            // Handle instrument selection for all cheques
            function bindInstrumentSelectors() {
                document.querySelectorAll('.instrument-select').forEach(select => {
                    select.replaceWith(select.cloneNode(true)); // Remove existing listeners
                });
                
                document.querySelectorAll('.instrument-select').forEach(select => {
                    select.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const chequeItem = this.closest('.cheque-item');
                        
                        if (selectedOption && selectedOption.value && chequeItem) {
                            // Update hidden fields with instrument data (do NOT update bank name field)
                            const instrumentData = {
                                instrument_name: selectedOption.getAttribute('data-bank-name'),
                                instrument_account_name: selectedOption.getAttribute('data-account-name'),
                                instrument_account_num: selectedOption.getAttribute('data-account-num'),
                                org_id: selectedOption.getAttribute('data-org-id')
                            };
                            
                            Object.entries(instrumentData).forEach(([key, value]) => {
                                const hiddenField = chequeItem.querySelector(`input[name*="[${key}]"]`);
                                if (hiddenField && value) {
                                    hiddenField.value = value;
                                }
                            });
                        }
                    });
                });
            }
            
            // Initialize event listeners
            bindRemoveButtons();
            bindInstrumentSelectors();
            
            // Auto-calculate total receipt amount
            function calculateTotalReceiptAmount() {
                let total = 0;
                document.querySelectorAll('.receipt-amount-input').forEach(input => {
                    const val = parseFloat(input.value);
                    if (!isNaN(val)) total += val;
                });
                
                const totalEl = document.getElementById('receipt_amount');
                if (totalEl) totalEl.value = total.toFixed(2);
            }

            // Use event delegation involved for robust handling of dynamic inputs
            document.addEventListener('input', function(e) {
                if (e.target && e.target.classList.contains('receipt-amount-input')) {
                    calculateTotalReceiptAmount();
                }
            });

            // Calculate on page load
            calculateTotalReceiptAmount();
            
            // Deprecated bind function (kept empty to prevent errors if called elsewhere)
            function bindAmountCalculation() {}
            
            // Sync description with comments field for backend compatibility
            document.getElementById('description').addEventListener('input', function() {
                document.getElementById('hidden_comments').value = this.value;
            });
            
            // Image viewing function
            function viewImage(imageSrc, imageTitle) {
                const modal = document.getElementById('imageViewModal');
                const modalImage = document.getElementById('modalImage');
                const modalTitle = document.getElementById('imageViewModalLabel');
                const downloadBtn = document.getElementById('downloadImageBtn');
                
                // Set image source and title
                modalImage.src = imageSrc;
                modalTitle.textContent = imageTitle;
                downloadBtn.href = imageSrc;
                
                // Extract filename for download
                const filename = imageSrc.split('/').pop() || 'cheque_image';
                downloadBtn.download = filename;
                
                // Show modal
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }

            // Handle instrument selection for new receipt form
            const newReceiptInstrumentSelect = document.getElementById('new_bank_account_id');
            if (newReceiptInstrumentSelect) {
                newReceiptInstrumentSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        // Update hidden OU field if exists
                        const ouInput = document.querySelector('input[name="ou_id"]');
                        if (ouInput) {
                            ouInput.value = selectedOption.getAttribute('data-ou-id') || '';
                        }
                    }
                });
            }

            // Handle cheque instrument selection in new receipt form
            document.querySelectorAll('#newReceiptForm select[name*="instrument_id"]').forEach(select => {
                select.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        // Find the hidden fields in the same row/section
                        const baseNamePrefix = this.name.replace('[instrument_id]', '');
                        
                        // Update hidden instrument fields
                        const instrumentData = {
                            instrument_name: selectedOption.getAttribute('data-bank-name'),
                            instrument_account_name: selectedOption.getAttribute('data-account-name'),
                            instrument_account_num: selectedOption.getAttribute('data-account-num'),
                            org_id: selectedOption.getAttribute('data-org-id')
                        };
                        
                        Object.entries(instrumentData).forEach(([key, value]) => {
                            const hiddenField = document.querySelector(`input[name="${baseNamePrefix}[${key}]"]`);
                            if (hiddenField && value) {
                                hiddenField.value = value;
                            }
                        });
                    }
                });
            });
        </script>

    </x-app-layout>