<ul role="list" class="-mx-2 space-y-1">
    @if (Auth::user()->isAdmin())
        <li>
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <x-link-icon icon="o-home" :active="request()->routeIs('dashboard')" />
                Dashboard
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('orders.all')" :active="request()->routeIs('orders.all')">
                <x-link-icon icon="o-shopping-cart" :active="request()->routeIs('orders.all')" />
                <span>Orders</span>
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('reciepts')" :active="request()->routeIs('reciepts')">
                <x-link-icon icon="o-book-open" :active="request()->routeIs('reciepts')" />
                <span>Reciepts</span>
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('products.all')" :active="request()->routeIs('products.all')">
                <x-link-icon icon="o-shopping-bag" :active="request()->routeIs('products.all')" />
                <span>Products</span>
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('customers.all')" :active="request()->routeIs('customers.all')">
                <x-link-icon icon="o-users" :active="request()->routeIs('customers.all')" />
                <span>Customers</span>
            </x-sidebar-link>
        </li>
        <li>
            <x-sidebar-link :href="route('users.all')" :active="request()->routeIs('users.all')">
                <x-link-icon icon="o-user-group" :active="request()->routeIs('users.all')" />
                <span>Users</span>
            </x-sidebar-link>
        </li>
    @endif

    @if (Auth::user()->isSupplyChain())
        <li>
            <x-sidebar-link :href="route('orders.supply-chain.all')" :active="request()->routeIs('orders.supply-chain.all')">
                <x-link-icon icon="o-shopping-cart" :active="request()->routeIs('orders.supply-chain.all')" />
                <span>Orders</span>
            </x-sidebar-link>
        </li>
    @endif

    @if (!Auth::user()->isSupplyChain())
        <li class="relative" x-data="{ openCrmMenu: {{ request()->routeIs('monthlyTourPlans.all', 'visits.all', 'manage.tourplans') ? 'true' : 'false' }} }">
            <x-sidebar-link href="javascript:void" @click="openCrmMenu = !openCrmMenu" aria-expanded="openCrmMenu">
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
</ul>
