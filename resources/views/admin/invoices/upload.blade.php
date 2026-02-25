<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Upload Invoice PDF') }}
            </h2>
            <a href="{{ route('invoices.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i>Back to Invoices
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Upload Instructions -->
                    <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-8">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4">
                            <i class="fas fa-info-circle mr-2"></i>How It Works
                        </h3>
                        <div class="text-blue-700 dark:text-blue-300 space-y-2">
                            <p><strong>1. Upload PDF:</strong> Select a PDF file containing multiple customer invoices</p>
                            <p><strong>2. Automatic Detection:</strong> System will scan for "Bill To:" sections and customer codes</p>
                            <p><strong>3. Customer Separation:</strong> Creates separate folders for each customer code (e.g., 11925, 1577)</p>
                            <p><strong>4. File Organization:</strong> Extracts and saves individual invoice PDFs per customer</p>
                        </div>
                        
                        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded">
                            <p class="text-yellow-800 dark:text-yellow-200 text-sm">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Expected Format:</strong> PDF should contain "Bill To: Customer Name 12345" pattern for proper detection
                            </p>
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        
                        <div class="space-y-6">
                            <!-- File Upload -->
                            <div>
                                <label for="invoice_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Invoice PDF File *
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-200">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                            <label for="invoice_file" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Upload a file</span>
                                                <input id="invoice_file" 
                                                       name="invoice_file" 
                                                       type="file" 
                                                       accept=".pdf"
                                                       class="sr-only" 
                                                       required
                                                       onchange="updateFileName(this)">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">PDF up to 50MB</p>
                                        <p id="fileName" class="text-sm text-green-600 dark:text-green-400 font-medium hidden"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Notes (Optional)
                                </label>
                                <textarea id="notes" 
                                          name="notes" 
                                          rows="3" 
                                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                          placeholder="Add any notes about this invoice batch...">{{ old('notes') }}</textarea>
                            </div>

                            <!-- Processing Preview -->
                            <div id="processingPreview" class="hidden bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-eye mr-2"></i>Expected Processing
                                </h4>
                                <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    <p><i class="fas fa-search mr-2"></i>Scan PDF for customer information</p>
                                    <p><i class="fas fa-users mr-2"></i>Identify unique customer codes</p>
                                    <p><i class="fas fa-folder mr-2"></i>Create customer folders</p>
                                    <p><i class="fas fa-cut mr-2"></i>Extract customer-specific pages</p>
                                    <p><i class="fas fa-save mr-2"></i>Save individual PDFs and database records</p>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-between">
                                <button type="submit" 
                                        id="submitBtn"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-upload mr-2"></i>
                                    <span id="submitText">Process Invoice PDF</span>
                                </button>
                                
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-clock mr-1"></i>
                                    Processing may take a few moments for large files
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sample Format Preview -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-file-alt mr-2"></i>Expected PDF Format
                    </h3>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <div class="text-sm font-mono text-gray-700 dark:text-gray-300 space-y-2">
                            <div class="border-b border-gray-300 dark:border-gray-600 pb-2">
                                <p><strong>Page 1-2:</strong></p>
                                <p class="text-blue-600 dark:text-blue-400">Bill To: M Babar Khan <strong>11925</strong></p>
                                <p>Invoice: 83264</p>
                                <p>Total Receivable: 3,252,740</p>
                            </div>
                            <div class="border-b border-gray-300 dark:border-gray-600 pb-2">
                                <p><strong>Page 3-5:</strong></p>
                                <p class="text-green-600 dark:text-green-400">Bill To: Wajeeh Alam Quadri <strong>1577</strong></p>
                                <p>Invoice: 83349</p>
                                <p>Total Receivable: 9,594,366</p>
                            </div>
                            <div>
                                <p><strong>Page 6:</strong></p>
                                <p class="text-purple-600 dark:text-purple-400">Bill To: Shakir Hameed M/S <strong>1575</strong></p>
                                <p>Invoice: 83266</p>
                                <p>Total Receivable: 887,661</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        <i class="fas fa-arrow-right mr-2"></i>
                        Will create 3 separate folders: <strong>11925</strong>, <strong>1577</strong>, <strong>1575</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = document.getElementById('fileName');
            const processingPreview = document.getElementById('processingPreview');
            
            if (input.files && input.files[0]) {
                fileName.textContent = '📄 ' + input.files[0].name;
                fileName.classList.remove('hidden');
                processingPreview.classList.remove('hidden');
            } else {
                fileName.classList.add('hidden');
                processingPreview.classList.add('hidden');
            }
        }

        // Handle form submission
        document.getElementById('uploadForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        });

        // Drag and drop functionality
        const dropZone = document.querySelector('[for="invoice_file"]').closest('.border-dashed');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        dropZone.addEventListener('drop', handleDrop, false);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            dropZone.classList.add('border-blue-400', 'bg-blue-50');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                document.getElementById('invoice_file').files = files;
                updateFileName(document.getElementById('invoice_file'));
            }
        }
    </script>
</x-app-layout>