<div x-show="isSidebarOpen || window.innerWidth >= 1024"
    x-transition:enter="transition transform ease-out duration-200"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition transform ease-in duration-200"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    class="bg-headerBg border-e border-gray-200 text-white dark:bg-neutral-800 dark:border-neutral-700 w-64 space-y-6 py-7 px-2 absolute inset-y-0 left-0 transform lg:relative lg:translate-x-0 transition duration-200 ease-in-out"
    @click.away="isSidebarOpen = false">
    <div class="flex justify-between items-center px-4">
        <a href="#" class="text-white flex items-center space-x-2">
            <img src="{{ asset('logo.png') }}" alt="Logo" class="h-12">
        </a>
        <button class="text-2xl text-white lg:me-0 lg:hidden" @click="isSidebarOpen = false">&times;</button>
    </div>
    <nav>
        <ul>
            <li class="mb-4">
                <a href="{{ route('dashboard') }}"
                    class="flex items-center space-x-2 px-4 py-2 rounded-md
                   {{ request()->routeIs('dashboard') ? 'text-white bg-primary-600' : 'text-gray-100 hover:bg-primary-600 hover:text-white' }}">
                    <x-heroicon-o-home class="h-5 w-5" />
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="mb-4">
                <a href="{{ route('orders.all') }}"
                    class="flex items-center space-x-2 px-4 py-2 rounded-md
                   {{ request()->routeIs('orders.all') ? 'text-white bg-primary-600' : 'text-gray-100 hover:bg-primary-600 hover:text-white' }}">
                    <x-heroicon-o-shopping-cart class="h-5 w-5" />
                    <span>Orders</span>
                </a>
            </li>
            <li class="mb-4">
                <a href="{{ route('products.all') }}"
                    class="flex items-center space-x-2 px-4 py-2 rounded-md
                   {{ request()->routeIs('products.all') ? 'text-white bg-primary-600' : 'text-gray-100 hover:bg-primary-600 hover:text-white' }}">
                    <x-heroicon-o-shopping-bag class="h-5 w-5" />
                    <span>Products</span>
                </a>
            </li>
            <li class="mb-4">
                <a href="{{ route('customers.all') }}"
                    class="flex items-center space-x-2 px-4 py-2 rounded-md
                   {{ request()->routeIs('customers.all') ? 'text-white bg-primary-600' : 'text-gray-100 hover:bg-primary-600 hover:text-white' }}">
                    <x-heroicon-o-users class="h-5 w-5" />
                    <span>Customers</span>
                </a>
            </li>
            <li>
                <a href="{{ route('users.all') }}"
                    class="flex items-center space-x-2 px-4 py-2 rounded-md
                   {{ request()->routeIs('users.all') ? 'text-white bg-primary-600' : 'text-gray-100 hover:bg-primary-600 hover:text-white' }}">
                    <x-heroicon-o-user-group class="h-5 w-5" />
                    <span>Users</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
