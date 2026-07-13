@push('title')
    Price Lists Management
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header with Search and Add New Price List -->
        <div class="d-flex justify-content-between mb-3">
            <div>
                <h3>Price Lists</h3>
                @if($stats['last_sync_date'])
                    <small class="text-muted">Last Sync: {{ \Carbon\Carbon::parse($stats['last_sync_date'])->format('M d, Y h:i A') }}</small>
                @endif
                <br>
                @if($stats['last_price_updated_date'])
                    <small class="text-muted">Last Price Update: {{ \Carbon\Carbon::parse($stats['last_price_updated_date'])->format('M d, Y h:i A') }}</small>
                @endif
            </div>
            <div>
                <!-- Oracle Integration Buttons -->
                <button id="syncOraclePricesBtn" class="btn btn-info me-2">
                    <i class="fas fa-sync"></i> Sync Oracle Prices
                </button>

                <!-- Upload Excel for Oracle comparison (shown after sync) -->
                <form id="oracleUploadForm" class="d-inline-block me-2" style="display: none;">
                    @csrf
                    <input type="file" id="oracleFileInput" accept=".xlsx" style="display: none;">
                    <button type="button" id="uploadExcelBtn" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Upload Excel File
                    </button>
                </form>

                <!-- Export Button -->
                <a href="{{ route('price-lists.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                   class="btn btn-warning me-2">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>

                <!-- Regular upload (hidden after Oracle sync) -->
                <!-- <div id="regularUploadButtons">
                    <a href="{{ route('price-lists.upload') }}" class="btn btn-primary me-2">
                        <i class="fas fa-upload"></i> Upload Price List
                    </a>
                </div> -->

                <a href="{{ route('price-lists.history') }}" class="btn btn-outline-info">
                    <i class="fas fa-history"></i> Upload History
                </a>
            </div>
        </div>

        <!-- Oracle Sync Status -->
        <div id="oracleSyncStatus" class="alert alert-info" style="display: none;">
            <i class="fas fa-info-circle"></i> <span id="syncStatusText">Starting Oracle price sync...</span>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card border-primary shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-list fa-2x text-primary mb-2"></i>
                        <h5 class="card-title text-primary">Total Items</h5>
                        <h3 class="text-dark">{{ number_format($stats['total_items']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card border-warning shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                        <h5 class="card-title text-warning">Last Price Updated</h5>
                        <h3 class="text-dark">{{ number_format($stats['changed_items']) }}</h3>
                        @if($stats['last_price_updated_date'])
                            <small class="text-muted d-block mt-1">{{ \Carbon\Carbon::parse($stats['last_price_updated_date'])->format('M d, Y') }}</small>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card shadow h-100" style="border-color: #3B82F6;">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-2x mb-2" style="color: #3B82F6;"></i>
                        <h5 class="card-title" style="color: #3B82F6;">Corporate</h5>
                        <h3 class="text-dark">{{ number_format($stats['corporate_items']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card shadow h-100" style="border-color: #8B5CF6;">
                    <div class="card-body text-center">
                        <i class="fas fa-briefcase fa-2x mb-2" style="color: #8B5CF6;"></i>
                        <h5 class="card-title" style="color: #8B5CF6;">Trade</h5>
                        <h3 class="text-dark">{{ number_format($stats['trade_items']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card shadow h-100" style="border-color: #10B981;">
                    <div class="card-body text-center">
                        <i class="fas fa-store fa-2x mb-2" style="color: #10B981;"></i>
                        <h5 class="card-title" style="color: #10B981;">Wholesaler</h5>
                        <h3 class="text-dark">{{ number_format($stats['wholesaler_items']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card shadow h-100" style="border-color: #F59E0B;">
                    <div class="card-body text-center">
                        <i class="fas fa-handshake fa-2x mb-2" style="color: #F59E0B;"></i>
                        <h5 class="card-title" style="color: #F59E0B;">HBM</h5>
                        <h3 class="text-dark">{{ number_format($stats['hbm_items']) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="search" id="searchInput" placeholder="Search items..."
                    class="form-control" value="{{ request('search') }}" />
            </div>
            <div class="col-md-2">
                <select id="cityFilter" class="form-select">
                    <option value="">All Cities</option>
                    <option value="karachi" {{ request('city') == 'karachi' ? 'selected' : '' }}>Karachi</option>
                    <option value="lahore" {{ request('city') == 'lahore' ? 'selected' : '' }}>Lahore</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="priceTypeFilter" class="form-select">
                    <option value="">All Types</option>
                    <option value="corporate" {{ request('price_type') == 'corporate' ? 'selected' : '' }}>Corporate</option>
                    <option value="trade" {{ request('price_type') == 'trade' ? 'selected' : '' }}>Trade</option>
                    <option value="wholesaler" {{ request('price_type') == 'wholesaler' ? 'selected' : '' }}>Wholesaler</option>
                    <option value="hbm" {{ request('price_type') == 'hbm' ? 'selected' : '' }}>HBM</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="changedFilter" class="form-select">
                    <option value="">All Items</option>
                    <option value="1" {{ request('changed_only') ? 'selected' : '' }}>Changed Only</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" id="applyFilters" class="btn btn-secondary">
                    <i class="fa fa-filter"></i>
                </button>
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

        <!-- Price List Matrix Table -->
        <div class="table-responsive">
            <table class="table table-sm" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">
                <thead>
                    <tr>
                        <th rowspan="2" class="align-middle" style="width: 8%;">Item Code</th>
                        <th rowspan="2" class="align-middle" style="width: 20%;">Description</th>
                        <th rowspan="2" class="align-middle" style="width: 5%;">UOM</th>
                        <th colspan="7" class="text-center">Price List Names</th>
                        <th rowspan="2" class="align-middle" style="width: 8%;">Effective Date</th>
                        <th rowspan="2" class="align-middle" style="width: 8%;">Updated</th>
                        <th rowspan="2" class="align-middle text-end" style="width: 5%;">Actions</th>
                    </tr>
                    <tr>
                        @foreach($priceListOrder as $priceListName)
                        <th class="text-center" style="min-width: 100px; font-size: 0.8em;">
                            @if($priceListName === 'Karachi - Trade Price')
                                <span class="badge bg-info">KHI Trade</span>
                            @elseif($priceListName === 'Karachi - Wholesale')
                                <span class="badge bg-success">KHI Wholesale</span>
                            @elseif($priceListName === 'Karachi - Corporate')
                                <span class="badge bg-primary">KHI Corporate</span>
                            @elseif($priceListName === 'Lahore - Trade Price')
                                <span class="badge bg-info">LHR Trade</span>
                            @elseif($priceListName === 'Lahore - Wholesale')
                                <span class="badge bg-success">LHR Wholesale</span>
                            @elseif($priceListName === 'Lahore - Corporate')
                                <span class="badge bg-primary">LHR Corporate</span>
                            @elseif($priceListName === 'QG HBM')
                                <span class="badge bg-warning text-dark">QG HBM</span>
                            @endif
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($prices as $item)
                    <tr class="{{ $item['has_changes'] ? 'table-warning' : '' }}">
                        <td>
                            <strong>{{ $item['item_code'] }}</strong>
                            @if($item['has_changes'])
                                <br><span class="badge bg-warning text-dark">CHANGED</span>
                            @endif
                        </td>
                        <td>
                            <small>{{ $item['item_description'] }}</small>
                        </td>
                        <td>{{ $item['uom'] }}</td>
                        
                        @foreach($priceListOrder as $priceListName)
                        @php
                            $price = $item['prices'][$priceListName];
                        @endphp
                        <td class="text-center position-relative">
                            @if($price['exists'])
                                <div class="price-cell" data-price-id="{{ $price['id'] }}">
                                    <span class="price-display fw-bold {{ $price['price_changed'] ? 'price-changed' : 'price-normal' }}">
                                        {{ number_format($price['list_price'], 0) }}
                                    </span>
                                    @if($price['price_changed'])
                                        <br><small class="price-previous">
                                            ({{ $price['previous_price'] ? number_format($price['previous_price'], 0) : '-' }})
                                        </small>
                                    @endif
                                </div>
                            @else
                                <span class="price-empty">-</span>
                            @endif
                        </td>
                        @endforeach

                        <td>
                            @if($item['effective_date'])
                                <small>{{ \Carbon\Carbon::parse($item['effective_date'])->format('M d, Y') }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($item['updated_at'])
                                <small>{{ \Carbon\Carbon::parse($item['updated_at'])->format('M d, Y') }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <!-- Edit functionality removed - use Oracle comparison workflow -->
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 3 + count($priceListOrder) + 3 }}" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No price list items found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Showing {{ $prices->firstItem() ?? 0 }} to {{ $prices->lastItem() ?? 0 }} 
                of {{ $prices->total() }} results
            </div>
            <div>
                {{ $prices->links() }}
            </div>
        </div>

        <!-- Recent Uploads -->
        @if($recentUploads->count() > 0)
        <div class="mt-4">
            <h5>Recent Uploads</h5>
            <div class="table-responsive">
                <table class="table table-sm" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Status</th>
                            <th>Summary</th>
                            <th>Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentUploads as $upload)
                        <tr>
                            <td>{{ $upload->original_filename }}</td>
                            <td>
                                <span class="badge bg-{{ $upload->status_color }}">
                                    {{ strtoupper($upload->status) }}
                                </span>
                            </td>
                            <td>{{ $upload->summary }}</td>
                            <td>
                                {{ $upload->uploaded_at->format('M d, Y H:i') }}<br>
                                <small class="text-muted">{{ $upload->uploadedBy->name }}</small>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    <script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Filter functionality
        document.getElementById('applyFilters').addEventListener('click', function() {
            const search = document.getElementById('searchInput').value;
            const city = document.getElementById('cityFilter').value;
            const priceType = document.getElementById('priceTypeFilter').value;
            const changed = document.getElementById('changedFilter').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (city) params.append('city', city);
            if (priceType) params.append('price_type', priceType);
            if (changed) params.append('changed_only', changed);

            window.location.href = '{{ route("price-lists.index") }}?' + params.toString();
        });

        // Search on Enter
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('applyFilters').click();
            }
        });

        $(document).ready(function() {
            // Oracle Integration Functionality
            
            // Sync Oracle Prices with batch processing
            $('#syncOraclePricesBtn').click(function() {
                const btn = $(this);
                const originalText = btn.html();
                
                btn.prop('disabled', true);
                $('#oracleSyncStatus').show();
                
                let currentPage = 1;
                let totalSynced = 0;
                
                function syncBatch(page) {
                    $.ajax({
                        url: '{{ route("price-lists.sync-oracle") }}',
                        method: 'POST',
                        data: {
                            page: page,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            console.log('Sync batch response:', response);

                            if (response.success) {
                                totalSynced = response.total_synced;

                                // Update button and status with batch info
                                if (response.has_more_pages) {
                                    btn.html(`<i class="fas fa-spinner fa-spin"></i> Syncing batch ${response.current_page} of ${response.total_pages}`);
                                    $('#syncStatusText').text(`${response.progress_text} - ${response.progress_percentage}% complete`);

                                    // Continue with next batch
                                    setTimeout(() => syncBatch(page + 1), 500);
                                } else {
                                    // All batches completed
                                    $('#oracleUploadForm').show();
                                    $('#regularUploadButtons').hide();

                                    // Change button to show it's synced
                                    btn.removeClass('btn-info').addClass('btn-success')
                                       .html('<i class="fas fa-check"></i> Oracle Synced')
                                       .prop('disabled', false);

                                    $('#syncStatusText').text(`Oracle sync completed! ${response.total_count} items processed in ${response.total_pages} batches.`);
                                }
                            } else {
                                alert(response.message || 'Failed to sync Oracle prices');
                                btn.prop('disabled', false).html(originalText);
                                $('#oracleSyncStatus').hide();
                            }
                        },
                        error: function(xhr) {
                            console.error('Sync batch error:', xhr);
                            const response = xhr.responseJSON;
                            alert(response?.message || `Failed to sync Oracle prices at batch ${page}`);
                            btn.prop('disabled', false).html(originalText);
                            $('#oracleSyncStatus').hide();
                        }
                    });
                }
                
                // Start the batch processing
                syncBatch(1);
            });
            
            
            // Excel file upload for Oracle comparison
            $('#uploadExcelBtn').click(function() {
                $('#oracleFileInput').click();
            });
            
            $('#oracleFileInput').change(function() {
                const file = this.files[0];
                if (file) {
                    if (file.type !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                        alert('Please select an Excel (.xlsx) file');
                        return;
                    }
                    
                    // Show loading
                    const btn = $('#uploadExcelBtn');
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
                    
                    // Create form data
                    const formData = new FormData();
                    formData.append('price_list_file', file);
                    formData.append('notes', 'Oracle comparison upload');
                    formData.append('_token', '{{ csrf_token() }}');
                    
                    // Upload and process
                    $.ajax({
                        url: '{{ route("price-lists.store") }}',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            console.log('Excel upload response:', response);
                            
                            // Display debug data if available
                            if (response.debug_data) {
                                console.group('📊 Excel Processing Debug Information');
                                
                                if (response.debug_data.file_info) {
                                    console.log('📄 File Information:', response.debug_data.file_info);
                                }
                                
                                if (response.debug_data.structure_info) {
                                    console.log('🏗️ Excel Structure Analysis:', response.debug_data.structure_info);
                                    if (!response.debug_data.structure_info.has_data_rows) {
                                        console.warn('⚠️ Excel file has no data rows - only headers detected!');
                                    }
                                }
                                
                                if (response.debug_data.structure_error) {
                                    console.error('❌ Excel Structure Analysis Failed:', response.debug_data.structure_error);
                                }
                                
                                if (response.debug_data.raw_structure) {
                                    console.group('📊 Raw Excel Structure (First 3 rows)');
                                    Object.entries(response.debug_data.raw_structure).forEach(([rowNum, rowData]) => {
                                        console.log(`Row ${rowNum}:`, rowData);
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.debug_data.raw_data && response.debug_data.raw_data.length > 0) {
                                    console.group('🔍 Raw Excel Data (First 3 rows)');
                                    response.debug_data.raw_data.forEach((row, index) => {
                                        console.log(`Row ${row.row_number}:`, row);
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.debug_data.columns && response.debug_data.columns.length > 0) {
                                    console.group('📋 Available Excel Columns');
                                    response.debug_data.columns.forEach((col, index) => {
                                        console.log(`Row ${col.row_number} columns:`, col.keys);
                                        console.log(`Row ${col.row_number} sample values:`, col.sample_values);
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.debug_data.mapping && response.debug_data.mapping.length > 0) {
                                    console.group('🔄 Column Mapping Results');
                                    response.debug_data.mapping.forEach((map, index) => {
                                        console.log(`Row ${map.row_number} mapping:`, map);
                                        if (map.missing_required_fields) {
                                            const missing = Object.entries(map.missing_required_fields)
                                                .filter(([key, value]) => value)
                                                .map(([key, value]) => key);
                                            if (missing.length > 0) {
                                                console.warn(`Row ${map.row_number} missing fields:`, missing);
                                            }
                                        }
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.import_stats) {
                                    console.log('📈 Import Statistics:', response.import_stats);
                                }
                                
                                console.groupEnd();
                            }
                            
                            // Check if Laravel returned a redirect (it will be in the response)
                            // For AJAX, Laravel returns status 200 with success, so we need to handle redirect manually
                            if (response.redirect_url) {
                                window.location.href = response.redirect_url;
                            } else {
                                // Fallback: try to extract upload_id from current response or reload page
                                if (response.upload_id) {
                                    window.location.href = '{{ route("price-lists.review-comparison") }}?upload_id=' + response.upload_id;
                                } else {
                                    // Reload the page to show any success messages
                                    window.location.reload();
                                }
                            }
                        },
                        error: function(xhr) {
                            console.error('Excel upload error:', xhr);
                            console.error('Response:', xhr.responseJSON);
                            const response = xhr.responseJSON;
                            
                            // Display debug data even for errors
                            if (response && response.debug_data) {
                                console.group('⚠️ Excel Processing Debug Information (Error Case)');
                                
                                if (response.debug_data.file_info) {
                                    console.log('📄 File Information:', response.debug_data.file_info);
                                }
                                
                                if (response.debug_data.structure_info) {
                                    console.log('🏗️ Excel Structure Analysis:', response.debug_data.structure_info);
                                    if (!response.debug_data.structure_info.has_data_rows) {
                                        console.warn('⚠️ Excel file has no data rows - only headers detected!');
                                    }
                                }
                                
                                if (response.debug_data.structure_error) {
                                    console.error('❌ Excel Structure Analysis Failed:', response.debug_data.structure_error);
                                }
                                
                                if (response.debug_data.raw_structure) {
                                    console.group('📊 Raw Excel Structure (First 3 rows)');
                                    Object.entries(response.debug_data.raw_structure).forEach(([rowNum, rowData]) => {
                                        console.log(`Row ${rowNum}:`, rowData);
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.debug_data.raw_data && response.debug_data.raw_data.length > 0) {
                                    console.group('🔍 Raw Excel Data (First 3 rows)');
                                    response.debug_data.raw_data.forEach((row, index) => {
                                        console.log(`Row ${row.row_number}:`, row);
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.debug_data.columns && response.debug_data.columns.length > 0) {
                                    console.group('📋 Available Excel Columns');
                                    response.debug_data.columns.forEach((col, index) => {
                                        console.log(`Row ${col.row_number} columns:`, col.keys);
                                        console.log(`Row ${col.row_number} sample values:`, col.sample_values);
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.debug_data.mapping && response.debug_data.mapping.length > 0) {
                                    console.group('🔄 Column Mapping Results');
                                    response.debug_data.mapping.forEach((map, index) => {
                                        console.log(`Row ${map.row_number} mapping:`, map);
                                        if (map.missing_required_fields) {
                                            const missing = Object.entries(map.missing_required_fields)
                                                .filter(([key, value]) => value)
                                                .map(([key, value]) => key);
                                            if (missing.length > 0) {
                                                console.warn(`Row ${map.row_number} missing fields:`, missing);
                                            }
                                        }
                                    });
                                    console.groupEnd();
                                }
                                
                                if (response.import_stats) {
                                    console.log('📈 Import Statistics:', response.import_stats);
                                }
                                
                                console.groupEnd();
                            }
                            if (response && response.errors) {
                                let errorMessage = 'Validation failed:\n';
                                for (const field in response.errors) {
                                    errorMessage += `- ${response.errors[field].join(', ')}\n`;
                                }
                                alert(errorMessage);
                            } else {
                                alert(response?.message || 'Failed to process file');
                            }
                        },
                        complete: function() {
                            btn.prop('disabled', false).html('<i class="fas fa-file-excel"></i> Upload Excel File');
                            // Reset file input
                            $('#oracleFileInput').val('');
                        }
                    });
                }
            });

            // Inline editing functionality removed - use Oracle comparison workflow for price updates
        });
    </script>

    <style>
        .table-warning {
            background-color: rgba(255, 193, 7, 0.15) !important;
            border-left: 4px solid #ffc107;
        }

        /* Better contrast for warning rows */
        .table-warning td {
            background-color: rgba(255, 193, 7, 0.08) !important;
        }

        /* Dark theme warning background */
        [data-bs-theme="dark"] .table-warning,
        .table-dark.table-warning {
            background-color: rgba(255, 193, 7, 0.2) !important;
            border-left: 4px solid #ffc107;
        }

        [data-bs-theme="dark"] .table-warning td,
        .table-dark.table-warning td {
            background-color: rgba(255, 193, 7, 0.12) !important;
        }

        .badge {
            font-size: 0.75em;
        }

        .price-edit {
            max-width: 80px;
            font-size: 0.85em;
        }

        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }

        .table th {
            border-top: none;
            font-size: 0.9em;
        }

        .table td {
            font-size: 0.9em;
            vertical-align: middle;
        }

        .price-cell {
            min-height: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* Price display colors for light theme */
        .price-normal {
            color: #2c3e50;
        }

        .price-changed {
            color: #dc3545 !important;
            text-shadow: 0 0 2px rgba(255, 255, 255, 0.8);
        }

        .price-previous {
            color: #6c757d;
        }

        .price-empty {
            color: #6c757d;
        }

        /* Special contrast for warning background (changed rows) */
        .table-warning .price-normal {
            color: #495057 !important;
            font-weight: 600;
        }

        .table-warning .price-changed {
            color: #721c24 !important;
            font-weight: 700;
            text-shadow: 0 0 3px rgba(255, 255, 255, 0.9);
        }

        .table-warning .price-previous {
            color: #495057 !important;
        }

        .table-warning .price-empty {
            color: #6c757d !important;
        }

        /* Dark theme adjustments */
        [data-bs-theme="dark"] .price-normal,
        .table-dark .price-normal {
            color: #e9ecef !important;
        }

        [data-bs-theme="dark"] .price-changed,
        .table-dark .price-changed {
            color: #ff6b6b !important;
            text-shadow: 0 0 3px rgba(0, 0, 0, 0.8);
        }

        [data-bs-theme="dark"] .price-previous,
        .table-dark .price-previous {
            color: #adb5bd !important;
        }

        [data-bs-theme="dark"] .price-empty,
        .table-dark .price-empty {
            color: #6c757d !important;
        }

        /* Dark theme warning background adjustments */
        [data-bs-theme="dark"] .table-warning .price-normal,
        .table-dark.table-warning .price-normal {
            color: #212529 !important;
            font-weight: 600;
        }

        [data-bs-theme="dark"] .table-warning .price-changed,
        .table-dark.table-warning .price-changed {
            color: #721c24 !important;
            font-weight: 700;
            text-shadow: 0 0 4px rgba(255, 255, 255, 0.9);
        }

        [data-bs-theme="dark"] .table-warning .price-previous,
        .table-dark.table-warning .price-previous {
            color: #495057 !important;
        }

        /* Ensure visibility in dark mode */
        [data-bs-theme="dark"] .table td,
        .table-dark td {
            color: #e9ecef;
        }

        /* Matrix table styling */
        .table-responsive {
            font-size: 0.9em;
        }

        /* Color coding for different price list types */

        /* Highlight changed prices with animation */
        .price-changed {
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .table-responsive {
                font-size: 0.8em;
            }
            .badge {
                font-size: 0.65em;
            }
        }
    </style>

</x-app-layout>