<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
    <!-- Sidebar component -->
    <div
        class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white text-gray-900 dark:bg-neutral-900 dark:border-neutral-700 dark:text-white px-6 pb-4">
        <div class="flex h-20 shrink-0 items-center">
            <x-application-logo />
        </div>
        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    <ul role="list" class="-mx-2 space-y-1">
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

                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</div>
