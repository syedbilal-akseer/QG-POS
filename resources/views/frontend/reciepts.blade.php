@push('title')
    Customer Receipts
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/css/lightbox.min.css" rel="stylesheet">

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header with Search and Filters -->
        <div class="d-flex justify-content-between mb-3">
            <h3>Customer Receipts</h3>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="search" id="searchInput" placeholder="Search receipts..." 
                    class="form-control" value="{{ request('search') }}" />
            </div>
            <div class="col-md-2">
                <select id="currencyFilter" class="form-select">
                    <option value="">All Currencies</option>
                    <option value="PKR" {{ request('currency') == 'PKR' ? 'selected' : '' }}>PKR</option>
                    <option value="USD" {{ request('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="typeFilter" class="form-select">
                    <option value="">All Types</option>
                    <option value="cash_only" {{ request('receipt_type') == 'cash_only' ? 'selected' : '' }}>Cash Only</option>
                    <option value="cheque_only" {{ request('receipt_type') == 'cheque_only' ? 'selected' : '' }}>Cheque Only</option>
                    <option value="cash_and_cheque" {{ request('receipt_type') == 'cash_and_cheque' ? 'selected' : '' }}>Cash & Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" id="yearFilter" placeholder="Year" class="form-control" 
                    value="{{ request('year') }}" min="2020" max="{{ date('Y') + 1 }}" />
            </div>
            <div class="col-md-1">
                <button type="button" id="applyFilters" class="btn btn-secondary">
                    <i class="fa fa-filter"></i>
                </button>
            </div>
        </div>

        <!-- Table List Monthly Tour Plans -->
        <div class="table-responsive">
            <table class="table" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Currency</th>
                        <th>Total Amount</th>
                        <th>Payment Type</th>
                        <th>Cheques</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $receipt)
                    <tr>
                        <td>{{ $receipt->receipt_number }}</td>
                        <td>{{ $receipt->created_at->format('d M Y') }}</td>
                        <td>
                            <strong>{{ $receipt->customer->customer_name ?? 'N/A' }}</strong><br>
                            <small class="text-muted">#{{ $receipt->customer_id }}</small>
                        </td>
                        <td>{{ $receipt->currency }}</td>
                        <td>{{ $receipt->formatted_amount }}</td>
                        <td>
                            @if($receipt->receipt_type == 'cash_only')
                                <span class="badge bg-success">Cash Only</span>
                            @elseif($receipt->receipt_type == 'cheque_only')
                                <span class="badge bg-info">Cheque Only</span>
                            @else
                                <span class="badge bg-warning">Cash & Cheque</span>
                            @endif
                        </td>
                        <td>
                            @if($receipt->cheques && $receipt->cheques->count() > 0)
                                <span class="badge bg-primary">{{ $receipt->cheques->count() }} Cheque(s)</span>
                            @elseif($receipt->cheque_no)
                                <span class="badge bg-info">{{ $receipt->cheque_no }}</span>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <!-- View Button -->
                            <button class="btn btn-sm btn-details me-2" type="button" 
                                onclick="viewReceipt({{ $receipt->id }})" data-bs-toggle="modal" data-bs-target="#verify">
                                <i class="fa fa-eye"></i> View
                            </button>

                            <!-- Edit Button -->
                            <button class="btn btn-sm btn-edit me-2" onclick="editReceipt({{ $receipt->id }})">
                                <i class="fa fa-pencil"></i> Edit
                            </button>

                            <!-- Delete Button -->
                            <button class="btn btn-sm btn-danger me-2" 
                                onclick="confirmDelete({{ $receipt->id }})">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fa fa-file-text-o fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No receipts found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Showing {{ $receipts->firstItem() ?? 0 }} to {{ $receipts->lastItem() ?? 0 }} 
                of {{ $receipts->total() }} results
            </div>
            <div>
                {{ $receipts->links() }}
            </div>
        </div>
    </div>
    <script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal -->
    <!-- Modal Structure -->
    <div class="modal fade" id="verify" tabindex="-1" aria-labelledby="verifyLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content"
                :class="{ 'bg-dark': darkMode, 'bg-light': !darkMode, 'text-light': darkMode, 'text-dark': !darkMode }">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalLabel">Customers Receipts</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="receiptDetails">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    {{-- <button type="button" class="btn btn-details">Verify</button> --}}
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this receipt? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/js/lightbox.min.js"></script>
    
    <script>
        // Filter functionality
        document.getElementById('applyFilters').addEventListener('click', function() {
            const search = document.getElementById('searchInput').value;
            const currency = document.getElementById('currencyFilter').value;
            const type = document.getElementById('typeFilter').value;
            const year = document.getElementById('yearFilter').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (currency) params.append('currency', currency);
            if (type) params.append('receipt_type', type);
            if (year) params.append('year', year);

            window.location.href = '{{ route("reciepts") }}?' + params.toString();
        });

        // Search on Enter
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('applyFilters').click();
            }
        });

        // Global variable to store current receipt ID
        let currentReceiptId = null;

        // View receipt details
        function viewReceipt(receiptId) {
            currentReceiptId = receiptId;
            
            fetch(`{{ url('app/reciepts') }}/${receiptId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('receiptDetails').innerHTML = html;
                    
                    // Receipt loaded successfully
                })
                .catch(error => {
                    document.getElementById('receiptDetails').innerHTML = 
                        '<div class="alert alert-danger">Error loading receipt details</div>';
                });
        }


        // Edit receipt
        function editReceipt(receiptId) {
            window.location.href = `{{ url('app/reciepts') }}/${receiptId}/edit`;
        }

        // Confirm delete
        function confirmDelete(receiptId) {
            const deleteForm = document.getElementById('deleteForm');
            deleteForm.action = `{{ url('app/reciepts') }}/${receiptId}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Initialize lightbox
        document.addEventListener('DOMContentLoaded', function() {
            lightbox.init();
        });
    </script>

    <style>
        .btn-details {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .btn-edit {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .table th {
            border-top: none;
        }

        .badge {
            font-size: 0.75em;
        }
    </style>

</x-app-layout>
