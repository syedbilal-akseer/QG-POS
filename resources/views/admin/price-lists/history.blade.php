@push('title')
    Upload History
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header -->
        <div class="d-flex justify-content-between mb-3">
            <h3>Upload History</h3>
            <a href="{{ route('price-lists.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
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

        <!-- Upload History Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary">Price List Upload History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Status</th>
                                <th>Summary</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($uploads as $upload)
                            <tr>
                                <td>
                                    <strong>{{ $upload->original_filename }}</strong>
                                    <br>
                                    <small class="text-muted">{{ basename($upload->filename) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $upload->status_color }} rounded-pill">
                                        {{ strtoupper($upload->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row text-center">
                                        <div class="col">
                                            <strong class="text-primary">{{ $upload->total_rows }}</strong>
                                            <br><small>Total</small>
                                        </div>
                                        @if($upload->updated_rows > 0)
                                        <div class="col">
                                            <strong class="text-warning">{{ $upload->updated_rows }}</strong>
                                            <br><small>Updated</small>
                                        </div>
                                        @endif
                                        @if($upload->new_rows > 0)
                                        <div class="col">
                                            <strong class="text-success">{{ $upload->new_rows }}</strong>
                                            <br><small>New</small>
                                        </div>
                                        @endif
                                        @if($upload->error_rows > 0)
                                        <div class="col">
                                            <strong class="text-danger">{{ $upload->error_rows }}</strong>
                                            <br><small>Errors</small>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $upload->uploadedBy->name ?? 'Unknown' }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $upload->uploadedBy->email ?? '' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $upload->uploaded_at->format('M d, Y') }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $upload->uploaded_at->format('H:i:s') }}</small>
                                    <br>
                                    <small class="text-muted">{{ $upload->uploaded_at->diffForHumans() }}</small>
                                </td>
                                <td>
                                    @if($upload->notes)
                                        <span class="text-wrap">{{ Str::limit($upload->notes, 50) }}</span>
                                        @if(strlen($upload->notes) > 50)
                                            <button class="btn btn-sm btn-link p-0"
                                                    data-bs-toggle="tooltip"
                                                    title="{{ $upload->notes }}">
                                                ...more
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('price-lists.export-upload-history', ['uploadId' => $upload->id]) }}"
                                       class="btn btn-sm btn-success"
                                       data-bs-toggle="tooltip"
                                       title="Export this upload with error highlights and status">
                                        <i class="fas fa-file-excel"></i> Export
                                    </a>
                                </td>
                            </tr>

                            <!-- Error Details Modal -->
                            @if($upload->error_details && (count($upload->error_details['failures'] ?? []) > 0 || count($upload->error_details['errors'] ?? []) > 0))
                            <div class="modal fade" id="errorModal{{ $upload->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                                Error Details - {{ $upload->original_filename }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            @if(count($upload->error_details['failures'] ?? []) > 0)
                                                <div class="mb-4">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                        <h6 class="text-danger mb-0">Validation Failures ({{ count($upload->error_details['failures']) }} errors)</h6>
                                                    </div>
                                                    
                                                    <div class="alert alert-warning">
                                                        <small><strong>Note:</strong> These rows failed validation and were not processed. Please fix these issues in your Excel file and re-upload.</small>
                                                    </div>
                                                </div>
                                            @endif

                                            @if(count($upload->error_details['errors'] ?? []) > 0)
                                                <div class="mb-4">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <i class="fas fa-bug text-danger me-2"></i>
                                                        <h6 class="text-danger mb-0">Processing Errors ({{ count($upload->error_details['errors']) }} errors)</h6>
                                                    </div>
                                                    
                                                    <div class="alert alert-danger">
                                                        <small><strong>System Error:</strong> These errors occurred during file processing. Please contact support if the issue persists.</small>
                                                    </div>

                                                    <div class="list-group">
                                                        @foreach($upload->error_details['errors'] as $index => $error)
                                                            <div class="list-group-item d-flex align-items-start">
                                                                <span class="badge bg-danger me-3 mt-1">{{ $index + 1 }}</span>
                                                                <div>
                                                                    <i class="fas fa-exclamation-circle text-danger me-1"></i>
                                                                    {{ 
                                                                        is_array($error) 
                                                                            ? implode(', ', array_map(function($item) { 
                                                                                return is_array($item) ? json_encode($item) : (string)$item; 
                                                                            }, $error)) 
                                                                            : (string)$error 
                                                                    }}
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            @if(empty($upload->error_details['failures']) && empty($upload->error_details['errors']))
                                                <div class="text-center py-4">
                                                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                                    <h5 class="text-success">No Errors Found</h5>
                                                    <p class="text-muted">This upload completed successfully without any errors.</p>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No upload history found</p>
                                    <a href="{{ route('price-lists.upload') }}" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Upload Your First Price List
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($uploads->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Showing {{ $uploads->firstItem() ?? 0 }} to {{ $uploads->lastItem() ?? 0 }} 
                        of {{ $uploads->total() }} results
                    </div>
                    <div>
                        {{ $uploads->links() }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>

    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }

        .rounded-pill {
            border-radius: 10rem !important;
        }

        .text-wrap {
            word-wrap: break-word;
            max-width: 200px;
        }

        .table th {
            border-top: none;
            font-weight: 600;
        }

        .modal-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
    </style>

</x-app-layout>