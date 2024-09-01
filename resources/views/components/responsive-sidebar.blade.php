<div x-show="isSidebarOpen" x-cloak x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="relative z-50 lg:hidden" role="dialog"
    aria-modal="true">

    <!-- Overlay -->
    <div @click.away="isSidebarOpen = false" class="fixed inset-0 bg-gray-900/80 dark:bg-gray-900/80"></div>

    <div class="fixed inset-0 flex">
        <!-- Sidebar -->
        <div class="relative mr-16 flex w-full max-w-xs flex-1" x-show="isSidebarOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full">

            <!-- Close Button -->
            <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                <button type="button" @click="isSidebarOpen = false" class="-m-2.5 p-2.5">
                    <span class="sr-only">Close sidebar</span>
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Sidebar Content -->
            <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white text-gray-900 dark:bg-neutral-900 dark:text-gray-100 px-6 pb-4">
                <div class="flex h-16 shrink-0 items-center">
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
    </div>
</div>
