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
</ul>
