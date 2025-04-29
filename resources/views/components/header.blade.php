<header
    class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 dark:bg-neutral-900 dark:text-gray-300 dark:border-neutral-700 px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">

    <!-- Sidebar Toggle Button -->
    <button type="button" @click="isSidebarOpen = !isSidebarOpen"
        class="-m-2.5 p-2.5 text-gray-700 dark:text-gray-300 lg:hidden">
        <span class="sr-only">Open sidebar</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <!-- Separator -->
    <div class="h-6 w-px bg-gray-900/10 lg:hidden" aria-hidden="true"></div>

    <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
        <div class="relative flex flex-1">
            <!-- Page Title -->
            {{-- <h1 class="text-gray-700 text-xl font-semibold mt-4 dark:text-gray-300">{{ $pageTitle }}</h1> --}}
        </div>
        <div class="flex items-center gap-x-4 lg:gap-x-6">
            <!-- Theme Toggle Buttons -->
            <button type="button" x-show="darkMode" @click="darkMode = false"
                class="-m-2.5 p-2.5 text-gray-400 dark:text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                <span class="group inline-flex shrink-0 justify-center items-center size-9">
                    <svg class="shrink-0 h-6 w-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2"></path>
                        <path d="M12 20v2"></path>
                        <path d="m4.93 4.93 1.41 1.41"></path>
                        <path d="m17.66 17.66 1.41 1.41"></path>
                        <path d="M2 12h2"></path>
                        <path d="M20 12h2"></path>
                        <path d="m6.34 17.66-1.41 1.41"></path>
                        <path d="m19.07 4.93-1.41 1.41"></path>
                    </svg>
                </span>
            </button>

            <button type="button" x-show="!darkMode" @click="darkMode = true"
                class="-m-2.5 p-2.5 text-gray-400 dark:text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                <span class="group inline-flex shrink-0 justify-center items-center size-9">
                    <svg class="shrink-0 h-6 w-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                    </svg>
                </span>
            </button>

            <!-- Separator -->
            <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-900/10" aria-hidden="true"></div>

            <x-filament::icon-button icon="heroicon-o-bell" size="xl"
                class="-m-2.5 p-2.5 text-gray-400 dark:text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                x-data="{
                    unreadCount: {{ auth()->user()->unreadNotifications->count() }},
                    updateCount() {
                        fetch('{{ route('app.notifications.unread') }}')
                            .then(res => res.json())
                            .then(data => this.unreadCount = data.count);
                    }
                }" x-init="setInterval(() => updateCount(), 5000)"
                x-on:click="$dispatch('open-modal', { id: 'database-notifications' })" label="Notifications">

                <x-slot name="badge">
                    <span x-text="unreadCount"></span>
                </x-slot>
            </x-filament::icon-button>

            <!-- Separator -->
            <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-900/10" aria-hidden="true"></div>

            <!-- Profile dropdown -->
            <div class="relative">
                <button type="button" class="-m-1.5 flex items-center p-1.5" @click="isDropdownOpen = !isDropdownOpen"
                    :aria-expanded="isDropdownOpen ? 'true' : 'false'" aria-haspopup="true">
                    <span class="sr-only">Open user menu</span>
                    @if (auth()->user()->profile_photo)
                        <img src="{{ asset('storage/' . auth()->user()->profile_photo) }}" alt="User"
                            class="h-8 w-8 rounded-full bg-gray-50 dark:bg-gray-800">
                    @else
                        <div
                            class="h-8 w-8 rounded-full flex justify-center items-center bg-primary-600 dark:bg-primary-700">
                            <span class="text-gray-100 dark:text-gray-300 font-semibold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                    <span class="hidden lg:flex lg:items-center">
                        <span class="ml-4 text-sm font-semibold leading-6 text-gray-900 dark:text-gray-300"
                            aria-hidden="true">{{ auth()->user()->name }}</span>
                        <svg class="ml-2 h-5 w-5 text-gray-400 dark:text-gray-400" viewBox="0 0 20 20"
                            fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>

                <!-- Dropdown menu -->
                <div x-show="isDropdownOpen" x-cloak @click.away="isDropdownOpen = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute right-0 z-10 mt-2.5 w-32 origin-top-right rounded-md  dark:bg-neutral-800 py-2 px-3 shadow-lg ring-1 ring-gray-900/5 focus:outline-none"
                    role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                    <!-- Dropdown Items -->
                    <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 dark:text-gray-200 hover:bg-primary-600 hover:text-white dark:hover:bg-primary-600 dark:hover:text-white focus:outline-none"
                        href="{{ route('profile.edit') }}">
                        <x-heroicon-o-user class="shrink-0 size-4" />
                        Profile
                    </a>
                    <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 dark:text-gray-200 hover:bg-primary-600 hover:text-white dark:hover:bg-primary-600 dark:hover:text-white focus:outline-none"
                        href="{{ route('logout') }}"
                        onclick="event.preventDefault();
                                         document.getElementById('logout-form').submit();">
                        <x-heroicon-o-lock-closed class="shrink-0 size-4" />
                        Logout
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
