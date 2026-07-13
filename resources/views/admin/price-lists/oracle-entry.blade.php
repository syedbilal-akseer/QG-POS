@push('title')
    Oracle Data Entry - Price Lists
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header -->
        <div class="d-flex justify-content-between mb-3">
            <div>
                <h3>Oracle Data Entry</h3>
                <p class="text-muted">Direct data entry to Oracle QG_PRICE_LIST_UPDATES table</p>
            </div>
            <div>
                <a href="{{ route('price-lists.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Price Lists
                </a>
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

        <!-- Data Entry Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Add New Price Update</h5>
                    </div>
                    <div class="card-body">
                        <form id="oracleEntryForm">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="item_code" class="form-label">Item Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="item_code" name="item_code" required 
                                           placeholder="e.g., 0001-0002">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="new_price" class="form-label">New Price <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="new_price" name="new_price" 
                                           step="0.01" min="0" required placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="currency_code" class="form-label">Currency Code</label>
                                    <select class="form-select" id="currency_code" name="currency_code">
                                        <option value="PKR" selected>PKR</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="list_header_id" class="form-label">List Header ID</label>
                                    <input type="number" class="form-control" id="list_header_id" name="list_header_id" 
                                           placeholder="Optional">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="list_line_id" class="form-label">List Line ID</label>
                                <input type="number" class="form-control" id="list_line_id" name="list_line_id" 
                                       placeholder="Optional">
                            </div>

                            <hr>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                                    <i class="fas fa-eraser"></i> Clear Form
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Submit to Oracle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reference Data -->
            <div class="col-lg-4">
                <!-- Sample Oracle Prices -->
                @if($samplePrices->count() > 0)
                <div class="card shadow mb-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-list"></i> Sample Oracle Prices</h6>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Price</th>
                                        <th>UOM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($samplePrices->take(5) as $price)
                                    <tr>
                                        <td><small><a href="#" class="text-decoration-none" onclick="fillItemCode('{{ $price['item_code'] }}')">{{ $price['item_code'] }}</a></small></td>
                                        <td><small>{{ number_format($price['list_price'], 2) }}</small></td>
                                        <td><small>{{ $price['uom'] }}</small></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Click item code to fill form</small>
                    </div>
                </div>
                @endif

                <!-- Status Guide -->
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Entry Guidelines</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><small><strong>Item Code:</strong> Must be valid Oracle item</small></li>
                            <li><small><strong>Price:</strong> New price to be updated</small></li>
                            <li><small><strong>Currency:</strong> Default is PKR</small></li>
                            <li><small><strong>Start Date:</strong> When price becomes effective</small></li>
                            <li><small><strong>Status:</strong> Will be 'Pending' initially</small></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Updates -->
        @if($recentUpdates->count() > 0)
        <div class="card shadow mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Oracle Updates</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>New Price</th>
                                <th>Currency</th>
                                <th>Status</th>
                                <th>Error Message</th>
                                <th>Processed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentUpdates as $update)
                            <tr>
                                <td><strong>{{ $update['item_code'] }}</strong></td>
                                <td>{{ number_format($update['new_price'], 2) }}</td>
                                <td>{{ $update['currency_code'] }}</td>
                                <td>
                                    <span class="badge bg-{{ $update['processed_flag'] === 'Y' ? 'success' : ($update['processed_flag'] === 'E' ? 'danger' : 'warning') }}">
                                        {{ $update['status_text'] }}
                                    </span>
                                </td>
                                <td>
                                    @if($update['error_message'])
                                        <small class="text-danger">{{ Str::limit($update['error_message'], 50) }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($update['processed_date'])
                                        <small>{{ \Carbon\Carbon::parse($update['processed_date'])->format('M d, Y H:i') }}</small>
                                    @else
                                        <span class="text-muted">Pending</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Form submission
        $('#oracleEntryForm').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.html();
            
            // Validate required fields
            const itemCode = $('#item_code').val().trim();
            const newPrice = $('#new_price').val();
            
            if (!itemCode) {
                alert('Item Code is required');
                $('#item_code').focus();
                return;
            }
            
            if (!newPrice || newPrice <= 0) {
                alert('Valid price is required');
                $('#new_price').focus();
                return;
            }
            
            // Show loading
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');
            
            // Prepare data
            const formData = {
                item_code: itemCode,
                new_price: parseFloat(newPrice),
                currency_code: $('#currency_code').val() || 'PKR',
                start_date: $('#start_date').val() || null,
                end_date: $('#end_date').val() || null,
                list_header_id: $('#list_header_id').val() || null,
                list_line_id: $('#list_line_id').val() || null,
                _token: '{{ csrf_token() }}'
            };
            
            // Submit via AJAX
            $.ajax({
                url: '{{ route("price-lists.update-oracle") }}',
                method: 'POST',
                data: {
                    selected_items: [itemCode],
                    single_entry: true,
                    entry_data: formData,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Price update submitted to Oracle successfully!');
                        clearForm();
                        // Optionally reload the page to show updated recent updates
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert(response.message || 'Failed to submit price update');
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert(response?.message || 'Failed to submit price update');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // Clear form function
        function clearForm() {
            $('#oracleEntryForm')[0].reset();
            $('#start_date').val('{{ date("Y-m-d") }}');
            $('#currency_code').val('PKR');
        }
        
        // Fill item code from sample data
        function fillItemCode(itemCode) {
            $('#item_code').val(itemCode);
        }
    </script>

    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }
        
        .table th {
            border-top: none;
        }
        
        .badge {
            font-size: 0.75em;
        }
    </style>

</x-app-layout>