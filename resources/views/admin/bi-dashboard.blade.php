<x-layout :pageTitle="$pageTitle">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Business Intelligence Dashboard</h2>
            <p class="text-gray-600 dark:text-gray-400">
                Real-time analytics and insights from Oracle EBS
                @if(isset($rlsRoles) && count($rlsRoles) > 0)
                    <span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold
                        @if(in_array('National', $rlsRoles)) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                        @elseif(in_array('KHI', $rlsRoles)) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif(in_array('LHR', $rlsRoles)) bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                        @elseif(in_array('Salesperson', $rlsRoles)) bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                        @endif">
                        @if(in_array('Salesperson', $rlsRoles))
                            Personal View - {{ $userName ?? 'Salesperson' }}
                        @else
                            {{ implode(', ', $rlsRoles) }} View
                        @endif
                    </span>
                @endif
            </p>
        </div>

        <!-- Page Navigation Tabs -->
        @if(isset($reportPages) && count($reportPages) > 0)
        <div class="mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-2">
                <div class="flex space-x-2" id="page-tabs">
                    @foreach($reportPages as $index => $page)
                    <button
                        onclick="switchPage('{{ $page['id'] }}')"
                        id="tab-{{ $page['id'] }}"
                        class="px-4 py-2 rounded-md font-medium transition-colors duration-200
                               {{ $index === 0 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}"
                        data-page-id="{{ $page['id'] }}">
                        {{ $page['name'] }}
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Power BI Embedded Container -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4">
            <div id="powerbi-container" style="height: 800px; width: 100%;">
                <!-- Loading State -->
                <div id="loading" class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400">Loading Power BI Dashboard...</p>
                    </div>
                </div>

                <!-- Power BI Report will be embedded here -->
                <div id="powerbi-report" style="height: 100%; width: 100%; display: none;"></div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
            <p>Dashboard updates automatically. Last refresh: <span id="last-refresh">{{ now()->format('F j, Y g:i A') }}</span></p>
        </div>
    </div>

    <!-- Power BI Embedded JS Library -->
    <script src="https://cdn.jsdelivr.net/npm/powerbi-client@2.23.1/dist/powerbi.min.js"></script>

    <script>
        // Global variables to store the report instance and filters
        let globalReport = null;
        let currentPageId = null;
        let userFilters = null; // Will be set from Laravel

        // Wait for DOM and Power BI library to load
        document.addEventListener('DOMContentLoaded', function() {
            // Power BI configuration from Laravel
            const embedConfig = @json($embedConfig);

            // User-specific filters from Laravel (assign to global variable)
            userFilters = @json($filters ?? ['type' => 'none']);

            // Check if Power BI library is loaded
            if (typeof window.powerbi === 'undefined') {
                console.error('Power BI library not loaded');
                document.getElementById('loading').innerHTML = `
                    <div class="text-center text-red-600">
                        <p class="font-semibold">Power BI library failed to load</p>
                        <p class="text-sm mt-2">Please refresh the page</p>
                    </div>
                `;
                return;
            }

            // Get the embed container
            const reportContainer = document.getElementById('powerbi-report');
            const loadingContainer = document.getElementById('loading');

            // Configuration for embedding (using string literals instead of models constants)
            const config = {
                type: 'report',
                tokenType: 1, // Embed token type
                accessToken: embedConfig.accessToken,
                embedUrl: embedConfig.embedUrl,
                id: embedConfig.id,
                permissions: 0, // Read permissions
                settings: {
                    panes: {
                        filters: {
                            expanded: false,
                            visible: true
                        },
                        pageNavigation: {
                            visible: false // Hide built-in navigation, we'll use our custom tabs
                        }
                    },
                    background: 0, // Transparent
                    layoutType: 2, // Custom
                    customLayout: {
                        displayOption: 1 // FitToWidth
                    }
                }
            };

            // Embed the report
            const report = window.powerbi.embed(reportContainer, config);
            globalReport = report; // Store globally for page switching

        // Handle loaded event
        report.on('loaded', function() {
            console.log('Power BI Report loaded successfully');
            loadingContainer.style.display = 'none';
            reportContainer.style.display = 'block';
            document.getElementById('last-refresh').textContent = new Date().toLocaleString();
        });

        // Handle rendered event
        report.on('rendered', function() {
            console.log('Power BI Report rendered');

            // Log filtering configuration being used
            console.log('🔐 Filtering Configuration:');
            console.log('  Mode: Client-Side Filtering (All data loaded from Power BI)');
            console.log('  User Filters:', userFilters);
            console.log('  User Name:', '{{ $userName ?? "unknown" }}');

            // Apply user-specific filters after render
            setTimeout(() => {
                console.log('🔧 Applying client-side filters...');
                applyUserFilters();

                // Detect structure after filters applied
                setTimeout(() => {
                    detectReportStructure();
                }, 500);
            }, 1000);
        });

        // Handle errors (ignore mobile layout warnings)
        report.on('error', function(event) {
            const error = event.detail;

            // Ignore mobile layout warnings - they're not critical
            if (error.message === 'mobileLayoutError') {
                console.log('Power BI Info:', error.detailedMessage);
                return;
            }

            // Log real errors
            console.error('Power BI Error:', error);

            // Check if it's an RLS/model loading error
            if (error.message && error.message.includes('FailedToLoadModel')) {
                console.error('RLS Configuration Issue - The effective identity or role may not match dataset security settings');
                loadingContainer.innerHTML = `
                    <div class="text-center text-orange-600">
                        <svg class="h-12 w-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="font-semibold">Dashboard Security Configuration Issue</p>
                        <p class="text-sm mt-2">The Row-Level Security (RLS) settings may need adjustment.</p>
                        <p class="text-xs mt-1 text-gray-500">Error: ${error.message}</p>
                        <p class="text-xs mt-1 text-gray-500">Contact your Power BI administrator to verify RLS roles.</p>
                    </div>
                `;
                return;
            }

            // Only show error UI for other critical errors
            loadingContainer.innerHTML = `
                <div class="text-center text-red-600">
                    <svg class="h-12 w-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="font-semibold">Failed to load dashboard</p>
                    <p class="text-sm mt-2">${error.detailedMessage || 'Please contact your administrator'}</p>
                </div>
            `;
        });

        // Auto-refresh token before expiry (refresh 5 minutes before expiration)
        const tokenExpiry = new Date(embedConfig.tokenExpiry);
        const refreshTime = tokenExpiry.getTime() - (5 * 60 * 1000); // 5 minutes before expiry
        const now = new Date().getTime();
        const timeout = refreshTime - now;

        if (timeout > 0) {
            setTimeout(function() {
                refreshEmbedToken();
            }, timeout);
        }

        // Function to refresh embed token
        function refreshEmbedToken() {
            fetch('{{ route("admin.bi-dashboard.refresh-token") }}', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the report with new token
                    report.setAccessToken(data.data.accessToken)
                        .then(() => {
                            console.log('Token refreshed successfully');
                            document.getElementById('last-refresh').textContent = new Date().toLocaleString();

                            // Schedule next refresh
                            const newExpiry = new Date(data.data.tokenExpiry);
                            const newRefreshTime = newExpiry.getTime() - (5 * 60 * 1000);
                            const newTimeout = newRefreshTime - new Date().getTime();

                            if (newTimeout > 0) {
                                setTimeout(refreshEmbedToken, newTimeout);
                            }
                        })
                        .catch(error => {
                            console.error('Failed to update token:', error);
                        });
                }
            })
            .catch(error => {
                console.error('Failed to refresh token:', error);
            });
        }

            // Handle page visibility change (refresh when user comes back to tab)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Check if token is expired or will expire soon
                    const tokenExpiry = new Date(embedConfig.tokenExpiry);
                    const now = new Date();
                    const minutesUntilExpiry = (tokenExpiry - now) / (1000 * 60);

                    if (minutesUntilExpiry < 10) {
                        refreshEmbedToken();
                    }
                }
            });
        }); // End of DOMContentLoaded

        // Function to detect report structure (tables and columns)
        async function detectReportStructure() {
            if (!globalReport) {
                console.log('Report not ready yet');
                return;
            }

            console.log('🔍 Starting structure detection...');

            try {
                const reportInfo = {
                    timestamp: new Date().toISOString(),
                    userFilter: userFilters,
                    pages: [],
                    activeFilters: []
                };

                const pages = await globalReport.getPages();
                console.log('📊 Report has', pages.length, 'pages');

                // Only get info from active page for speed
                const activePage = pages.find(p => p.isActive);

                if (activePage) {
                    const pageInfo = {
                        name: activePage.name,
                        displayName: activePage.displayName,
                        isActive: true,
                        visuals: [],
                        filters: []
                    };

                    // Get page filters (THIS IS THE MOST IMPORTANT!)
                    try {
                        const pageFilters = await activePage.getFilters();
                        console.log('🔍 Active page has', pageFilters.length, 'filters');

                        pageInfo.filters = pageFilters.map(f => ({
                            target: f.target,
                            operator: f.operator,
                            values: f.values
                        }));

                        // Log filters to console
                        if (pageFilters.length > 0) {
                            console.log('📌 EXISTING FILTERS FOUND:');
                            pageFilters.forEach((f, i) => {
                                if (f.target) {
                                    console.log(`  Filter ${i+1}: Table="${f.target.table}", Column="${f.target.column}"`);
                                }
                            });
                        }
                    } catch (e) {
                        console.log('⚠️ Could not get page filters:', e.message);
                        pageInfo.filters = [];
                    }

                    // Get visuals count only
                    try {
                        const visuals = await activePage.getVisuals();
                        pageInfo.visualCount = visuals.length;
                        console.log('📊 Active page has', visuals.length, 'visuals');
                    } catch (e) {
                        pageInfo.visualCount = 0;
                    }

                    reportInfo.pages.push(pageInfo);
                }

                // Get report-level filters
                try {
                    const reportFilters = await globalReport.getFilters();
                    reportInfo.activeFilters = reportFilters;
                    console.log('🔍 Report has', reportFilters.length, 'report-level filters');
                } catch (e) {
                    console.log('⚠️ Could not get report filters:', e.message);
                }

                console.log('📄 Full Report Structure:', reportInfo);

                // Send to Laravel to save as file
                console.log('💾 Saving structure to server...');

                fetch('{{ route("admin.bi-dashboard.save-structure") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(reportInfo)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log('✅ Report structure saved!');
                        console.log('📥 JSON Download:', data.download_url);
                        console.log('📥 TXT Download:', data.txt_download_url);
                        console.log('📂 Server path:', data.path);

                        // Show notification to user
                        const message = `✅ Report structure saved!\n\nJSON: ${data.file}\nTXT: ${data.txt_file}\n\nCheck browser console for download links.`;
                        alert(message);
                    } else {
                        console.error('❌ Save failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Failed to save structure:', error);
                    console.log('You can still copy the structure from console above ⬆️');
                });

            } catch (error) {
                console.error('❌ Structure detection error:', error);
            }
        }

        // Function to apply user-specific filters to the Power BI report
        async function applyUserFilters() {
            if (!globalReport) {
                console.error('❌ Report not loaded yet');
                return;
            }

            console.log('📊 User Filters Config:', userFilters);

            // No filter needed for admins
            if (userFilters.type === 'none') {
                console.log('✅ Admin user - No filters applied, showing all data');
                return;
            }

            try {
                const pages = await globalReport.getPages();
                console.log('📄 Total pages:', pages.length);

                // Apply filter to ALL pages (not just active page)
                for (const page of pages) {
                    await applyFilterToPage(page);
                }

                console.log('✅ Filters applied to all pages');

            } catch (error) {
                console.error('❌ Error in applyUserFilters:', error);
            }
        }

        // Helper function to apply filter to a specific page
        async function applyFilterToPage(page) {
            try {
                let filterToApply = null;

                // Build filter for salesperson
                if (userFilters.type === 'salesperson' && userFilters.salespersonName) {
                    filterToApply = {
                        $schema: "http://powerbi.com/product/schema#basic",
                        target: {
                            table: "DIM_SALESREP",  // Try DIM_SALESREP first
                            column: "Name"
                        },
                        operator: "In",
                        values: [userFilters.salespersonName],
                        filterType: 1,  // BasicFilter
                        requireSingleSelection: false
                    };

                    console.log(`🔧 Applying salesperson filter to page "${page.displayName}":`, userFilters.salespersonName);
                }

                // Build filter for location
                if (userFilters.type === 'location' && userFilters.ouIds.length > 0) {
                    filterToApply = {
                        $schema: "http://powerbi.com/product/schema#basic",
                        target: {
                            table: "Sales",
                            column: "OU_ID"
                        },
                        operator: "In",
                        values: userFilters.ouIds,
                        filterType: 1,
                        requireSingleSelection: false
                    };

                    console.log(`🔧 Applying location filter to page "${page.displayName}":`, userFilters.ouIds);
                }

                if (!filterToApply) {
                    return;
                }

                // Try applying the filter
                try {
                    await page.setFilters([filterToApply]);
                    console.log(`✅ Filter applied to page: ${page.displayName}`);
                } catch (err) {
                    // If DIM_SALESREP doesn't work, try alternative table names
                    if (filterToApply.target.table === "DIM_SALESREP") {
                        console.log(`⚠️ DIM_SALESREP failed, trying "Sales Person Hierarchy"...`);

                        filterToApply.target.table = "Sales Person Hierarchy";

                        try {
                            await page.setFilters([filterToApply]);
                            console.log(`✅ Filter applied with "Sales Person Hierarchy" table`);
                        } catch (err2) {
                            console.error(`❌ Both table names failed for page ${page.displayName}:`, err2.message);
                        }
                    } else {
                        console.error(`❌ Filter failed for page ${page.displayName}:`, err.message);
                    }
                }

            } catch (error) {
                console.error(`❌ Error applying filter to page:`, error);
            }
        }

        // Function to switch between report pages
        function switchPage(pageId) {
            if (!globalReport) {
                console.error('❌ Report not loaded yet');
                return;
            }

            console.log('🔄 Switching to page:', pageId);

            // Update tab styling
            const allTabs = document.querySelectorAll('#page-tabs button');
            allTabs.forEach(tab => {
                if (tab.getAttribute('data-page-id') === pageId) {
                    tab.className = 'px-4 py-2 rounded-md font-medium transition-colors duration-200 bg-blue-600 text-white';
                } else {
                    tab.className = 'px-4 py-2 rounded-md font-medium transition-colors duration-200 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600';
                }
            });

            // Get all pages and set the active page
            globalReport.getPages().then(pages => {
                const targetPage = pages.find(page => page.name === pageId);
                if (targetPage) {
                    targetPage.setActive().then(() => {
                        console.log('✅ Switched to page:', pageId);
                        currentPageId = pageId;

                        // Note: Filters should persist across page switches
                        // But if they don't, we can reapply them
                        console.log('ℹ️ Filters should already be applied to this page');
                    }).catch(error => {
                        console.error('❌ Error switching page:', error);
                    });
                } else {
                    console.error('❌ Page not found:', pageId);
                }
            }).catch(error => {
                console.error('❌ Error getting pages:', error);
            });
        }
    </script>
</x-layout>
