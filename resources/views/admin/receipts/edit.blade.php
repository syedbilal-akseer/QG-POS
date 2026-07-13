@push('title')
    Edit Receipt #{{ $receipt->receipt_number }}
@endpush

<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <style>
        .bank-row { cursor: pointer; transition: background .15s; }
        .bank-row:hover { background: #f0f4ff; }
        .bank-row.selected { background: #dbeafe; border-left: 3px solid #3b82f6; }
        .filter-list { max-height: 180px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: .375rem; }
        .filter-item { padding: 6px 10px; cursor: pointer; font-size: .875rem; border-bottom: 1px solid #f1f1f1; }
        .filter-item:hover { background: #f8f9fa; }
        .filter-item.active { background: #dbeafe; font-weight: 600; }
        .section-disabled { opacity: .45; pointer-events: none; }
        .detail-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .5rem; padding: 12px 16px; }
        .clickable-image { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .clickable-image:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    </style>

    {{-- MUST run before the x-data container below — chequeBankPicker reads
         these globals during Alpine init, so they have to exist first.
         Previously this lived after the form and pre-selection silently failed. --}}
    <script>
        window._receiptAllBanks = @json($oracleBanks);
        window._receiptOuId     = '{{ $receipt->customer?->ou_id ?? $receipt->ou_id ?? '' }}';
    </script>

    <div class="container mt-2"
         x-data="receiptEditForm()"
         x-init="init()">

        <div class="row justify-content-center">
            <div class="col-md-11">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Edit Receipt #{{ $receipt->receipt_number }}</h4>
                        <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}" class="btn btn-secondary btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to List
                        </a>
                    </div>

                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form id="receiptForm"
                              action="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.update', $receipt->id) : route('reciepts.update', $receipt->id) }}"
                              method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="">
                            <input type="hidden" name="creation_date" value="{{ date('Y-m-d H:i:s') }}">
                            <input type="hidden" name="comments" id="hidden_comments" value="{{ old('comments', $receipt->comments) }}">

                            {{-- ═══════════════════════════════════════════
                                 CARD 1 — CUSTOMER (locked)
                            ═══════════════════════════════════════════ --}}
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white py-2">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Customer</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Customer</label>
                                            {{-- Customer is locked on edit —— cannot change which customer a receipt belongs to --}}
                                            <input type="hidden" name="customer_id" value="{{ $receipt->customer_id }}">
                                            <input type="hidden" name="ou_id"       value="{{ $receipt->ou_id ?? $receipt->customer?->ou_id }}">
                                            <div class="form-control bg-light text-dark d-flex align-items-center gap-2">
                                                <i class="fas fa-lock text-secondary"></i>
                                                <strong>{{ $receipt->customer?->customer_name ?? $receipt->customer_id }}</strong>
                                                <span class="badge bg-secondary ms-auto">{{ $receipt->customer?->ou_name ?? $receipt->ou_id }}</span>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Operating Unit</label>
                                            <div class="form-control bg-light text-secondary">
                                                {{ $receipt->customer?->ou_name ?? $receipt->ou_id ?? '—' }}
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Credit Limit</label>
                                            <div class="form-control bg-light text-secondary">
                                                @if($receipt->customer?->overall_credit_limit)
                                                    PKR {{ number_format($receipt->customer->overall_credit_limit) }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="description" class="form-label fw-semibold">Comments <span class="text-danger">*</span></label>
                                            <textarea name="description" id="description" class="form-control" rows="3" required
                                                placeholder="Enter receipt description / comments">{{ old('description', $receipt->description) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ═══════════════════════════════════════════
                                 CARD 2 — RECEIPT DETAILS
                            ═══════════════════════════════════════════ --}}
                            <div class="card mb-3">
                                <div class="card-header bg-secondary text-white py-2">
                                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Receipt Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Receipt Number <span class="text-danger">*</span></label>
                                            <input type="text" name="receipt_number" class="form-control"
                                                value="{{ old('receipt_number', $receipt->receipt_number) }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Receipt Date <span class="text-danger">*</span></label>
                                            <input type="date" name="receipt_date" class="form-control"
                                                value="{{ old('receipt_date', $receipt->receipt_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                                            <input type="number" name="receipt_amount" id="receipt_amount" class="form-control"
                                                value="{{ old('receipt_amount', $receipt->receipt_amount) }}"
                                                step="0.01" min="0" required placeholder="0.00" readonly>
                                            <small class="text-muted">Auto-calculated</small>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label fw-semibold">Currency</label>
                                            <select name="currency" class="form-select">
                                                <option value="PKR" {{ old('currency', $receipt->currency) == 'PKR' ? 'selected' : '' }}>PKR</option>
                                                <option value="USD" {{ old('currency', $receipt->currency) == 'USD' ? 'selected' : '' }}>USD</option>
                                                <option value="EUR" {{ old('currency', $receipt->currency) == 'EUR' ? 'selected' : '' }}>EUR</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ═══════════════════════════════════════════
                                 CARD 3 — CASH PAYMENT (toggle)
                            ═══════════════════════════════════════════ --}}
                            <div class="card mb-3">
                                <div class="card-header py-2 d-flex align-items-center gap-3">
                                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2 text-success"></i>Cash Payment</h5>
                                    <div class="form-check form-switch mb-0 ms-auto">
                                        <input class="form-check-input" type="checkbox" id="hasCashToggle"
                                               x-model="hasCash" role="switch">
                                        <label class="form-check-label" for="hasCashToggle">
                                            <span x-text="hasCash ? 'Included' : 'Not included'"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body" :class="!hasCash ? 'section-disabled' : ''">
                                    <p x-show="!hasCash" class="text-muted mb-2">
                                        <i class="fas fa-info-circle"></i> Enable the toggle above to enter cash payment details.
                                    </p>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Cash Amount</label>
                                            <input type="number" name="cash_amount" id="cash_amount" class="form-control receipt-amount-input"
                                                value="{{ old('cash_amount', $receipt->cash_amount) }}" step="0.01" min="0"
                                                :disabled="!hasCash" placeholder="0.00">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Maturity Date</label>
                                            <input type="date" name="cash_maturity_date" class="form-control"
                                                value="{{ old('cash_maturity_date', $receipt->cash_maturity_date?->format('Y-m-d')) }}"
                                                :disabled="!hasCash">
                                        </div>
                                    </div>

                                    {{-- Bank / Instrument picker for Cash --}}
                                    <div x-show="hasCash">
                                        <hr class="my-2">
                                        <p class="fw-semibold mb-2"><i class="fas fa-university me-1 text-primary"></i>Bank / Instrument for Cash</p>
                                        @if($receipt->remittance_bank_name || $receipt->customer_bank_name)
                                            <div class="row g-3 mb-2">
                                                @if($receipt->remittance_bank_name)
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold text-muted" style="font-size:.8rem;"><i class="fas fa-mobile-alt me-1"></i>From mobile app (Remittance)</label>
                                                        <input type="text" class="form-control form-control-sm bg-light" value="{{ $receipt->remittance_bank_name }}" disabled>
                                                    </div>
                                                @endif
                                                @if($receipt->customer_bank_name)
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold text-muted" style="font-size:.8rem;"><i class="fas fa-mobile-alt me-1"></i>From mobile app (Customer Bank)</label>
                                                        <input type="text" class="form-control form-control-sm bg-light" value="{{ $receipt->customer_bank_name }}" disabled>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="row g-3 mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>1. Bank Name <span x-show="cSelBank" class="badge bg-primary ms-1" x-text="cSelBank"></span></label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="cBankQ" placeholder="Type to filter banks...">
                                                <div class="filter-list">
                                                    <div class="filter-item text-muted" style="font-style:italic" @click="cSelBank='';cSelInst='';cSelAcc=null" :class="!cSelBank?'active':''">— All Banks —</div>
                                                    <template x-for="name in cBankNames" :key="name">
                                                        <div class="filter-item" :class="cSelBank===name?'active':''" @click="selectCBank(name)" x-text="name"></div>
                                                    </template>
                                                    <div x-show="cBankNames.length===0" class="filter-item text-muted">No banks found</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>2. Instrument <span x-show="cSelInst" class="badge bg-info text-dark ms-1" x-text="cSelInst"></span></label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="cInstQ" placeholder="Type to filter instruments...">
                                                <div class="filter-list">
                                                    <div class="filter-item text-muted" style="font-style:italic" @click="cSelInst='';cSelAcc=null" :class="!cSelInst?'active':''">— All Instruments —</div>
                                                    <template x-for="inst in cInstruments" :key="inst">
                                                        <div class="filter-item" :class="cSelInst===inst?'active':''" @click="selectCInst(inst)" x-text="inst"></div>
                                                    </template>
                                                    <div x-show="cInstruments.length===0" class="filter-item text-muted">No instruments found</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>3. Account / Search <span x-show="cSelAcc" class="badge bg-success ms-1">Selected</span></label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="cAccQ" placeholder="Search account name or number...">
                                                <div class="filter-list">
                                                    <template x-for="acc in cAccounts" :key="acc.id || acc.bank_account_id + '-' + acc.receipt_method_id">
                                                        <div class="filter-item bank-row" :class="(cSelAcc?.bank_account_id===acc.bank_account_id && cSelAcc?.receipt_method_id===acc.receipt_method_id)?'selected':''" @click="selectCAcc(acc)">
                                                            <div class="fw-semibold" x-text="acc.bank_account_name||acc.bank_name"></div>
                                                            <div class="text-muted" style="font-size:.78rem" x-text="acc.bank_account_num"></div>
                                                        </div>
                                                    </template>
                                                    <div x-show="cAccounts.length===0" class="filter-item text-muted">No accounts match</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div x-show="cSelAcc" class="detail-card mt-1">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-auto"><i class="fas fa-check-circle text-success fa-lg"></i></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">BANK</div><div class="fw-semibold" x-text="cSelAcc?.bank_name"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">INSTRUMENT</div><div x-text="cSelAcc?.receipt_method"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NAME</div><div x-text="cSelAcc?.bank_account_name"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NO.</div><div class="font-monospace" x-text="cSelAcc?.bank_account_num"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">IBAN</div><div class="font-monospace" style="font-size:.8rem" x-text="cSelAcc?.iban_number||'—'"></div></div>
                                                <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="clearCAcc()"><i class="fas fa-times"></i></button></div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="cash_bank_account_id" :value="cSelAcc?.bank_account_id??''">
                                        <input type="hidden" name="cash_receipt_method_id" :value="cSelAcc?.receipt_method_id??''">
                                    </div>
                                </div>
                            </div>

                            {{-- ═══════════════════════════════════════════
                                 CARD 5 — CHEQUE DETAILS (multi, toggle)
                            ═══════════════════════════════════════════ --}}
                            <div class="card mb-3">
                                <div class="card-header py-2 d-flex align-items-center gap-3">
                                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2 text-warning"></i>Cheque Details</h5>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="hasChequeToggle"
                                               x-model="hasCheque"
                                               role="switch">
                                        <label class="form-check-label" for="hasChequeToggle">
                                            <span x-text="hasCheque ? 'Included' : 'Not included'"></span>
                                        </label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary ms-auto" id="addChequeBtn"
                                            x-show="hasCheque">
                                        <i class="fas fa-plus"></i> Add Cheque
                                    </button>
                                </div>
                                <div class="card-body" :class="!hasCheque ? 'section-disabled' : ''">
                                    <p x-show="!hasCheque" class="text-muted mb-3">
                                        <i class="fas fa-info-circle"></i> Enable the toggle above to enter cheque details.
                                    </p>

                                    <fieldset :disabled="!hasCheque" style="border:none;padding:0;margin:0;">
                                    <div id="chequesContainer">
                                        @if($receipt->cheques && $receipt->cheques->count() > 0)
                                            @foreach($receipt->cheques as $index => $cheque)
                                                {{-- Each cheque gets its own Alpine.js bank picker scope --}}
                                                <div class="cheque-item border rounded p-3 mb-3"
                                                     data-cheque-index="{{ $index }}"
                                                     x-data="chequeBankPicker({{ old("cheques.{$index}.instrument_id", $cheque->instrument_id ?? 0) }}, {{ old("cheques.{$index}.receipt_method_id", $cheque->receipt_method_id ?? 0) }})">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="mb-0">Cheque #{{ $index + 1 }}</h6>
                                                        @if($index > 0)
                                                            <button type="button" class="btn btn-sm btn-danger remove-cheque-btn">
                                                                <i class="fas fa-trash"></i> Remove
                                                            </button>
                                                        @endif
                                                    </div>

                                                    {{-- 3-column bank picker --}}
                                                    <p class="fw-semibold mb-2"><i class="fas fa-university me-1 text-primary"></i>Bank / Instrument</p>
                                                    @if($cheque->bank_name)
                                                        <div class="row g-3 mb-2">
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold text-muted" style="font-size:.8rem;"><i class="fas fa-mobile-alt me-1"></i>From mobile app (Bank)</label>
                                                                <input type="text" class="form-control form-control-sm bg-light" value="{{ $cheque->bank_name }}" disabled>
                                                            </div>
                                                            @if($cheque->instrument_name)
                                                                <div class="col-md-4">
                                                                    <label class="form-label fw-semibold text-muted" style="font-size:.8rem;"><i class="fas fa-mobile-alt me-1"></i>From mobile app (Instrument)</label>
                                                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $cheque->instrument_name }}" disabled>
                                                                </div>
                                                            @endif
                                                            @if($cheque->instrument_account_name)
                                                                <div class="col-md-4">
                                                                    <label class="form-label fw-semibold text-muted" style="font-size:.8rem;"><i class="fas fa-mobile-alt me-1"></i>From mobile app (Account)</label>
                                                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $cheque->instrument_account_name }}" disabled>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div class="row g-3 mb-2">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>1. Bank Name <span x-show="selBank" class="badge bg-primary ms-1" x-text="selBank"></span></label>
                                                            <input type="text" class="form-control form-control-sm mb-1" x-model="bankQ" placeholder="Type to filter banks...">
                                                            <div class="filter-list">
                                                                <div class="filter-item text-muted" style="font-style:italic" @click="selBank='';selInst='';selAcc=null" :class="!selBank?'active':''">— All Banks —</div>
                                                                <template x-for="name in bankNames" :key="name">
                                                                    <div class="filter-item" :class="selBank===name?'active':''" @click="pickBank(name)" x-text="name"></div>
                                                                </template>
                                                                <div x-show="bankNames.length===0" class="filter-item text-muted">No banks found</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>2. Instrument <span x-show="selInst" class="badge bg-info text-dark ms-1" x-text="selInst"></span></label>
                                                            <input type="text" class="form-control form-control-sm mb-1" x-model="instQ" placeholder="Type to filter instruments...">
                                                            <div class="filter-list">
                                                                <div class="filter-item text-muted" style="font-style:italic" @click="selInst='';selAcc=null" :class="!selInst?'active':''">— All Instruments —</div>
                                                                <template x-for="inst in instruments" :key="inst">
                                                                    <div class="filter-item" :class="selInst===inst?'active':''" @click="pickInst(inst)" x-text="inst"></div>
                                                                </template>
                                                                <div x-show="instruments.length===0" class="filter-item text-muted">No instruments found</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>3. Account / Search <span x-show="selAcc" class="badge bg-success ms-1">Selected</span></label>
                                                            <input type="text" class="form-control form-control-sm mb-1" x-model="accQ" placeholder="Search account name or number...">
                                                            <div class="filter-list">
                                                                <template x-for="acc in accounts" :key="acc.id || acc.bank_account_id + '-' + acc.receipt_method_id">
                                                                    <div class="filter-item bank-row" :class="(selAcc?.bank_account_id===acc.bank_account_id && selAcc?.receipt_method_id===acc.receipt_method_id)?'selected':''" @click="pickAcc(acc)">
                                                                        <div class="fw-semibold" x-text="acc.bank_account_name||acc.bank_name"></div>
                                                                        <div class="text-muted" style="font-size:.78rem" x-text="acc.bank_account_num"></div>
                                                                    </div>
                                                                </template>
                                                                <div x-show="accounts.length===0" class="filter-item text-muted">No accounts match</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div x-show="selAcc" class="detail-card mt-1 mb-3">
                                                        <div class="row g-2 align-items-center">
                                                            <div class="col-auto"><i class="fas fa-check-circle text-success fa-lg"></i></div>
                                                            <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">BANK</div><div class="fw-semibold" x-text="selAcc?.bank_name"></div></div>
                                                            <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">INSTRUMENT</div><div x-text="selAcc?.receipt_method"></div></div>
                                                            <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NAME</div><div x-text="selAcc?.bank_account_name"></div></div>
                                                            <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NO.</div><div class="font-monospace" x-text="selAcc?.bank_account_num"></div></div>
                                                            <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">IBAN</div><div class="font-monospace" style="font-size:.8rem" x-text="selAcc?.iban_number||'—'"></div></div>
                                                            <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="selAcc=null"><i class="fas fa-times"></i></button></div>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="cheques[{{ $index }}][instrument_id]" :value="selAcc?.bank_account_id??''">
                                                    <input type="hidden" name="cheques[{{ $index }}][receipt_method_id]" :value="selAcc?.receipt_method_id??''">
                                                    <hr class="my-3">

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-3">
                                                            <label class="form-label fw-semibold">Cheque Number</label>
                                                            <input type="text" name="cheques[{{ $index }}][cheque_no]" class="form-control"
                                                                value="{{ old("cheques.{$index}.cheque_no", $cheque->cheque_no) }}" placeholder="Enter cheque number">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label fw-semibold">Cheque Amount</label>
                                                            <input type="number" name="cheques[{{ $index }}][cheque_amount]" class="form-control receipt-amount-input"
                                                                step="0.01" min="0" value="{{ old("cheques.{$index}.cheque_amount", $cheque->cheque_amount) }}" placeholder="0.00">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label fw-semibold">Cheque Date <span class="text-danger">*</span></label>
                                                            <input type="date" name="cheques[{{ $index }}][cheque_date]" class="form-control"
                                                                value="{{ old("cheques.{$index}.cheque_date", $cheque->cheque_date?->format('Y-m-d')) }}" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label fw-semibold">Maturity Date <span class="text-danger">*</span></label>
                                                            <input type="date" name="cheques[{{ $index }}][maturity_date]" class="form-control"
                                                                value="{{ old("cheques.{$index}.maturity_date", $cheque->maturity_date?->format('Y-m-d')) }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-8">
                                                            <label class="form-label fw-semibold">Cheque Comments</label>
                                                            <textarea name="cheques[{{ $index }}][comments]" class="form-control" rows="2"
                                                                placeholder="Optional comments">{{ old("cheques.{$index}.comments", $cheque->comments) }}</textarea>
                                                        </div>
                                                        <div class="col-md-2 d-flex align-items-end">
                                                            <div class="form-check mb-2">
                                                                <input type="checkbox" name="cheques[{{ $index }}][is_third_party_cheque]"
                                                                       class="form-check-input" value="1"
                                                                       {{ old("cheques.{$index}.is_third_party_cheque", $cheque->is_third_party_cheque) ? 'checked' : '' }}>
                                                                <label class="form-check-label">Third-party</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Cheque Images</label>
                                                            <input type="file" name="cheques[{{ $index }}][cheque_images][]" class="form-control" accept="image/*" multiple>
                                                            @if($cheque->cheque_images && is_array($cheque->cheque_images))
                                                                <div class="mt-2">
                                                                    <small class="text-muted">Current images:</small><br>
                                                                    @foreach($cheque->cheque_images as $imgIdx => $image)
                                                                        <img src="{{ $image }}" alt="Cheque image"
                                                                             class="img-thumbnail me-2 clickable-image"
                                                                             style="max-height:100px;cursor:pointer;"
                                                                             onclick="viewImage('{{ $image }}', 'Cheque #{{ $index + 1 }} - Image {{ $imgIdx + 1 }}')">
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="cheques[{{ $index }}][instrument_name]"         :value="selAcc?.bank_name??'{{ $cheque->instrument_name }}'">
                                                    <input type="hidden" name="cheques[{{ $index }}][instrument_account_name]" :value="selAcc?.bank_account_name??'{{ $cheque->instrument_account_name }}'">
                                                    <input type="hidden" name="cheques[{{ $index }}][instrument_account_num]"  :value="selAcc?.bank_account_num??'{{ $cheque->instrument_account_num }}'">
                                                    <input type="hidden" name="cheques[{{ $index }}][org_id]"                  :value="selAcc?.org_id??'{{ $cheque->org_id }}'">
                                                </div>
                                            @endforeach
                                        @else
                                            {{-- Empty cheque row --}}
                                            <div class="cheque-item border rounded p-3 mb-3" data-cheque-index="0"
                                                 x-data="chequeBankPicker(0, 0)">
                                                <h6 class="mb-3">Cheque #1</h6>
                                                <p class="fw-semibold mb-2"><i class="fas fa-university me-1 text-primary"></i>Bank / Instrument</p>
                                                {{-- No existing cheque data — no mobile reference to show --}}
                                                <div class="row g-3 mb-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>1. Bank Name <span x-show="selBank" class="badge bg-primary ms-1" x-text="selBank"></span></label>
                                                        <input type="text" class="form-control form-control-sm mb-1" x-model="bankQ" placeholder="Type to filter banks...">
                                                        <div class="filter-list">
                                                            <div class="filter-item text-muted" style="font-style:italic" @click="selBank='';selInst='';selAcc=null" :class="!selBank?'active':''">— All Banks —</div>
                                                            <template x-for="name in bankNames" :key="name"><div class="filter-item" :class="selBank===name?'active':''" @click="pickBank(name)" x-text="name"></div></template>
                                                            <div x-show="bankNames.length===0" class="filter-item text-muted">No banks found</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>2. Instrument <span x-show="selInst" class="badge bg-info text-dark ms-1" x-text="selInst"></span></label>
                                                        <input type="text" class="form-control form-control-sm mb-1" x-model="instQ" placeholder="Type to filter instruments...">
                                                        <div class="filter-list">
                                                            <div class="filter-item text-muted" style="font-style:italic" @click="selInst='';selAcc=null" :class="!selInst?'active':''">— All Instruments —</div>
                                                            <template x-for="inst in instruments" :key="inst"><div class="filter-item" :class="selInst===inst?'active':''" @click="pickInst(inst)" x-text="inst"></div></template>
                                                            <div x-show="instruments.length===0" class="filter-item text-muted">No instruments found</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>3. Account / Search <span x-show="selAcc" class="badge bg-success ms-1">Selected</span></label>
                                                        <input type="text" class="form-control form-control-sm mb-1" x-model="accQ" placeholder="Search account name or number...">
                                                        <div class="filter-list">
                                                            <template x-for="acc in accounts" :key="acc.id || acc.bank_account_id + '-' + acc.receipt_method_id">
                                                                <div class="filter-item bank-row" :class="(selAcc?.bank_account_id===acc.bank_account_id && selAcc?.receipt_method_id===acc.receipt_method_id)?'selected':''" @click="pickAcc(acc)">
                                                                    <div class="fw-semibold" x-text="acc.bank_account_name||acc.bank_name"></div>
                                                                    <div class="text-muted" style="font-size:.78rem" x-text="acc.bank_account_num"></div>
                                                                </div>
                                                            </template>
                                                            <div x-show="accounts.length===0" class="filter-item text-muted">No accounts match</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div x-show="selAcc" class="detail-card mt-1 mb-3">
                                                    <div class="row g-2 align-items-center">
                                                        <div class="col-auto"><i class="fas fa-check-circle text-success fa-lg"></i></div>
                                                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">BANK</div><div class="fw-semibold" x-text="selAcc?.bank_name"></div></div>
                                                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">INSTRUMENT</div><div x-text="selAcc?.receipt_method"></div></div>
                                                        <div class="col-md-3"><div class="text-muted" style="font-size:.75rem">ACCOUNT NAME</div><div x-text="selAcc?.bank_account_name"></div></div>
                                                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NO.</div><div class="font-monospace" x-text="selAcc?.bank_account_num"></div></div>
                                                        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="selAcc=null"><i class="fas fa-times"></i></button></div>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="cheques[0][instrument_id]" :value="selAcc?.bank_account_id??''">
                                                <hr class="my-3">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-3"><label class="form-label fw-semibold">Cheque Number</label><input type="text" name="cheques[0][cheque_no]" class="form-control" placeholder="Enter cheque number"></div>
                                                    <div class="col-md-3"><label class="form-label fw-semibold">Cheque Amount</label><input type="number" name="cheques[0][cheque_amount]" class="form-control receipt-amount-input" step="0.01" min="0" placeholder="0.00"></div>
                                                    <div class="col-md-3"><label class="form-label fw-semibold">Cheque Date <span class="text-danger">*</span></label><input type="date" name="cheques[0][cheque_date]" class="form-control" required></div>
                                                    <div class="col-md-3"><label class="form-label fw-semibold">Maturity Date <span class="text-danger">*</span></label><input type="date" name="cheques[0][maturity_date]" class="form-control" required></div>
                                                </div>
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-8"><label class="form-label fw-semibold">Cheque Comments</label><textarea name="cheques[0][comments]" class="form-control" rows="2" placeholder="Optional comments"></textarea></div>
                                                    <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" name="cheques[0][is_third_party_cheque]" class="form-check-input" value="1"><label class="form-check-label">Third-party</label></div></div>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label fw-semibold">Cheque Images</label><input type="file" name="cheques[0][cheque_images][]" class="form-control" accept="image/*" multiple></div>
                                                </div>
                                                <input type="hidden" name="cheques[0][instrument_name]" :value="selAcc?.bank_name??''">
                                                <input type="hidden" name="cheques[0][instrument_account_name]" :value="selAcc?.bank_account_name??''">
                                                <input type="hidden" name="cheques[0][instrument_account_num]" :value="selAcc?.bank_account_num??''">
                                                <input type="hidden" name="cheques[0][receipt_method_id]" :value="selAcc?.receipt_method_id??''">
                                                <input type="hidden" name="cheques[0][org_id]" :value="selAcc?.org_id??''">
                                            </div>
                                        @endif
                                    </div>
                                    </fieldset>
                                </div>
                            </div>

                            {{-- Submit --}}
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}"
                                   class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save me-1"></i> Update Receipt
                                </button>

                                @php
                                    $hasCash    = ($receipt->cash_amount ?? 0) > 0;
                                    $hasCheques = $receipt->cheques && $receipt->cheques->count() > 0;

                                    // Cash requires a saved bank_account_id
                                    $cashReady = !$hasCash || !empty($receipt->bank_account_id);

                                    // Every cheque with data must have an instrument selected
                                    $chequesReady = true;
                                    if ($hasCheques) {
                                        foreach ($receipt->cheques as $chk) {
                                            if (($chk->cheque_amount > 0 || !empty($chk->cheque_no)) && empty($chk->instrument_id)) {
                                                $chequesReady = false;
                                                break;
                                            }
                                        }
                                    }

                                    // Must have at least one payment type and all present types must be ready
                                    $readyForOracle = ($hasCash || $hasCheques) && $cashReady && $chequesReady;
                                @endphp

                                @if($receipt->oracle_receipt_number)
                                    <button type="button" class="btn btn-secondary" disabled>
                                        <i class="fas fa-check-circle"></i> Already Entered to Oracle (#{{ $receipt->oracle_receipt_number }})
                                    </button>
                                @elseif(!$readyForOracle)
                                    <button type="button" class="btn btn-secondary" disabled
                                            title="Please save Instrument details before entering to Oracle">
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

    {{-- Image Viewing Modal --}}
    <div class="modal fade" id="imageViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageViewModalLabel">Cheque Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Cheque image" class="img-fluid" style="max-height:70vh;">
                </div>
                <div class="modal-footer">
                    <a id="downloadImageBtn" href="" download class="btn btn-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── window._receiptAllBanks / _receiptOuId are set at the top of the page
    //    (before x-data) so Alpine can read them during init. ──

    // ── Alpine.js: main form component ────────────────────────────────────
    function receiptEditForm() {
        return {
            allBanks:     window._receiptAllBanks,
            customerOuId: window._receiptOuId,

            hasCash:   {{ ($receipt->cash_amount ?? 0) > 0 ? 'true' : 'false' }},
            hasCheque: {{ ($receipt->cheques && $receipt->cheques->count() > 0) ? 'true' : 'false' }},

            // Cash bank picker state
            cBankQ:'', cSelBank:'', cInstQ:'', cSelInst:'', cAccQ:'', cSelAcc:null,

            init() {
                @if(!empty($receipt->cash_bank_account_id))
                    const existing = this.allBanks.find(
                        b => String(b.bank_account_id) === '{{ $receipt->cash_bank_account_id }}'
                    );
                    if (existing) {
                        this.cSelAcc  = existing;
                        this.cSelBank = existing.bank_name      || '';
                        this.cSelInst = existing.receipt_method || '';
                    }
                @endif
            },

            get ouBanks() {
                if (!this.customerOuId) return this.allBanks;
                return this.allBanks.filter(b => String(b.org_id) === String(this.customerOuId));
            },

            // Cash — column 1
            get cBankNames() {
                const q = this.cBankQ.toLowerCase();
                const names = [...new Set(this.ouBanks.map(b => b.bank_name).filter(Boolean))].sort();
                return q ? names.filter(n => n.toLowerCase().includes(q)) : names;
            },
            selectCBank(name) { this.cSelBank = name; this.cSelInst = ''; this.cSelAcc = null; this.cBankQ = ''; },

            // Cash — column 2
            get cInstruments() {
                const q    = this.cInstQ.toLowerCase();
                const pool = this.cSelBank ? this.ouBanks.filter(b => b.bank_name === this.cSelBank) : this.ouBanks;
                const insts = [...new Set(pool.map(b => b.receipt_method).filter(Boolean))].sort();
                return q ? insts.filter(i => i.toLowerCase().includes(q)) : insts;
            },
            selectCInst(inst) { this.cSelInst = inst; this.cSelAcc = null; this.cInstQ = ''; },

            // Cash — column 3
            get cAccounts() {
                let pool = this.ouBanks;
                if (this.cSelBank) pool = pool.filter(b => b.bank_name      === this.cSelBank);
                if (this.cSelInst) pool = pool.filter(b => b.receipt_method === this.cSelInst);
                const q = this.cAccQ.toLowerCase();
                if (q) pool = pool.filter(b =>
                    (b.bank_account_name || '').toLowerCase().includes(q) ||
                    (b.bank_account_num  || '').toLowerCase().includes(q) ||
                    (b.bank_name         || '').toLowerCase().includes(q)
                );
                return pool;
            },
            selectCAcc(acc) { this.cSelAcc = acc; },
            clearCAcc()     { this.cSelAcc = null; this.cSelInst = ''; this.cSelBank = ''; },
        };
    }

    // ── Alpine.js: per-cheque isolated bank picker ────────────────────────
    function chequeBankPicker(existingInstrumentId, existingReceiptMethodId) {
        return {
            allBanks: window._receiptAllBanks || [],
            ouId:     window._receiptOuId     || '',
            bankQ:'', selBank:'',
            instQ:'', selInst:'',
            accQ:'',  selAcc: null,

            init() {
                if (existingInstrumentId) {
                    const found = this.allBanks.find(
                        b => String(b.bank_account_id) === String(existingInstrumentId) && (!existingReceiptMethodId || String(b.receipt_method_id) === String(existingReceiptMethodId))
                    );
                    if (found) {
                        this.selAcc  = found;
                        this.selBank = found.bank_name      || '';
                        this.selInst = found.receipt_method || '';
                    }
                }
            },

            get ouBanks() {
                if (!this.ouId) return this.allBanks;
                return this.allBanks.filter(b => String(b.org_id) === String(this.ouId));
            },

            get bankNames() {
                const q = this.bankQ.toLowerCase();
                const names = [...new Set(this.ouBanks.map(b => b.bank_name).filter(Boolean))].sort();
                return q ? names.filter(n => n.toLowerCase().includes(q)) : names;
            },
            pickBank(name) { this.selBank = name; this.selInst = ''; this.selAcc = null; this.bankQ = ''; },

            get instruments() {
                const q    = this.instQ.toLowerCase();
                const pool = this.selBank ? this.ouBanks.filter(b => b.bank_name === this.selBank) : this.ouBanks;
                const insts = [...new Set(pool.map(b => b.receipt_method).filter(Boolean))].sort();
                return q ? insts.filter(i => i.toLowerCase().includes(q)) : insts;
            },
            pickInst(inst) { this.selInst = inst; this.selAcc = null; this.instQ = ''; },

            get accounts() {
                let pool = this.ouBanks;
                if (this.selBank) pool = pool.filter(b => b.bank_name      === this.selBank);
                if (this.selInst) pool = pool.filter(b => b.receipt_method === this.selInst);
                const q = this.accQ.toLowerCase();
                if (q) pool = pool.filter(b =>
                    (b.bank_account_name || '').toLowerCase().includes(q) ||
                    (b.bank_account_num  || '').toLowerCase().includes(q) ||
                    (b.bank_name         || '').toLowerCase().includes(q)
                );
                return pool;
            },
            pickAcc(acc) { this.selAcc = acc; },
        };
    }

    // ── Multi-cheque add / remove ──────────────────────────────────────────
    let chequeIndex = {{ $receipt->cheques && $receipt->cheques->count() > 0 ? $receipt->cheques->count() : 1 }};

    document.getElementById('addChequeBtn').addEventListener('click', function () {
        const container = document.getElementById('chequesContainer');
        const idx = chequeIndex;
        const html = `
            <div class="cheque-item border rounded p-3 mb-3" data-cheque-index="${idx}"
                 x-data="chequeBankPicker(0, 0)">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Cheque #${idx + 1}</h6>
                    <button type="button" class="btn btn-sm btn-danger remove-cheque-btn"><i class="fas fa-trash"></i> Remove</button>
                </div>

                <p class="fw-semibold mb-2"><i class="fas fa-university me-1 text-primary"></i>Bank / Instrument</p>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>1. Bank Name <span x-show="selBank" class="badge bg-primary ms-1" x-text="selBank"></span></label>
                        <input type="text" class="form-control form-control-sm mb-1" x-model="bankQ" placeholder="Type to filter banks...">
                        <div class="filter-list">
                            <div class="filter-item text-muted" style="font-style:italic" @click="selBank='';selInst='';selAcc=null" :class="!selBank?'active':''">— All Banks —</div>
                            <template x-for="name in bankNames" :key="name"><div class="filter-item" :class="selBank===name?'active':''" @click="pickBank(name)" x-text="name"></div></template>
                            <div x-show="bankNames.length===0" class="filter-item text-muted">No banks found</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>2. Instrument <span x-show="selInst" class="badge bg-info text-dark ms-1" x-text="selInst"></span></label>
                        <input type="text" class="form-control form-control-sm mb-1" x-model="instQ" placeholder="Type to filter instruments...">
                        <div class="filter-list">
                            <div class="filter-item text-muted" style="font-style:italic" @click="selInst='';selAcc=null" :class="!selInst?'active':''">— All Instruments —</div>
                            <template x-for="inst in instruments" :key="inst"><div class="filter-item" :class="selInst===inst?'active':''" @click="pickInst(inst)" x-text="inst"></div></template>
                            <div x-show="instruments.length===0" class="filter-item text-muted">No instruments found</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-primary"><i class="fas fa-search me-1"></i>3. Account / Search <span x-show="selAcc" class="badge bg-success ms-1">Selected</span></label>
                        <input type="text" class="form-control form-control-sm mb-1" x-model="accQ" placeholder="Search account name or number...">
                        <div class="filter-list">
                            <template x-for="acc in accounts" :key="acc.id || acc.bank_account_id + '-' + acc.receipt_method_id">
                                <div class="filter-item bank-row" :class="(selAcc?.bank_account_id===acc.bank_account_id && selAcc?.receipt_method_id===acc.receipt_method_id)?'selected':''" @click="pickAcc(acc)">
                                    <div class="fw-semibold" x-text="acc.bank_account_name||acc.bank_name"></div>
                                    <div class="text-muted" style="font-size:.78rem" x-text="acc.bank_account_num"></div>
                                </div>
                            </template>
                            <div x-show="accounts.length===0" class="filter-item text-muted">No accounts match</div>
                        </div>
                    </div>
                </div>
                <div x-show="selAcc" class="detail-card mt-1 mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto"><i class="fas fa-check-circle text-success fa-lg"></i></div>
                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">BANK</div><div class="fw-semibold" x-text="selAcc?.bank_name"></div></div>
                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">INSTRUMENT</div><div x-text="selAcc?.receipt_method"></div></div>
                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NAME</div><div x-text="selAcc?.bank_account_name"></div></div>
                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NO.</div><div class="font-monospace" x-text="selAcc?.bank_account_num"></div></div>
                        <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">IBAN</div><div class="font-monospace" style="font-size:.8rem" x-text="selAcc?.iban_number||'—'"></div></div>
                        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="selAcc=null"><i class="fas fa-times"></i></button></div>
                    </div>
                </div>
                <input type="hidden" name="cheques[${idx}][instrument_id]" :value="selAcc?.bank_account_id??''">
                <hr class="my-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label fw-semibold">Cheque Number</label>
                        <input type="text" name="cheques[${idx}][cheque_no]" class="form-control" placeholder="Enter cheque number"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Cheque Amount</label>
                        <input type="number" name="cheques[${idx}][cheque_amount]" class="form-control receipt-amount-input" step="0.01" min="0" placeholder="0.00"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Cheque Date <span class="text-danger">*</span></label>
                        <input type="date" name="cheques[${idx}][cheque_date]" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Maturity Date <span class="text-danger">*</span></label>
                        <input type="date" name="cheques[${idx}][maturity_date]" class="form-control" required></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-8"><label class="form-label fw-semibold">Cheque Comments</label>
                        <textarea name="cheques[${idx}][comments]" class="form-control" rows="2" placeholder="Optional comments"></textarea></div>
                    <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2">
                        <input type="checkbox" name="cheques[${idx}][is_third_party_cheque]" class="form-check-input" value="1">
                        <label class="form-check-label">Third-party</label></div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold">Cheque Images</label>
                        <input type="file" name="cheques[${idx}][cheque_images][]" class="form-control" accept="image/*" multiple></div>
                </div>
                <input type="hidden" name="cheques[${idx}][instrument_name]"         :value="selAcc?.bank_name??''">
                <input type="hidden" name="cheques[${idx}][instrument_account_name]" :value="selAcc?.bank_account_name??''">
                <input type="hidden" name="cheques[${idx}][instrument_account_num]"  :value="selAcc?.bank_account_num??''">
                <input type="hidden" name="cheques[${idx}][receipt_method_id]" :value="selAcc?.receipt_method_id??''">
                <input type="hidden" name="cheques[${idx}][org_id]"                  :value="selAcc?.org_id??''">
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        Alpine.initTree(container.lastElementChild);
        chequeIndex++;
        bindRemoveButtons();
    });

    function bindRemoveButtons() {
        document.querySelectorAll('.remove-cheque-btn').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });
        document.querySelectorAll('.remove-cheque-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                this.closest('.cheque-item').remove();
                document.querySelectorAll('.cheque-item').forEach((item, i) => {
                    const title = item.querySelector('h6');
                    if (title) title.textContent = `Cheque #${i + 1}`;
                });
                calculateTotalReceiptAmount();
            });
        });
    }

    bindRemoveButtons();

    // ── Auto-calculate total from cash + all cheque amounts ────────────────
    function calculateTotalReceiptAmount() {
        let total = 0;
        document.querySelectorAll('.receipt-amount-input').forEach(inp => {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) total += v;
        });
        const el = document.getElementById('receipt_amount');
        if (el) el.value = total.toFixed(2);
    }
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('receipt-amount-input')) calculateTotalReceiptAmount();
    });
    calculateTotalReceiptAmount();

    // ── Sync description → comments hidden ────────────────────────────────
    document.getElementById('description').addEventListener('input', function () {
        document.getElementById('hidden_comments').value = this.value;
    });

    // ── Enter to Oracle ────────────────────────────────────────────────────
    const oracleBtnEl = document.getElementById('enterToOracleBtn');
    if (oracleBtnEl) {
        oracleBtnEl.addEventListener('click', function () {
            const receiptAmount = parseFloat(document.getElementById('receipt_amount').value) || 0;
            const receiptDate   = document.querySelector('input[name="receipt_date"]').value;
            const customerId    = '{{ $receipt->customer_id }}';

            if (receiptAmount <= 0) {
                alert('Receipt Amount must be greater than 0.');
                return;
            }
            if (!receiptDate) {
                alert('Receipt Date is required for Oracle integration.');
                return;
            }

            if (!confirm(`Are you sure you want to enter this receipt to Oracle?\n\nCustomer: ${customerId}\nAmount: ${receiptAmount}`)) return;

            const btn          = this;
            const originalText = btn.innerHTML;
            btn.disabled       = true;
            btn.innerHTML      = '<i class="fas fa-spinner fa-spin"></i> Entering to Oracle...';

            const formData = new FormData(document.getElementById('receiptForm'));
            formData.delete('_method');
            formData.append('receipt_id', {{ $receipt->id }});

            fetch('{{ route("reciepts.enter-to-oracle", $receipt->id) }}', {
                method: 'POST',
                body:   formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Receipt successfully entered to Oracle!');
                    btn.disabled  = true;
                    btn.className = 'btn btn-secondary';
                    btn.innerHTML = `<i class="fas fa-check-circle"></i> Already Entered to Oracle (#${data.oracle_receipt_number})`;
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    alert(data.message || 'Failed to enter receipt to Oracle. Please try again.');
                }
            })
            .catch(() => alert('An error occurred while entering to Oracle. Please try again.'))
            .finally(() => {
                btn.disabled  = false;
                btn.innerHTML = originalText;
            });
        });
    }

    // ── Image viewer ───────────────────────────────────────────────────────
    function viewImage(src, title) {
        document.getElementById('modalImage').src    = src;
        document.getElementById('imageViewModalLabel').textContent = title;
        const dl = document.getElementById('downloadImageBtn');
        dl.href     = src;
        dl.download = src.split('/').pop() || 'cheque_image';
        new bootstrap.Modal(document.getElementById('imageViewModal')).show();
    }
    </script>

</x-app-layout>
