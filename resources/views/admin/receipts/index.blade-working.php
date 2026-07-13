@push('title')
    Customer Receipts Management
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/css/lightbox.min.css" rel="stylesheet">

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header with Search and Add New Receipt -->
        <div class="d-flex justify-content-between mb-3">
            <h3>Receipts</h3>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-file-export"></i> Export Data
                </button>
                <a href="{{ route('admin.receipts.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Receipt
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        @php $isAdmin = auth()->user()->isAdmin(); @endphp
        <div class="row mb-4">
            {{-- Total Receipts --}}
            <div class="{{ $isAdmin ? 'col-md-3' : 'col-md-4' }} mb-3">
                <div class="card shadow h-100" style="border-color: #3B82F6;">
                    <div class="card-body text-center">
                        <i class="fas fa-receipt fa-2x mb-2" style="color: #3B82F6;"></i>
                        <h5 class="card-title" style="color: #3B82F6;">Total Receipts</h5>
                        <h3 class="text-dark">{{ number_format($stats['total_receipts']) }}</h3>
                    </div>
                </div>
            </div>

            {{-- Pending --}}
            <div class="{{ $isAdmin ? 'col-md-3' : 'col-md-4' }} mb-3">
                <div class="card shadow h-100" style="border-color: #F59E0B;">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2" style="color: #F59E0B;"></i>
                        <h5 class="card-title" style="color: #F59E0B;">Pending</h5>
                        <h3 class="text-dark">{{ number_format($stats['pending_receipts']) }}</h3>
                        @if($isAdmin)
                            <p class="text-muted mb-0" style="font-size:.85rem;">
                                Rs {{ number_format($stats['pending_amount'], 0) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Pushed to Oracle --}}
            <div class="{{ $isAdmin ? 'col-md-3' : 'col-md-4' }} mb-3">
                <div class="card shadow h-100" style="border-color: #10B981;">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2" style="color: #10B981;"></i>
                        <h5 class="card-title" style="color: #10B981;">Pushed to Oracle</h5>
                        <h3 class="text-dark">{{ number_format($stats['pushed_receipts']) }}</h3>
                        @if($isAdmin)
                            <p class="text-muted mb-0" style="font-size:.85rem;">
                                Rs {{ number_format($stats['pushed_amount'], 0) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Total Amount — admin only --}}
            @if($isAdmin)
            <div class="col-md-3 mb-3">
                <div class="card shadow h-100" style="border-color: #8B5CF6;">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2" style="color: #8B5CF6;"></i>
                        <h5 class="card-title" style="color: #8B5CF6;">Total Amount</h5>
                        <h3 class="text-dark">Rs {{ number_format($stats['total_amount'], 0) }}</h3>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Filters -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="search" id="searchInput" placeholder="Search by Receipt #, Cheque #, or Customer..."
                        class="form-control border-start-0 ps-0" value="{{ request('search') }}" />
                </div>
            </div>
            <div class="col-md-8 text-end">
                <div class="dropdown d-inline-block me-2">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <i class="fa fa-filter me-1"></i> Filter
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-4 shadow" style="min-width: 360px; max-height: 80vh; overflow-y: auto; border-radius: 12px;">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Receipt Number</label>
                            <input type="text" id="receiptNumberFilter" class="form-control"
                                value="{{ request('receipt_number') }}" placeholder="e.g. 2026-0042" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Customer</label>
                            <select id="customerFilter" class="form-select">
                                <option value="">All Customers</option>
                                @foreach($customerOptions ?? [] as $cid => $cname)
                                    <option value="{{ $cid }}" {{ (string) request('customer_id') === (string) $cid ? 'selected' : '' }}>{{ $cname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Salesperson (Created By)</label>
                            <select id="salespersonFilter" class="form-select">
                                <option value="">All Salespeople</option>
                                @foreach($salespeople ?? [] as $uid => $uname)
                                    <option value="{{ $uid }}" {{ (string) request('created_by') === (string) $uid ? 'selected' : '' }}>{{ $uname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pushed By</label>
                            <select id="pushedByFilter" class="form-select">
                                <option value="">Anyone</option>
                                @foreach($pushers ?? [] as $uid => $uname)
                                    <option value="{{ $uid }}" {{ (string) request('oracle_entered_by') === (string) $uid ? 'selected' : '' }}>{{ $uname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <select id="locationFilter" class="form-select">
                                <option value="">All Locations</option>
                                <option value="karachi" {{ request('location') == 'karachi' ? 'selected' : '' }}>Karachi</option>
                                <option value="lahore" {{ request('location') == 'lahore' ? 'selected' : '' }}>Lahore</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Currency</label>
                            <select id="currencyFilter" class="form-select">
                                <option value="">All Currencies</option>
                                <option value="PKR" {{ request('currency') == 'PKR' ? 'selected' : '' }}>PKR</option>
                                <option value="USD" {{ request('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Payment Type</label>
                            <select id="typeFilter" class="form-select">
                                <option value="">All Types</option>
                                <option value="cash_only" {{ request('receipt_type') == 'cash_only' ? 'selected' : '' }}>Cash Only</option>
                                <option value="cheque_only" {{ request('receipt_type') == 'cheque_only' ? 'selected' : '' }}>Cheque Only</option>
                                <option value="cash_and_cheque" {{ request('receipt_type') == 'cash_and_cheque' ? 'selected' : '' }}>Cash & Cheque</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="pushed" {{ request('status') == 'pushed' ? 'selected' : '' }}>Pushed to Oracle</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Receipt Date</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="date" id="fromDateFilter" class="form-control"
                                        value="{{ request('from_date') }}" placeholder="From" />
                                </div>
                                <div class="col-6">
                                    <input type="date" id="toDateFilter" class="form-control"
                                        value="{{ request('to_date') }}" placeholder="To" />
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pushed Date</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="date" id="pushedFromFilter" class="form-control"
                                        value="{{ request('pushed_from') }}" placeholder="From" />
                                </div>
                                <div class="col-6">
                                    <input type="date" id="pushedUntilFilter" class="form-control"
                                        value="{{ request('pushed_until') }}" placeholder="To" />
                                </div>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="button" id="applyFilters" class="btn btn-primary">Apply Filters</button>
                            <button type="button" id="resetFilters" class="btn btn-link btn-sm text-decoration-none text-muted">Reset All</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Table List Receipts -->
        <div class="table-responsive">
            <table class="table" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Salesperson</th>
                        <th>Currency</th>
                        <th>Total Amount</th>
                        <th>Payment Type</th>
                        <th>Status</th>
                        <th>Pushed By</th>
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
                        <td>{{ $receipt->createdBy->name ?? 'N/A' }}</td>
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
                            @if($receipt->oracle_entered_at)
                                <span class="badge bg-success">Entered to Oracle</span>
                            @else
                                <span class="badge bg-warning">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($receipt->oracle_entered_at)
                                {{ $receipt->enteredBy->name ?? 'N/A' }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex">
                                <!-- View Button -->
                                @if($receipt->oracle_entered_at)
                                <button class="btn btn-sm btn-success text-white me-1 text-nowrap" type="button"
                                    onclick="viewReceipt({{ $receipt->id }})" data-bs-toggle="modal" data-bs-target="#viewModal">
                                    <i class="fas fa-check-circle"></i> View (Pushed to Oracle)
                                </button>
                                @else
                                <button class="btn btn-sm btn-warning me-1 text-white text-nowrap" type="button"
                                    onclick="viewReceipt({{ $receipt->id }})" data-bs-toggle="modal" data-bs-target="#viewModal">
                                    <i class="fas fa-clock"></i> View
                                </button>
                                @endif

                                <!-- Edit Button - Hidden if already pushed to Oracle -->
                                @if(!$receipt->oracle_entered_at)
                                <a href="{{ route('admin.receipts.edit', $receipt->id) }}" class="btn btn-sm btn-edit me-1">
                                    Edit
                                </a>

                                <!-- Delete Button -->
                                <button class="btn btn-sm btn-danger"
                                    onclick="confirmDelete({{ $receipt->id }})">
                                    Delete
                                </button>
                                @endif
                            </div>
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

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" :class="{ 'bg-dark': darkMode, 'bg-light': !darkMode, 'text-light': darkMode, 'text-dark': !darkMode }">
                <form action="{{ route('admin.receipts.download_excel') }}" method="GET">
                    <div class="modal-header">
                        <h5 class="modal-title">Export Receipts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending Only</option>
                                <option value="pushed">Pushed Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Download Excel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Receipt Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" :class="{ 'bg-dark': darkMode, 'bg-light': !darkMode, 'text-light': darkMode, 'text-dark': !darkMode }">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="viewModalLabel">Receipt Details</h1>
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

    <script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/js/lightbox.min.js"></script>

    <script>
        // Filter functionality
        document.getElementById('applyFilters').addEventListener('click', function() {
            const search        = document.getElementById('searchInput').value;
            const receiptNumber = document.getElementById('receiptNumberFilter').value;
            const customerId    = document.getElementById('customerFilter').value;
            const createdBy     = document.getElementById('salespersonFilter').value;
            const pushedBy      = document.getElementById('pushedByFilter').value;
            const location      = document.getElementById('locationFilter').value;
            const currency      = document.getElementById('currencyFilter').value;
            const type          = document.getElementById('typeFilter').value;
            const status        = document.getElementById('statusFilter').value;
            const fromDate      = document.getElementById('fromDateFilter').value;
            const toDate        = document.getElementById('toDateFilter').value;
            const pushedFrom    = document.getElementById('pushedFromFilter').value;
            const pushedUntil   = document.getElementById('pushedUntilFilter').value;

            const params = new URLSearchParams();
            if (search)        params.append('search', search);
            if (receiptNumber) params.append('receipt_number', receiptNumber);
            if (customerId)    params.append('customer_id', customerId);
            if (createdBy)     params.append('created_by', createdBy);
            if (pushedBy)      params.append('oracle_entered_by', pushedBy);
            if (location)      params.append('location', location);
            if (currency)      params.append('currency', currency);
            if (type)          params.append('receipt_type', type);
            if (status)        params.append('status', status);
            if (fromDate)      params.append('from_date', fromDate);
            if (toDate)        params.append('to_date', toDate);
            if (pushedFrom)    params.append('pushed_from', pushedFrom);
            if (pushedUntil)   params.append('pushed_until', pushedUntil);

            window.location.href = '{{ route("admin.receipts.index") }}?' + params.toString();
        });

        // Reset functionality
        document.getElementById('resetFilters').addEventListener('click', function() {
            window.location.href = '{{ route("admin.receipts.index") }}';
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
            
            fetch(`{{ url('app/receipts') }}/${receiptId}`)
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


        // Confirm delete
        function confirmDelete(receiptId) {
            const deleteForm = document.getElementById('deleteForm');
            deleteForm.action = `{{ url('app/receipts') }}/${receiptId}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Verify receipt (placeholder)
        function verifyReceipt(receiptId) {
            alert('Verify functionality will be implemented to send data to Oracle database');
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