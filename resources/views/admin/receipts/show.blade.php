<!-- Customer Info -->
<div class="mb-4">
    <h5 class="font-weight-bold">Customer Information</h5>
    <hr class="my-2">
    <div class="row">
        <div class="col-md-6">
            <p><strong>Customer ID:</strong> {{ $receipt->customer_id }}</p>
            <p><strong>Customer Name:</strong> {{ $receipt->customer->customer_name ?? 'N/A' }}</p>
            <p><strong>Receipt Number:</strong> {{ $receipt->receipt_number }}</p>
        </div>
        <div class="col-md-6">
            <p><strong>Outstanding Balance:</strong> {{ $receipt->currency }} {{ number_format($receipt->outstanding, 2) }}</p>
            <p><strong>Credit Limit:</strong> {{ $receipt->currency }} {{ number_format($receipt->overall_credit_limit, 2) }}</p>
            <p><strong>Receipt Date:</strong> {{ $receipt->created_at->format('d M Y, H:i') }}</p>
        </div>
    </div>
</div>

<!-- Payment Information -->
<div class="mb-4">
    <h5 class="font-weight-bold">Payment Information</h5>
    <hr class="my-2">
    <div class="row">
        <div class="col-md-6">
            <p><strong>Currency:</strong> {{ $receipt->currency }}</p>
            <p><strong>Payment Type:</strong> 
                <span class="badge 
                    @if($receipt->receipt_type == 'cash_only') bg-success
                    @elseif($receipt->receipt_type == 'cheque_only') bg-info
                    @else bg-warning @endif">
                    {{ ucwords(str_replace('_', ' ', $receipt->receipt_type)) }}
                </span>
            </p>
            @if($receipt->cash_amount)
            <p><strong>Cash Amount:</strong> {{ $receipt->formatted_cash_amount }}</p>
            <!-- @if($receipt->cash_maturity_date)
            <p><strong>Cash Maturity Date:</strong> {{ $receipt->cash_maturity_date->format('d M Y') }}</p>
            @endif -->
            @endif
        </div>
        <div class="col-md-6">
            @if($receipt->cheques && $receipt->cheques->count() > 0)
            <p><strong>Number of Cheques:</strong> {{ $receipt->cheques->count() }}</p>
            <p><strong>Total Cheque Amount:</strong> {{ $receipt->currency }} {{ number_format($receipt->cheques->sum('cheque_amount'), 2) }}</p>
            @endif
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <p><strong>Total Amount:</strong> <span class="h5 text-primary">{{ $receipt->formatted_amount }}</span></p>
        </div>
    </div>
</div>

<!-- Bank Information -->
@if($receipt->remittance_bank_name || $receipt->customer_bank_name)
<div class="mb-4">
    <h5 class="font-weight-bold">Bank Information</h5>
    <hr class="my-2">
    <div class="row">
        @if($receipt->remittance_bank_name)
        <div class="col-md-6">
            <p><strong>Remittance Bank:</strong> {{ $receipt->remittance_bank_name }}</p>
        </div>
        @endif
        @if($receipt->customer_bank_name)
        <div class="col-md-6">
            <p><strong>Customer Bank:</strong> {{ $receipt->customer_bank_name }}</p>
        </div>
        @endif
    </div>
</div>
@endif

<!-- Cheques Information -->
@if($receipt->cheques && $receipt->cheques->count() > 0)
<div class="mb-4">
    <h5 class="font-weight-bold">Cheques Details</h5>
    <hr class="my-2">
    
    @foreach($receipt->cheques as $index => $cheque)
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">Cheque #{{ $index + 1 }} - {{ $cheque->cheque_no }}</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Bank:</strong> {{ $cheque->bank_name }}</p>
                    <p><strong>Cheque Number:</strong> {{ $cheque->cheque_no }}</p>
                    <p><strong>Amount:</strong> {{ $receipt->currency }} {{ number_format($cheque->cheque_amount, 2) }}</p>
                    <p><strong>Cheque Date:</strong> {{ $cheque->cheque_date->format('d M Y') }}</p>
                    @if($cheque->maturity_date)
                    <p><strong>Maturity Date:</strong> {{ $cheque->maturity_date->format('d M Y') }}</p>
                    @endif
                </div>
                <div class="col-md-6">
                    <p><strong>Third Party Cheque:</strong> {{ $cheque->is_third_party_cheque ? 'Yes' : 'No' }}</p>
                    <p><strong>Status:</strong> 
                        <span class="badge 
                            @if($cheque->status == 'pending') bg-warning
                            @elseif($cheque->status == 'cleared') bg-success
                            @elseif($cheque->status == 'bounced') bg-danger
                            @else bg-secondary @endif">
                            {{ ucfirst($cheque->status) }}
                        </span>
                    </p>
                    @if($cheque->reference)
                    <p><strong>Reference:</strong> {{ $cheque->reference }}</p>
                    @endif
                    @if($cheque->comments)
                    <p><strong>Comments:</strong> {{ $cheque->comments }}</p>
                    @endif
                </div>
            </div>
            
            <!-- Cheque Images -->
            @if($cheque->cheque_images && count($cheque->cheque_images) > 0)
            <div class="row mt-3">
                <div class="col-12">
                    <strong>Cheque Images:</strong>
                    <div class="row mt-2">
                        @foreach($cheque->cheque_images as $imageIndex => $imageUrl)
                        <div class="col-md-3 mb-2">
                            <a href="{{ $imageUrl }}" data-lightbox="cheque-images-{{ $cheque->id }}" data-title="Cheque {{ $cheque->cheque_no }} - Image {{ $imageIndex + 1 }}">
                                <img src="{{ $imageUrl }}" class="img-fluid rounded border" alt="Cheque Image {{ $imageIndex + 1 }}" style="max-height: 150px; width: 100%; object-fit: cover;">
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif

<!-- Comments and Description -->
<div class="mb-4">
    <h5 class="font-weight-bold">Description & Comments</h5>
    <hr class="my-2">
    <div class="row">
        <div class="col-12">
            <p><strong>Description:</strong></p>
            <p class="bg-dark p-3 rounded">{{ $receipt->description }}</p>
        </div>
    </div>
</div>

<!-- Oracle Status -->
@if($receipt->oracle_entered_at)
<div class="mb-4">
    <h5 class="font-weight-bold">Oracle Information</h5>
    <hr class="my-2">
    <div class="row">
        <div class="col-md-6">
            <p><strong>Oracle Status:</strong>
                <span class="badge
                    @if($receipt->oracle_status == 'entered') bg-success
                    @elseif($receipt->oracle_status == 'pending') bg-warning
                    @elseif($receipt->oracle_status == 'failed') bg-danger
                    @else bg-secondary @endif">
                    {{ ucfirst($receipt->oracle_status ?? 'N/A') }}
                </span>
            </p>
            <p><strong>Oracle Receipt Number:</strong> {{ $receipt->oracle_receipt_number ?? 'N/A' }}</p>
        </div>
        <div class="col-md-6">
            <p><strong>Entered By:</strong> {{ $receipt->enteredBy->name ?? 'N/A' }}</p>
            <p><strong>Entered At:</strong> {{ $receipt->oracle_entered_at ? $receipt->oracle_entered_at->format('d M Y, H:i:s') : 'N/A' }}</p>
        </div>
    </div>
</div>
@endif

<!-- Created By -->
<div class="mb-4">
    <h5 class="font-weight-bold">Created By</h5>
    <hr class="my-2">
    <div class="row">
        <div class="col-md-6">
            <p><strong>User:</strong> {{ $receipt->createdBy->name ?? 'System' }}</p>
            <p><strong>Email:</strong> {{ $receipt->createdBy->email ?? 'N/A' }}</p>
        </div>
        <div class="col-md-6">
            <p><strong>Created At:</strong> {{ $receipt->created_at->format('d M Y, H:i:s') }}</p>
            <p><strong>Last Updated:</strong> {{ $receipt->updated_at->format('d M Y, H:i:s') }}</p>
        </div>
    </div>
</div>