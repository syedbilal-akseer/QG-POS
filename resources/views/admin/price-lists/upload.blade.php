@push('title')
    Upload Price List
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header -->
        <div class="d-flex justify-content-between mb-3">
            <h3>Upload Price List</h3>
            <a href="{{ route('price-lists.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="row">
            <!-- Upload Form -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-primary">Upload Excel File</h6>
                    </div>
                    <div class="card-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('price-lists.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                            @csrf
                            
                            <div class="mb-4">
                                <label for="price_list_file" class="form-label">
                                    <strong>Excel File (.xlsx)</strong>
                                </label>
                                <input type="file" class="form-control" id="price_list_file" name="price_list_file" 
                                       accept=".xlsx" required>
                                <div class="form-text">
                                    Maximum file size: 10MB. 
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Add any notes about this upload...">{{ old('notes') }}</textarea>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary" id="uploadBtn">
                                    <i class="fas fa-upload"></i> Upload & Process
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-info">
                            <i class="fas fa-info-circle"></i> Instructions
                        </h6>
                    </div>
                    <div class="card-body">
                        <h6 class="text-primary">Excel Format - Matrix Layout:</h6>
                        <div class="mb-3">
                            <strong>Basic Product Columns:</strong>
                            <ul class="list-unstyled small">
                                <li>• <strong>Item Code</strong> - Unique identifier</li>
                                <li>• <strong>Item Description</strong> - Product name</li>
                                <li>• <strong>UOM</strong> - Unit of measure</li>
                                <li>• <strong>Brand</strong> - Brand name (optional)</li>
                                <li>• <strong>Major_Desc</strong> - Category (optional)</li>
                                <li>• <strong>Minor_Desc</strong> - Subcategory (optional)</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Price List Columns (with prices):</strong>
                            <ul class="list-unstyled small">
                                <li>• <strong>Karachi - Corporate</strong></li>
                                <li>• <strong>Karachi - Wholesale</strong></li>
                                <li>• <strong>Karachi - Trade Price</strong></li>
                                <li>• <strong>Lahore - Corporate</strong></li>
                                <li>• <strong>Lahore - Wholesale</strong></li>
                                <li>• <strong>Lahore - Trade Price</strong></li>
                                <li>• <strong>QG HBM</strong></li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <strong>Matrix Format:</strong> Each row = 1 product, each price column = different city/type.
                                Leave price cells blank to skip updates for that price list.
                            </small>
                        </div>

                        <h6 class="text-primary mt-3">Price Types & Colors:</h6>
                        <ul class="list-unstyled">
                            <li>
                                <span class="badge" style="background-color: #3B82F6; color: white;">CORPORATE</span>
                                Blue
                            </li>
                            <li>
                                <span class="badge" style="background-color: #10B981; color: white;">WHOLESALER</span>
                                Green
                            </li>
                            <li>
                                <span class="badge" style="background-color: #F59E0B; color: white;">HBM</span>
                                Amber
                            </li>
                        </ul>

                        <div class="alert alert-warning mt-3">
                            <small>
                                <strong>Note:</strong> The system will automatically detect price changes 
                                and highlight them in the dashboard. Existing items will be updated, 
                                new items will be created.
                            </small>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    $(document).ready(function() {
        const uploadForm = $('#uploadForm');
        const uploadBtn = $('#uploadBtn');
        const fileInput = $('#price_list_file');

        // File validation
        fileInput.change(function() {
            const file = this.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // Convert to MB
                const maxSize = 10; // 10MB
                
                if (fileSize > maxSize) {
                    alert(`File size (${fileSize.toFixed(2)}MB) exceeds maximum allowed size (${maxSize}MB)`);
                    $(this).val('');
                    return;
                }

                const allowedTypes = [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel'
                ];
                
                if (!allowedTypes.includes(file.type) && !file.name.toLowerCase().endsWith('.xlsx')) {
                    alert('Please select a valid Excel file (.xlsx)');
                    $(this).val('');
                    return;
                }
            }
        });

        // Form submission
        uploadForm.submit(function(e) {
            if (!fileInput.val()) {
                e.preventDefault();
                alert('Please select a file to upload');
                return;
            }

            // Show loading state
            uploadBtn.prop('disabled', true)
                     .html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            // Show progress message
            $('<div class="alert alert-info mt-3" id="progressAlert">' +
              '<i class="fas fa-clock"></i> ' +
              'Processing your file. This may take a few minutes for large files...' +
              '</div>').insertAfter(uploadForm);
        });
    });
    </script>

    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }

        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .badge {
            font-size: 0.75em;
            margin-right: 8px;
        }

        .list-unstyled li {
            margin-bottom: 5px;
        }
    </style>

</x-app-layout>