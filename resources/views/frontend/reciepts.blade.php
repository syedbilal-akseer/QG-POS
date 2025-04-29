@push('title')
    {{ $pageTitle ?? 'Default Title' }}
@endpush

<x-app-layout>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/css/lightbox.min.css" rel="stylesheet">

    <div class="container mt-2" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">

        <!-- Header with Search and Add New Plan -->
        <div class="d-flex justify-content-between mb-3">


            <div class="relative w-full max-w-xs align-items-end">
                <!-- Search Input -->
                <input type="search" wire:model.live.debounce.500ms="tableSearch" placeholder="Search"
                    autocomplete="off"
                    class="block w-full bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded-lg focus:ring-gray-500 focus:border-gray-500 pr-10 p-2.5" />

                <!-- Search Icon (Positioned on Right) -->
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none search-icon">
                    <i class="fa fa-search text-gray-400"></i>
                </div>
            </div>

        </div>

        <!-- Table List Monthly Tour Plans -->
        <div class="table-responsive">
            <table class="table" :class="{ 'table-dark': darkMode, 'table-light': !darkMode }">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Balance Amount</th>
                        <th>Pay Type</th>
                        <th>Cheque No.</th>
                        <th>Account Title</th>
                        <th>Amount Paid</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>12 Dec 2024</td>
                        <td>#240025</td>
                        <td>Arif Bhatti</td>
                        <td>200,000</td>
                        <td>Cash</td>
                        <td>#95254</td>
                        <td>Arif Bhatii</td>
                        <td>200,000</td>
                        <td class="text-end">
                            <!-- Direct Link for Viewing Plan -->
                            <a href="#" class="btn btn-sm btn-details me-2" type="button" data-bs-toggle="modal"
                                data-bs-target="#verify">
                                <i class="fa fa-eye"></i> Verify
                            </a>

                            <!-- Direct Link for Editing Plan -->
                            <a href="#" class="btn btn-sm btn-edit me-2">
                                <i class="fa fa-pencil"></i> Edit
                            </a>

                            <a href="#" class="btn btn-sm btn-danger me-2">
                                <i class="fa fa-cancel"></i> Reject
                            </a>
                        </td>

                    </tr>

                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Showing 5 of 10 results
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
                <div class="modal-body">
                    <!-- Customer Info -->
                    <div class="mb-4">
                        <h5 class="font-weight-bold">Customer Info</h5>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Customer Id:</strong> 21</p>
                                <p><strong>Customer Name:</strong> Kamran Ghulam</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Outstanding Balance:</strong> PKR 15,000,000</p>
                                <p><strong>Limit:</strong> PKR 50,000,000</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="mb-4">
                        <h5 class="font-weight-bold">Payment Info</h5>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="cash" class="form-label">Cash</label>
                                <input readonly type="text" id="cash" class="form-control"
                                    value="PKR 50,000,000" placeholder="Enter Cash Amount">
                            </div>
                            <div class="col-md-6">
                                <label for="cheque" class="form-label">Cheque</label>
                                <input readonly type="text" id="cheque" class="form-control" value="123456789"
                                    placeholder="Enter Cheque Number">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="uploadImages" class="form-label">Upload Cheque Images</label>
                        <div class="row">
                            <div class="col-md-4">
                                <!-- Link to image with Lightbox2 attribute -->
                                <a href="{{ asset('assets/images/cheque-dummy.jpg') }}" data-lightbox="cheque-images" data-title="Cheque Image 1">
                                    <img src="{{ asset('assets/images/cheque-dummy.jpg') }}" class="img-fluid" alt="Cheque Image 1">
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ asset('assets/images/cheque-dummy.jpg') }}" data-lightbox="cheque-images" data-title="Cheque Image 2">
                                    <img src="{{ asset('assets/images/cheque-dummy.jpg') }}" class="img-fluid" alt="Cheque Image 2">
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="{{ asset('assets/images/cheque-dummy.jpg') }}" data-lightbox="cheque-images" data-title="Cheque Image 3">
                                    <img src="{{ asset('assets/images/cheque-dummy.jpg') }}" class="img-fluid" alt="Cheque Image 3">
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Description</label>
                        <textarea readonly id="description" class="form-control" rows="4" placeholder="Enter Description">Payment for January 2025 invoice. Cheque received from customer Kamran Ghulam.</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-details">Verify</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/js/lightbox.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lightbox.init();
        });
    </script>

</x-app-layout>
