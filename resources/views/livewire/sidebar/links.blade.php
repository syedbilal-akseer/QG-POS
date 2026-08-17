<ul role="list" class="-mx-2 space-y-1">
    {{-- Dashboard --}}
    @if (Auth::user()->isAdmin() || Auth::user()->isAudit())
        <li>
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <x-link-icon icon="o-home" :active="request()->routeIs('dashboard')" />
                <span>Dashboard</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- Orders --}}
    @php $u = Auth::user(); @endphp
    @if ($u->isAdmin() || $u->isSalesHead() || (method_exists($u, 'hasAnyViewRole') && $u->hasAnyViewRole()) || $u->isAudit())
        {{-- Roles routed to /app/orders (orders.all): admin, sales-head, cmd-*,
             view-khi / view-lhr / view-all, audit. Mirrors routes/web.php gate. --}}
        <li>
            <x-sidebar-link :href="route('orders.all')" :active="request()->routeIs('orders.all')">
                <x-link-icon icon="o-shopping-cart" :active="request()->routeIs('orders.all')" />
                <span>Orders</span>
            </x-sidebar-link>
        </li>
    @elseif ($u->isSupplyChain())
        <li>
            <x-sidebar-link :href="route('orders.supply-chain.all')" :active="request()->routeIs('orders.supply-chain.all')">
                <x-link-icon icon="o-shopping-cart" :active="request()->routeIs('orders.supply-chain.all')" />
                <span>Orders</span>
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('builties.quickUpload')" :active="request()->routeIs('builties.quickUpload')">
                <x-link-icon icon="o-truck" :active="request()->routeIs('builties.quickUpload')" />
                <span>Upload Bilty</span>
            </x-sidebar-link>
        </li>
    @elseif ($u->isScmLhr())
        <li>
            <x-sidebar-link :href="route('orders.scm-lhr.all')" :active="request()->routeIs('orders.scm-lhr.all')">
                <x-link-icon icon="o-shopping-cart" :active="request()->routeIs('orders.scm-lhr.all')" />
                <span>Orders</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- Receipts --}}
    @if (Auth::user()->canAccessReceipts() && !Auth::user()->isScmLhr())
        <li>
            <x-sidebar-link :href="route('admin.receipts.index')" :active="request()->routeIs('admin.receipts.*')">
                <x-link-icon icon="o-book-open" :active="request()->routeIs('admin.receipts.*')" />
                <span>Receipts</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- Reports — mobile-order / mobile-receipt adoption. Mirrors the
         checkRole:admin,sales-head,cmd-khi,cmd-lhr gate on admin.reports.* routes. --}}
    @if ($u->isAdmin() || $u->isSalesHead())
        <li class="relative" x-data="{ openReportsMenu: {{ request()->routeIs('admin.reports.*') ? 'true' : 'false' }} }">
            <x-sidebar-link href="javascript:void(0)" @click="openReportsMenu = !openReportsMenu"
                aria-expanded="openReportsMenu" :active="request()->routeIs('admin.reports.*')">
                <x-link-icon icon="o-chart-bar" :active="request()->routeIs('admin.reports.*')" />
                <span class="flex-1 me-3">Reports</span>
                <x-heroicon-s-chevron-down x-bind:class="{ 'rotate-180': openReportsMenu }"
                    class="h-5 w-5 transform transition-transform" />
            </x-sidebar-link>

            <ul x-show="openReportsMenu" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95" x-cloak class="mt-2 space-y-1">
                <li>
                    <x-sidebar-link :href="route('admin.reports.sales-order-percentage')" :active="request()->routeIs('admin.reports.sales-order-percentage')">
                        <span>Orders % Adoption</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('admin.reports.receipts-percentage')" :active="request()->routeIs('admin.reports.receipts-percentage')">
                        <span>Receipts % Adoption</span>
                    </x-sidebar-link>
                </li>
            </ul>
        </li>
    @endif

    {{-- Price Lists --}}
    @if (Auth::user()->isAdmin() || Auth::user()->isPriceUploads() || Auth::user()->isAudit())
        <li>
            <x-sidebar-link :href="route('price-lists.index')" :active="request()->routeIs('price-lists.*')">
                <x-link-icon icon="o-currency-dollar" :active="request()->routeIs('price-lists.*')" />
                <span>Price Lists</span>
            </x-sidebar-link>
        </li>
    @endif


    {{-- Account — parent group combining Invoices, Ledgers, and Vendors AP.
     Outer visibility is the OR of all child conditions, since none of them
     is a strict superset of the others. --}}
    @if (Auth::user()->isAdmin() ||
            Auth::user()->isInvoiceManager() ||
            Auth::user()->hasViewKhi() ||
            Auth::user()->hasViewLhr() ||
            Auth::user()->hasViewAll() ||
            Auth::user()->isAccountUser() ||
            Auth::user()->isDirector() ||
            Auth::user()->isCmd() ||
            Auth::user()->isAudit())
        <li class="relative" x-data="{ openAccountMenu: {{ request()->routeIs('invoices.*', 'builties.*', 'ledgers.*', 'vendor-bills.*') ? 'true' : 'false' }} }">
            <x-sidebar-link href="javascript:void(0)" @click="openAccountMenu = !openAccountMenu"
                aria-expanded="openAccountMenu" :active="request()->routeIs(
                    'invoices.*',
                    'builties.*',
                    'ledgers.*',
                    'vendor-bills.*',
                )">
                <x-link-icon icon="o-banknotes" :active="request()->routeIs(
                    'invoices.*',
                    'builties.*',
                    'ledgers.*',
                    'vendor-bills.*',
                )" />
                <span class="flex-1 me-3">Accounts</span>
                <x-heroicon-s-chevron-down x-bind:class="{ 'rotate-180': openAccountMenu }"
                    class="h-5 w-5 transform transition-transform" />
            </x-sidebar-link>

            <ul x-show="openAccountMenu" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95" x-cloak class="mt-2 space-y-1 ps-4">

                {{-- Invoices sub-section --}}
                @if (Auth::user()->isAdmin() ||
                        Auth::user()->isInvoiceManager() ||
                        Auth::user()->hasViewKhi() ||
                        Auth::user()->hasViewLhr() ||
                        Auth::user()->hasViewAll() ||
                        Auth::user()->isAudit())
                    <li class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoices</li>
                    <li>
                        <x-sidebar-link :href="route('invoices.view')" :active="request()->routeIs('invoices.view')">
                            <x-link-icon icon="o-document-text" :active="request()->routeIs('invoices.view')" />
                            <span>Invoices</span>
                        </x-sidebar-link>
                    </li>
                    @if (Auth::user()->isAdmin() || Auth::user()->isInvoiceManager())
                        <li>
                            <x-sidebar-link :href="route('invoices.index')" :active="request()->routeIs('invoices.index')">
                                <x-link-icon icon="o-paper-airplane" :active="request()->routeIs('invoices.index')" />
                                <span>Send Invoices</span>
                            </x-sidebar-link>
                        </li>
                    @endif
                @endif

                {{-- Bilty — own condition (not nested under the Invoices
                     sub-section above) so account-user, who can't send
                     invoices, still gets to review/complete the
                     supply-chain bilty queue. --}}
                @if (Auth::user()->isAdmin() || Auth::user()->isInvoiceManager() || Auth::user()->isAccountUser())
                    <li>
                        <x-sidebar-link :href="route('builties.index')" :active="request()->routeIs('builties.*')">
                            <x-link-icon icon="o-truck" :active="request()->routeIs('builties.*')" />
                            <span>Bilty</span>
                        </x-sidebar-link>
                    </li>
                @endif

                {{-- Ledgers sub-section --}}
                @if (Auth::user()->isAdmin() || Auth::user()->isInvoiceManager() || Auth::user()->isAudit())
                    <li class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ledgers</li>
                    <li>
                        <x-sidebar-link :href="route('ledgers.index')" :active="request()->routeIs('ledgers.index')">
                            <x-link-icon icon="o-document-duplicate" :active="request()->routeIs('ledgers.index')" />
                            <span>Ledgers</span>
                        </x-sidebar-link>
                    </li>
                    @if (Auth::user()->isAdmin() || Auth::user()->isInvoiceManager())
                        <li>
                            <x-sidebar-link :href="route('ledgers.upload')" :active="request()->routeIs('ledgers.upload')">
                                <x-link-icon icon="o-arrow-up-tray" :active="request()->routeIs('ledgers.upload')" />
                                <span>Ledger Import</span>
                            </x-sidebar-link>
                        </li>
                    @endif
                @endif

                {{-- Vendors AP sub-section --}}
                @if (Auth::user()->isAdmin() || Auth::user()->isDirector() || Auth::user()->isCmd())
                    <li class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Vendors</li>
                    <li>
                        <x-sidebar-link :href="route('vendor-bills.index')" :active="request()->routeIs('vendor-bills.*')">
                            <x-link-icon icon="o-banknotes" :active="request()->routeIs('vendor-bills.*')" />
                            <span>Vendors AP</span>
                        </x-sidebar-link>
                    </li>
                @endif
            </ul>
        </li>

        {{-- Documents — unchanged, still its own top-level entry --}}
        @if (Auth::user()->isAdmin() ||
                Auth::user()->isInvoiceManager() ||
                Auth::user()->hasViewKhi() ||
                Auth::user()->hasViewLhr() ||
                Auth::user()->hasViewAll() ||
                Auth::user()->isAudit())
            <li>
                <x-sidebar-link :href="route('documents.index')" :active="request()->routeIs('documents.*')">
                    <x-link-icon icon="o-folder" :active="request()->routeIs('documents.*')" />
                    <span>Documents</span>
                </x-sidebar-link>
            </li>
        @endif
    @endif
    {{-- App Version — admin manages the mobile minimum supported version
         the /api/* middleware checks against. --}}
    @if (Auth::user()->isAdmin())
        <li>
            <x-sidebar-link :href="route('app-versions.index')" :active="request()->routeIs('app-versions.*')">
                <x-link-icon icon="o-device-phone-mobile" :active="request()->routeIs('app-versions.*')" />
                <span>App Version</span>
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('announcements.index')" :active="request()->routeIs('announcements.*')">
                <x-link-icon icon="o-megaphone" :active="request()->routeIs('announcements.*')" />
                <span>Announcements</span>
            </x-sidebar-link>
        </li>
    @endif

    @if (Auth::user()->isAdmin())
        {{-- Promotional Items --}}
        <li>
            <x-sidebar-link :href="route('promotional-items.all')" :active="request()->routeIs('promotional-items.all')">
                <x-link-icon icon="o-gift" :active="request()->routeIs('promotional-items.all')" />
                <span>Promotional Items</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- Salesperson Targets — admin-only (upload + cross-team view) --}}
    @if (Auth::user()->isAdmin())
        <li>
            <x-sidebar-link :href="route('admin.salesperson-targets.index')" :active="request()->routeIs('admin.salesperson-targets.*')">
                <x-link-icon icon="o-flag" :active="request()->routeIs('admin.salesperson-targets.*')" />
                <span>Targets</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- Customer Form Events — admin-only --}}
    @if (Auth::user()->isAdmin())
        <li>
            <x-sidebar-link :href="route('admin.customer-form-events.index')" :active="request()->routeIs('admin.customer-form-events.*')">
                <x-link-icon icon="o-calendar" :active="request()->routeIs('admin.customer-form-events.*')" />
                <span>Form Events</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- QR Labels (multi-level packing barcodes) --}}
    @if (Auth::user()->isAdmin() || Auth::user()->isInventoryManager())
        <li>
            <x-sidebar-link :href="route('items.qr-labels.bulk')" :active="request()->routeIs('items.qr-labels*')">
                <x-link-icon icon="o-qr-code" :active="request()->routeIs('items.qr-labels*')" />
                <span>QR Labels</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- CRM --}}
    @if (Auth::user()->isAdmin() ||
            Auth::user()->isSalesHead() ||
            Auth::user()->isScmLhr() ||
            (!Auth::user()->isSupplyChain() &&
                !Auth::user()->isPriceUploads() &&
                !Auth::user()->isInvoiceManager() &&
                !Auth::user()->isInventoryManager() &&
                !Auth::user()->isCmd()))
        <li class="relative" x-data="{ openCrmMenu: {{ request()->routeIs('monthlyTourPlans.all', 'visits.all', 'manage.tourplans') ? 'true' : 'false' }} }">
            <x-sidebar-link href="javascript:void(0)" @click="openCrmMenu = !openCrmMenu" aria-expanded="openCrmMenu">
                <x-link-icon icon="o-chart-pie" />
                <span class="flex-1 me-3">CRM</span>
                <x-heroicon-s-chevron-down x-bind:class="{ 'rotate-180': openCrmMenu }"
                    class="h-5 w-5 transform transition-transform" />
            </x-sidebar-link>

            <!-- Dropdown Menu -->
            <ul x-show="openCrmMenu" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95" x-cloak class="mt-2 space-y-1">
                @if (isManager() || isHod() || isAdmin())
                    <li>
                        <x-sidebar-link :href="route('manage.tourplans')" :active="request()->routeIs('manage.tourplans')">
                            <x-link-icon icon="o-clipboard-document-list" :active="request()->routeIs('manage.tourplans')" />
                            <span>Manage Tour Plans</span>
                        </x-sidebar-link>
                    </li>
                @endif
                <li>
                    <x-sidebar-link :href="route('monthlyTourPlans.all')" :active="request()->routeIs('monthlyTourPlans.all')">
                        <x-link-icon icon="o-calendar-days" :active="request()->routeIs('monthlyTourPlans.all')" />
                        <span>Monthly Tour Plans</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('visits.all')" :active="request()->routeIs('visits.all')">
                        <x-link-icon icon="o-map-pin" :active="request()->routeIs('visits.all')" />
                        <span>Visits</span>
                    </x-sidebar-link>
                </li>
            </ul>
        </li>
    @endif

    @if (Auth::user()->isAdmin())
        {{-- Customers --}}
        <li>
            <x-sidebar-link :href="route('customers.all')" :active="request()->routeIs('customers.all')">
                <x-link-icon icon="o-users" :active="request()->routeIs('customers.all')" />
                <span>Customers</span>
            </x-sidebar-link>
        </li>

        {{-- Users --}}
        <li>
            <x-sidebar-link :href="route('users.all')" :active="request()->routeIs('users.all')">
                <x-link-icon icon="o-user-group" :active="request()->routeIs('users.all')" />
                <span>Users</span>
            </x-sidebar-link>
        </li>

        {{-- Products --}}
        <li>
            <x-sidebar-link :href="route('products.all')" :active="request()->routeIs('products.all')">
                <x-link-icon icon="o-shopping-bag" :active="request()->routeIs('products.all')" />
                <span>Products</span>
            </x-sidebar-link>
        </li>

        {{-- Locations --}}
        <li>
            <x-sidebar-link :href="route('user-locations.all')" :active="request()->routeIs('user-locations.all')">
                <x-link-icon icon="o-map-pin" :active="request()->routeIs('user-locations.all')" />
                <span>Locations</span>
            </x-sidebar-link>
        </li>

        {{-- HCM Module --}}
        <li class="relative" x-data="{ openHcmMenu: {{ request()->routeIs('admin.hcm.*') ? 'true' : 'false' }} }">
            <x-sidebar-link href="javascript:void(0)" @click="openHcmMenu = !openHcmMenu" aria-expanded="openHcmMenu"
                :active="request()->routeIs('admin.hcm.*')">
                <x-link-icon icon="o-users" :active="request()->routeIs('admin.hcm.*')" />
                <span class="flex-1 me-3">HCM</span>
                <x-heroicon-s-chevron-down x-bind:class="{ 'rotate-180': openHcmMenu }"
                    class="h-5 w-5 transform transition-transform" />
            </x-sidebar-link>

            <ul x-show="openHcmMenu" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95" x-cloak class="mt-2 space-y-1 ps-4">

                <li>
                    <x-sidebar-link :href="route('admin.hcm.dashboard')" :active="request()->routeIs('admin.hcm.dashboard')">
                        <span>Overview</span>
                    </x-sidebar-link>
                </li>

                {{-- Hiring Sub-section --}}
                <li class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Hiring</li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.hiring.requisition')" :active="request()->routeIs('admin.hcm.hiring.requisition')">
                        <span>Requisitions</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.hiring.candidates')" :active="request()->routeIs('admin.hcm.hiring.candidates')">
                        <span>Candidates</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.hiring.onboarding')" :active="request()->routeIs('admin.hcm.hiring.onboarding')">
                        <span>Onboarding</span>
                    </x-sidebar-link>
                </li>

                {{-- Performance Sub-section --}}
                <li class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Performance</li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.performance.dashboard')" :active="request()->routeIs('admin.hcm.performance.dashboard')">
                        <span>KPI Dashboard</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.performance.goals')" :active="request()->routeIs('admin.hcm.performance.goals')">
                        <span>Goal Setting</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.performance.appraisals')" :active="request()->routeIs('admin.hcm.performance.appraisals')">
                        <span>Appraisals</span>
                    </x-sidebar-link>
                </li>

                {{-- Admin --}}
                <li class="px-3 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Admin</li>
                <li>
                    <x-sidebar-link :href="route('admin.hcm.integration')" :active="request()->routeIs('admin.hcm.integration')">
                        <span>Integration</span>
                    </x-sidebar-link>
                </li>
            </ul>
        </li>

        {{-- Activity Logs --}}
        <li>
            <x-sidebar-link :href="route('activity-logs')" :active="request()->routeIs('activity-logs')">
                <x-link-icon icon="o-list-bullet" :active="request()->routeIs('activity-logs')" />
                <span>Activity Logs</span>
            </x-sidebar-link>
        </li>
    @endif

    {{-- WMS Digitization Module - Admin and Inventory Manager only --}}
    @if (Auth::user()->isAdmin() || Auth::user()->isInventoryManager())
        <li class="relative" x-data="{ openWmsMenu: {{ request()->routeIs('wms.*') ? 'true' : 'false' }} }">
            <x-sidebar-link href="javascript:void(0)" @click="openWmsMenu = !openWmsMenu" aria-expanded="openWmsMenu"
                :active="request()->routeIs('wms.*')">
                <x-link-icon icon="o-archive-box" :active="request()->routeIs('wms.*')" />
                <span class="flex-1 me-3">Warehouse (WMS)</span>
                <x-heroicon-s-chevron-down x-bind:class="{ 'rotate-180': openWmsMenu }"
                    class="h-5 w-5 transform transition-transform" />
            </x-sidebar-link>

            <ul x-show="openWmsMenu" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95" x-cloak class="mt-2 space-y-1 ps-4">

                <li>
                    <x-sidebar-link :href="route('wms.locations')" :active="request()->routeIs('wms.locations')">
                        <span>Locations & Racking</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('wms.grn')" :active="request()->routeIs('wms.grn')">
                        <span>GRN Generation</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('wms.lpn')" :active="request()->routeIs('wms.lpn')">
                        <span>LPN / Stock Handling</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('wms.picking')" :active="request()->routeIs('wms.picking')">
                        <span>Picking Workflow</span>
                    </x-sidebar-link>
                </li>
                <li>
                    <x-sidebar-link :href="route('wms.traceability')" :active="request()->routeIs('wms.traceability')">
                        <span>Traceability Reports</span>
                    </x-sidebar-link>
                </li>
            </ul>
        </li>
    @endif
</ul>
