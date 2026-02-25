@push('title')
    Price Comparison Review
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2">
        <!-- Header -->
        <div class="d-flex justify-content-between mb-3">
            <h3>Price Comparison Review</h3>
            <div>
                <a href="{{ route('price-lists.index') }}" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Back to Price Lists
                </a>
                <a href="{{ route('price-lists.export-comparison', request('upload_id')) }}"
                   class="btn btn-success me-2">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
                <button id="sendToOracleBtn" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Selected Prices to Oracle
                </button>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card border-primary shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-list fa-2x text-primary mb-2"></i>
                        <h5 class="card-title text-primary">Total Items</h5>
                        <h3 class="text-dark">{{ number_format($summary['total_items']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 mb-3">
                <div class="card shadow h-100" style="border-color: #17a2b8;">
                    <div class="card-body text-center">
                        <i class="fas fa-plus fa-2x mb-2" style="color: #17a2b8;"></i>
                        <h5 class="card-title" style="color: #17a2b8;">New Items</h5>
                        <h3 class="text-dark">{{ number_format($summary['new_items']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 mb-3">
                <div class="card shadow h-100" style="border-color: #6c757d;">
                    <div class="card-body text-center">
                        <i class="fas fa-equals fa-2x mb-2" style="color: #6c757d;"></i>
                        <h5 class="card-title" style="color: #6c757d;">Same Price</h5>
                        <h3 class="text-dark">{{ number_format($summary['same_price']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 mb-3">
                <div class="card border-warning shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-arrow-up fa-2x text-warning mb-2"></i>
                        <h5 class="card-title text-warning">Price Increased</h5>
                        <h3 class="text-dark">{{ number_format($summary['price_increased']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 mb-3">
                <div class="card border-danger shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                        <h5 class="card-title text-danger">Price Decreased</h5>
                        <h3 class="text-dark">{{ number_format($summary['price_decreased']) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Color Legend -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">Color Legend:</h6>
                <div class="row">
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #d1ecf1; color: #0c5460;">New Items</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #f8f9fa; color: #495057;">Same Price</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #fff3cd; color: #856404;">Price Increased</span>
                    </div>
                    <div class="col-md-3">
                        <span class="badge" style="background-color: #f8d7da; color: #721c24;">Price Decreased</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Select All Options -->
        <div class="mb-3">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="selectAll">
                <label class="form-check-label" for="selectAll">Select All</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="selectChangedOnly">
                <label class="form-check-label" for="selectChangedOnly">Select Changed Prices Only</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="selectNewOnly">
                <label class="form-check-label" for="selectNewOnly">Select New Items Only</label>
            </div>
        </div>

        <!-- Price Effective Date -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-calendar-day me-2"></i>Price Effective Date</h6>
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label for="priceStartDate" class="form-label">New Price Start Date:</label>
                        <input type="date" 
                               class="form-control" 
                               id="priceStartDate" 
                               name="start_date" 
                               value="{{ now()->addDay()->format('Y-m-d') }}" 
                               min="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-8">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Price History Management:</strong> Current prices will be end-dated automatically, and new prices will become effective from the selected start date.
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

        <!-- Price Comparison Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAllHeader" class="form-check-input">
                        </th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>UOM</th>
                        <th>Price List</th>
                        <th>Previous Price</th>
                        <th>New Price</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comparisonData as $item)
                    <tr style="background-color: {{ $item['background_color'] }};" 
                        data-item-code="{{ $item['item_code'] }}"
                        data-price-status="{{ $item['price_status'] }}">
                        <td>
                            <input type="checkbox" name="selected_items[]" 
                                   value="{{ $item['item_code'] }}" 
                                   class="form-check-input item-checkbox"
                                   {{ in_array($item['price_status'], ['increased', 'decreased', 'new']) ? 'checked' : '' }}>
                        </td>
                        <td>
                            <strong>{{ $item['item_code'] }}</strong>
                        </td>
                        <td>{{ $item['item_description'] ?? 'N/A' }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $item['uom'] ?? 'N/A' }}</span>
                        </td>
                        <td>
                            <small class="text-muted">{{ $item['price_type'] ?? 'N/A' }}</small><br>
                            {{ $item['price_list_name'] ?? 'N/A' }}
                        </td>
                        <td>
                            @if($item['previous_price'] !== null && $item['previous_price'] > 0)
                                <span class="fw-bold text-muted">{{ number_format($item['previous_price'], 2) }}</span>
                            @else
                                <span class="text-muted">New Item</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-bold">{{ number_format($item['list_price'], 2) }}</span>
                        </td>
                        <td>
                            @if($item['price_change'] !== null)
                                <span class="fw-bold {{ $item['price_change'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $item['price_change'] >= 0 ? '+' : '' }}{{ number_format($item['price_change'], 2) }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @switch($item['price_status'])
                                @case('new')
                                    <span class="badge bg-info">New</span>
                                    @break
                                @case('same')
                                    <span class="badge bg-secondary">Same</span>
                                    @break
                                @case('increased')
                                    <span class="badge bg-warning text-dark">Increased</span>
                                    @break
                                @case('decreased')
                                    <span class="badge bg-danger">Decreased</span>
                                    @break
                            @endswitch
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No comparison data found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Selected Items Counter -->
        <div class="mt-3 mb-3">
            <span id="selectedCount" class="badge bg-primary">0 items selected</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Setup CSRF token for all AJAX requests
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        console.log('CSRF Token:', csrfToken ? 'Found (' + csrfToken.substring(0, 10) + '...)' : 'NOT FOUND!');

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        $(document).ready(function() {

            // Update selected count
            function updateSelectedCount() {
                const count = $('.item-checkbox:checked').length;
                $('#selectedCount').text(`${count} items selected`);
            }

            // Initial count
            updateSelectedCount();

            // Individual checkbox change
            $('.item-checkbox').change(function() {
                updateSelectedCount();
            });

            // Select all functionality
            $('#selectAll, #selectAllHeader').change(function() {
                const isChecked = $(this).is(':checked');
                $('.item-checkbox').prop('checked', isChecked);
                $('#selectAll, #selectAllHeader').prop('checked', isChecked);
                updateSelectedCount();
            });

            // Select changed prices only
            $('#selectChangedOnly').change(function() {
                if ($(this).is(':checked')) {
                    $('.item-checkbox').prop('checked', false);
                    $('tr[data-price-status="increased"], tr[data-price-status="decreased"]').find('.item-checkbox').prop('checked', true);
                    $('#selectNewOnly').prop('checked', false);
                } else {
                    $('.item-checkbox').prop('checked', false);
                }
                updateSelectedCount();
            });

            // Select new items only
            $('#selectNewOnly').change(function() {
                if ($(this).is(':checked')) {
                    $('.item-checkbox').prop('checked', false);
                    $('tr[data-price-status="new"]').find('.item-checkbox').prop('checked', true);
                    $('#selectChangedOnly').prop('checked', false);
                } else {
                    $('.item-checkbox').prop('checked', false);
                }
                updateSelectedCount();
            });

            // Send to Oracle prices
            $('#sendToOracleBtn').click(function() {
                const selectedItems = [];
                $('.item-checkbox:checked').each(function() {
                    selectedItems.push($(this).val());
                });

                if (selectedItems.length === 0) {
                    alert('Please select at least one item to update.');
                    return;
                }

                const startDate = $('#priceStartDate').val();
                if (!startDate) {
                    alert('Please select a start date for the new prices.');
                    return;
                }

                if (!confirm(`Are you sure you want to update ${selectedItems.length} items in Oracle?\n\nCurrent prices will be end-dated and new prices will start from ${startDate}.`)) {
                    return;
                }

                const btn = $(this);
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating Oracle...');

                // Debug: Log what we're sending
                const uploadId = new URLSearchParams(window.location.search).get('upload_id');
                console.log('Sending:', {
                    selected_items: selectedItems,
                    upload_id: uploadId,
                    start_date: startDate
                });

                // Send as JSON to avoid PHP max_input_vars limit
                $.ajax({
                    url: '{{ route("price-lists.enter-new-prices") }}',
                    method: 'POST',
                    timeout: 600000, // 10 minutes timeout for large batch operations
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({
                        selected_items: selectedItems,
                        upload_id: parseInt(uploadId),
                        start_date: startDate
                    }),
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            if (response.redirect_url) {
                                window.location.href = response.redirect_url;
                            }
                        } else {
                            alert(response.message || 'Failed to update Oracle prices');
                        }
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        console.error('AJAX Error:', textStatus, errorThrown, xhr);

                        if (textStatus === 'timeout') {
                            alert('Request timed out. The operation may still be running on the server. Please check the logs and refresh the page.');
                            return;
                        }
                        if (xhr.status === 419) {
                            alert('Session expired. Please refresh the page and try again.');
                            window.location.reload();
                            return;
                        }
                        if (xhr.status === 0) {
                            alert('Network error or server not responding. The operation may have completed. Please check the logs.');
                            return;
                        }
                        const response = xhr.responseJSON;
                        alert(response?.message || 'Failed to update Oracle prices. Status: ' + xhr.status);
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
    </script>

    <style>
        .table tbody tr:hover {
            opacity: 0.9;
        }
        
        .badge {
            font-size: 0.75em;
        }
        
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }
        
        .table th {
            border-top: none;
        }

        /* Sticky table header */
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        /* Ensure sticky header has proper background and visibility */
        .table-dark.sticky-top th {
            background-color: #212529;
            border-bottom: 2px solid #454d55;
        }

        /* Add slight shadow for better visual separation */
        .sticky-top {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>

</x-app-layout>