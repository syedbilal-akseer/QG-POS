@push('title')
    Create New Receipt{{ isset($fromEdit) && $fromEdit ? ' - Continue from Previous' : '' }}
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
    </style>

    <div class="container mt-2"
         x-data="receiptForm()"
         x-init="init()">

        <div class="row justify-content-center">
            <div class="col-md-11">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Create New Receipt{{ isset($fromEdit) && $fromEdit ? ' - Continue from Previous' : '' }}</h4>
                        <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}" class="btn btn-secondary btn-sm">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                    </div>

                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @if(isset($fromEdit) && $fromEdit)
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle"></i> Customer pre-selected from previous receipt. Fields are locked.
                            </div>
                        @endif

                        <form action="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.store') : route('reciepts.store') }}"
                              method="POST" enctype="multipart/form-data">
                            @csrf

                            {{-- ═══════════════════════════════════════════
                                 CARD 1 — CUSTOMER
                            ═══════════════════════════════════════════ --}}
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white py-2">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Customer</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>

                                            @if($preSelectedCustomer)
                                                {{-- Locked: customer came from "Add More Receipt" --}}
                                                <input type="hidden" name="customer_id" value="{{ $preSelectedCustomer->customer_id }}">
                                                <input type="hidden" name="ou_id"       value="{{ $preSelectedCustomer->ou_id }}">
                                                <div class="form-control bg-light text-dark d-flex align-items-center gap-2">
                                                    <i class="fas fa-lock text-secondary"></i>
                                                    <strong>{{ $preSelectedCustomer->customer_name }}</strong>
                                                    <span class="badge bg-secondary ms-auto">{{ $preSelectedCustomer->ou_name }}</span>
                                                </div>
                                            @else
                                                {{-- Selectable customer --}}
                                                <select name="customer_id" id="customer_id" class="form-select" required
                                                        x-on:change="onCustomerChange($event)">
                                                    <option value="">— Select Customer —</option>
                                                    @foreach($customers as $c)
                                                        <option value="{{ $c->customer_id }}"
                                                                data-ou-id="{{ $c->ou_id }}"
                                                                data-ou-name="{{ $c->ou_name }}"
                                                                data-credit="{{ $c->overall_credit_limit }}"
                                                                {{ old('customer_id') == $c->customer_id ? 'selected' : '' }}>
                                                            {{ $c->customer_name }} ({{ $c->customer_id }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                {{-- Hidden OU field filled by JS --}}
                                                <input type="hidden" name="ou_id" x-bind:value="customerOuId">
                                            @endif
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Operating Unit</label>
                                            <div class="form-control bg-light text-secondary" x-text="customerOuName || '—'"></div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Credit Limit</label>
                                            <div class="form-control bg-light text-secondary"
                                                 x-text="customerCredit ? 'PKR ' + Number(customerCredit).toLocaleString() : '—'"></div>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="description" class="form-label fw-semibold">Comments <span class="text-danger">*</span></label>
                                            <textarea name="description" id="description" class="form-control" rows="3" required
                                                placeholder="Enter receipt description / comments">{{ old('description') }}</textarea>
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
                                                value="{{ old('receipt_number') }}" required placeholder="Auto-generated or enter manually">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Receipt Date <span class="text-danger">*</span></label>
                                            <input type="date" name="receipt_date" class="form-control"
                                                value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                                            <input type="number" name="receipt_amount" class="form-control"
                                                value="{{ old('receipt_amount') }}" step="0.01" min="0" required placeholder="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label fw-semibold">Currency</label>
                                            <select name="currency" class="form-select">
                                                <option value="PKR" {{ old('currency','PKR')=='PKR'?'selected':'' }}>PKR</option>
                                                <option value="USD" {{ old('currency')=='USD'?'selected':'' }}>USD</option>
                                                <option value="EUR" {{ old('currency')=='EUR'?'selected':'' }}>EUR</option>
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
                                            <input type="number" name="cash_amount" class="form-control"
                                                value="{{ old('cash_amount') }}" step="0.01" min="0"
                                                :disabled="!hasCash" placeholder="0.00">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Maturity Date</label>
                                            <input type="date" name="cash_maturity_date" class="form-control"
                                                value="{{ old('cash_maturity_date') }}"
                                                :disabled="!hasCash">
                                        </div>
                                    </div>

                                    {{-- Bank / Instrument picker for Cash --}}
                                    <div x-show="hasCash">
                                        <hr class="my-2">
                                        <p class="fw-semibold mb-2"><i class="fas fa-university me-1 text-primary"></i>Bank / Instrument for Cash</p>
                                        <div class="row g-3 mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary">
                                                    <i class="fas fa-search me-1"></i>1. Bank Name
                                                    <span x-show="cSelBank" class="badge bg-primary ms-1" x-text="cSelBank"></span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="cBankQ" placeholder="Type to filter banks..." :disabled="!customerOuId">
                                                <div class="filter-list" x-show="customerOuId">
                                                    <div class="filter-item text-muted" style="font-style:italic" @click="cSelBank='';cSelInst='';cSelAcc=null" :class="!cSelBank?'active':''">— All Banks —</div>
                                                    <template x-for="name in cBankNames" :key="name">
                                                        <div class="filter-item" :class="cSelBank===name?'active':''" @click="selectCBank(name)" x-text="name"></div>
                                                    </template>
                                                    <div x-show="cBankNames.length===0" class="filter-item text-muted">No banks found</div>
                                                </div>
                                                <div x-show="!customerOuId" class="text-muted small mt-1"><i class="fas fa-info-circle"></i> Select a customer first</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary">
                                                    <i class="fas fa-search me-1"></i>2. Instrument
                                                    <span x-show="cSelInst" class="badge bg-info text-dark ms-1" x-text="cSelInst"></span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="cInstQ" placeholder="Type to filter instruments..." :disabled="!customerOuId">
                                                <div class="filter-list" x-show="customerOuId">
                                                    <div class="filter-item text-muted" style="font-style:italic" @click="cSelInst='';cSelAcc=null" :class="!cSelInst?'active':''">— All Instruments —</div>
                                                    <template x-for="inst in cInstruments" :key="inst">
                                                        <div class="filter-item" :class="cSelInst===inst?'active':''" @click="selectCInst(inst)" x-text="inst"></div>
                                                    </template>
                                                    <div x-show="cInstruments.length===0" class="filter-item text-muted">No instruments found</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary">
                                                    <i class="fas fa-search me-1"></i>3. Account / Search
                                                    <span x-show="cSelAcc" class="badge bg-success ms-1">Selected</span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="cAccQ" placeholder="Search account name or number..." :disabled="!customerOuId">
                                                <div class="filter-list" x-show="customerOuId">
                                                    <template x-for="acc in cAccounts" :key="acc.bank_account_id">
                                                        <div class="filter-item bank-row" :class="cSelAcc?.bank_account_id===acc.bank_account_id?'selected':''" @click="selectCAcc(acc)">
                                                            <div class="fw-semibold" x-text="acc.bank_account_name||acc.bank_name"></div>
                                                            <div class="text-muted" style="font-size:.78rem" x-text="acc.bank_account_num"></div>
                                                        </div>
                                                    </template>
                                                    <div x-show="cAccounts.length===0&&customerOuId" class="filter-item text-muted">No accounts match</div>
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
                                    </div>
                                </div>
                            </div>

                            {{-- ═══════════════════════════════════════════
                                 CARD 4 — CHEQUE DETAILS (with bank picker)
                            ═══════════════════════════════════════════ --}}
                            <div class="card mb-3">
                                <div class="card-header py-2 d-flex align-items-center gap-3">
                                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2 text-warning"></i>Cheque Details</h5>
                                    <div class="form-check form-switch mb-0 ms-auto">
                                        <input class="form-check-input" type="checkbox" id="hasChequeToggle"
                                               x-model="hasCheque" role="switch">
                                        <label class="form-check-label" for="hasChequeToggle">
                                            <span x-text="hasCheque ? 'Included' : 'Not included'"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body" :class="!hasCheque ? 'section-disabled' : ''">
                                    <p x-show="!hasCheque" class="text-muted mb-2">
                                        <i class="fas fa-info-circle"></i> Enable the toggle above to enter cheque details.
                                    </p>

                                    {{-- Bank / Instrument picker for Cheque --}}
                                    <div x-show="hasCheque">
                                        <p class="fw-semibold mb-2"><i class="fas fa-university me-1 text-primary"></i>Bank / Instrument</p>
                                        <div class="row g-3 mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary">
                                                    <i class="fas fa-search me-1"></i>1. Bank Name
                                                    <span x-show="qSelBank" class="badge bg-primary ms-1" x-text="qSelBank"></span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="qBankQ" placeholder="Type to filter banks..." :disabled="!customerOuId">
                                                <div class="filter-list" x-show="customerOuId">
                                                    <div class="filter-item text-muted" style="font-style:italic" @click="qSelBank='';qSelInst='';qSelAcc=null" :class="!qSelBank?'active':''">— All Banks —</div>
                                                    <template x-for="name in qBankNames" :key="name">
                                                        <div class="filter-item" :class="qSelBank===name?'active':''" @click="selectQBank(name)" x-text="name"></div>
                                                    </template>
                                                    <div x-show="qBankNames.length===0" class="filter-item text-muted">No banks found</div>
                                                </div>
                                                <div x-show="!customerOuId" class="text-muted small mt-1"><i class="fas fa-info-circle"></i> Select a customer first</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary">
                                                    <i class="fas fa-search me-1"></i>2. Instrument
                                                    <span x-show="qSelInst" class="badge bg-info text-dark ms-1" x-text="qSelInst"></span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="qInstQ" placeholder="Type to filter instruments..." :disabled="!customerOuId">
                                                <div class="filter-list" x-show="customerOuId">
                                                    <div class="filter-item text-muted" style="font-style:italic" @click="qSelInst='';qSelAcc=null" :class="!qSelInst?'active':''">— All Instruments —</div>
                                                    <template x-for="inst in qInstruments" :key="inst">
                                                        <div class="filter-item" :class="qSelInst===inst?'active':''" @click="selectQInst(inst)" x-text="inst"></div>
                                                    </template>
                                                    <div x-show="qInstruments.length===0" class="filter-item text-muted">No instruments found</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold text-primary">
                                                    <i class="fas fa-search me-1"></i>3. Account / Search
                                                    <span x-show="qSelAcc" class="badge bg-success ms-1">Selected</span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm mb-1" x-model="qAccQ" placeholder="Search account name or number..." :disabled="!customerOuId">
                                                <div class="filter-list" x-show="customerOuId">
                                                    <template x-for="acc in qAccounts" :key="acc.bank_account_id">
                                                        <div class="filter-item bank-row" :class="qSelAcc?.bank_account_id===acc.bank_account_id?'selected':''" @click="selectQAcc(acc)">
                                                            <div class="fw-semibold" x-text="acc.bank_account_name||acc.bank_name"></div>
                                                            <div class="text-muted" style="font-size:.78rem" x-text="acc.bank_account_num"></div>
                                                        </div>
                                                    </template>
                                                    <div x-show="qAccounts.length===0&&customerOuId" class="filter-item text-muted">No accounts match</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div x-show="qSelAcc" class="detail-card mt-1 mb-3">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-auto"><i class="fas fa-check-circle text-success fa-lg"></i></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">BANK</div><div class="fw-semibold" x-text="qSelAcc?.bank_name"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">INSTRUMENT</div><div x-text="qSelAcc?.receipt_method"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NAME</div><div x-text="qSelAcc?.bank_account_name"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">ACCOUNT NO.</div><div class="font-monospace" x-text="qSelAcc?.bank_account_num"></div></div>
                                                <div class="col-md-2"><div class="text-muted" style="font-size:.75rem">IBAN</div><div class="font-monospace" style="font-size:.8rem" x-text="qSelAcc?.iban_number||'—'"></div></div>
                                                <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="clearQAcc()"><i class="fas fa-times"></i></button></div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="bank_account_id" :value="qSelAcc?.bank_account_id??''">
                                        <hr class="my-3">
                                    </div>

                                    {{-- Cheque detail fields --}}
                                    <div :class="(!hasCheque||!qSelAcc)?'section-disabled':''">
                                        <p x-show="hasCheque&&!qSelAcc" class="text-muted mb-2">
                                            <i class="fas fa-lock"></i> Select a bank account above to enter cheque details.
                                        </p>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Cheque Number</label>
                                                <input type="text" name="cheque_no" class="form-control"
                                                    value="{{ old('cheque_no') }}"
                                                    :disabled="!hasCheque||!qSelAcc" placeholder="Enter cheque number">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Cheque Amount</label>
                                                <input type="number" name="cheque_amount" class="form-control"
                                                    value="{{ old('cheque_amount') }}" step="0.01" min="0"
                                                    :disabled="!hasCheque||!qSelAcc" placeholder="0.00">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Cheque Date</label>
                                                <input type="date" name="cheque_date" class="form-control"
                                                    value="{{ old('cheque_date') }}"
                                                    :disabled="!hasCheque||!qSelAcc">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Maturity Date</label>
                                                <input type="date" name="maturity_date" class="form-control"
                                                    value="{{ old('maturity_date') }}"
                                                    :disabled="!hasCheque||!qSelAcc">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Cheque Comments</label>
                                                <textarea name="cheque_comments" class="form-control" rows="2"
                                                    :disabled="!hasCheque||!qSelAcc"
                                                    placeholder="Optional comments">{{ old('cheque_comments') }}</textarea>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold">Cheque Image</label>
                                                <input type="file" name="cheque_image" class="form-control" accept="image/*"
                                                    :disabled="!hasCheque||!qSelAcc">
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_third_party_cheque" value="1"
                                                           class="form-check-input" id="thirdPartyCheque"
                                                           :disabled="!hasCheque||!qSelAcc"
                                                           {{ old('is_third_party_cheque') ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="thirdPartyCheque">Third-party cheque</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Submit --}}
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ request()->routeIs('admin.receipts.*') ? route('admin.receipts.index') : route('reciepts') }}"
                                   class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary" :disabled="!customerOuId">
                                    <i class="fa fa-save me-1"></i> Create Receipt
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
    function receiptForm() {
        return {
            // ── Customer state ─────────────────────────────────────────────
            allBanks:       @json($oracleBanks),
            customerOuId:   '{{ old('ou_id', $preSelectedCustomer?->ou_id ?? '') }}',
            customerOuName: '{{ $preSelectedCustomer?->ou_name ?? '' }}',
            customerCredit: '{{ $preSelectedCustomer?->overall_credit_limit ?? '' }}',

            hasCash:    false,
            hasCheque:  false,

            // ── Cash bank picker state (c prefix) ──────────────────────────
            cBankQ:'', cSelBank:'', cInstQ:'', cSelInst:'', cAccQ:'', cSelAcc:null,

            // ── Cheque bank picker state (q prefix) ────────────────────────
            qBankQ:'', qSelBank:'', qInstQ:'', qSelInst:'', qAccQ:'', qSelAcc:null,

            // ── Init ───────────────────────────────────────────────────────
            init() {
                @if($preSelectedCustomer)
                    this.customerOuId   = '{{ $preSelectedCustomer->ou_id }}';
                    this.customerOuName = '{{ $preSelectedCustomer->ou_name }}';
                    this.customerCredit = '{{ $preSelectedCustomer->overall_credit_limit }}';
                @else
                    const sel = document.getElementById('customer_id');
                    if (sel && sel.value) this.onCustomerChange({ target: sel });
                @endif
            },

            // ── Customer change ────────────────────────────────────────────
            onCustomerChange(event) {
                const opt = event.target.options[event.target.selectedIndex];
                this.customerOuId   = opt.dataset.ouId   || '';
                this.customerOuName = opt.dataset.ouName || '';
                this.customerCredit = opt.dataset.credit || '';
                // Reset both pickers
                this.cBankQ=''; this.cSelBank=''; this.cInstQ=''; this.cSelInst=''; this.cAccQ=''; this.cSelAcc=null;
                this.qBankQ=''; this.qSelBank=''; this.qInstQ=''; this.qSelInst=''; this.qAccQ=''; this.qSelAcc=null;
            },

            // ── Banks scoped to this customer's OU ────────────────────────
            get ouBanks() {
                if (!this.customerOuId) return [];
                return this.allBanks.filter(b => String(b.org_id) === String(this.customerOuId));
            },

            // ── Cash bank picker ───────────────────────────────────────────
            get cBankNames() {
                const q = this.cBankQ.toLowerCase();
                const names = [...new Set(this.ouBanks.map(b => b.bank_name))].sort();
                return q ? names.filter(n => n.toLowerCase().includes(q)) : names;
            },
            get cInstruments() {
                const q    = this.cInstQ.toLowerCase();
                const pool = this.cSelBank ? this.ouBanks.filter(b => b.bank_name === this.cSelBank) : this.ouBanks;
                const insts = [...new Set(pool.map(b => b.receipt_method).filter(Boolean))].sort();
                return q ? insts.filter(i => i.toLowerCase().includes(q)) : insts;
            },
            get cAccounts() {
                let pool = this.ouBanks;
                if (this.cSelBank) pool = pool.filter(b => b.bank_name      === this.cSelBank);
                if (this.cSelInst) pool = pool.filter(b => b.receipt_method === this.cSelInst);
                const q = this.cAccQ.toLowerCase();
                if (q) pool = pool.filter(b =>
                    (b.bank_account_name||'').toLowerCase().includes(q) ||
                    (b.bank_account_num ||'').toLowerCase().includes(q) ||
                    (b.bank_name        ||'').toLowerCase().includes(q));
                return pool;
            },
            selectCBank(name) { this.cSelBank=name; this.cSelInst=''; this.cSelAcc=null; this.cBankQ=''; },
            selectCInst(inst) { this.cSelInst=inst; this.cSelAcc=null; this.cInstQ=''; },
            selectCAcc(acc)   { this.cSelAcc=acc; },
            clearCAcc()       { this.cSelAcc=null; },

            // ── Cheque bank picker ─────────────────────────────────────────
            get qBankNames() {
                const q = this.qBankQ.toLowerCase();
                const names = [...new Set(this.ouBanks.map(b => b.bank_name))].sort();
                return q ? names.filter(n => n.toLowerCase().includes(q)) : names;
            },
            get qInstruments() {
                const q    = this.qInstQ.toLowerCase();
                const pool = this.qSelBank ? this.ouBanks.filter(b => b.bank_name === this.qSelBank) : this.ouBanks;
                const insts = [...new Set(pool.map(b => b.receipt_method).filter(Boolean))].sort();
                return q ? insts.filter(i => i.toLowerCase().includes(q)) : insts;
            },
            get qAccounts() {
                let pool = this.ouBanks;
                if (this.qSelBank) pool = pool.filter(b => b.bank_name      === this.qSelBank);
                if (this.qSelInst) pool = pool.filter(b => b.receipt_method === this.qSelInst);
                const q = this.qAccQ.toLowerCase();
                if (q) pool = pool.filter(b =>
                    (b.bank_account_name||'').toLowerCase().includes(q) ||
                    (b.bank_account_num ||'').toLowerCase().includes(q) ||
                    (b.bank_name        ||'').toLowerCase().includes(q));
                return pool;
            },
            selectQBank(name) { this.qSelBank=name; this.qSelInst=''; this.qSelAcc=null; this.qBankQ=''; },
            selectQInst(inst) { this.qSelInst=inst; this.qSelAcc=null; this.qInstQ=''; },
            selectQAcc(acc)   { this.qSelAcc=acc; },
            clearQAcc()       { this.qSelAcc=null; },
        };
    }
    </script>
</x-app-layout>
