<!-- ========== END HEADER ========== -->
<div class="bg-headerBg text-white dark:bg-neutral-800 dark:border-neutral-700 p-4 flex justify-between items-center">
    <!-- Sidebar Toggle Button for Small Devices -->
    <button @click="isSidebarOpen = !isSidebarOpen"
        class="lg:hidden p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Page Title -->
    <h1 class="text-xl font-semibold">{{ $pageTitle }}</h1>

    <!-- Right Side Icons: Theme Toggle and User Profile -->
    <div class="flex items-center space-x-4">
        <!-- Theme Toggle Buttons -->
        <button type="button" x-show="darkMode" @click="darkMode = false"
            class="font-medium text-gray-800 rounded-full hover:bg-primary-600 focus:outline-none focus:bg-primary-600 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            <span class="group inline-flex shrink-0 justify-center items-center size-9">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
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
            class="font-medium text-gray-100 rounded-full hover:bg-primary-600 focus:outline-none focus:bg-primary-600 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            <span class="group inline-flex shrink-0 justify-center items-center size-9">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                </svg>
            </span>
        </button>

        <!-- User Profile Dropdown -->
        <div class="relative" @click.away="isDropdownOpen = false">
            @if (auth()->user()->profile_photo)
                <img src="{{ asset('storage/' . auth()->user()->profile_photo) }}" alt="User"
                    class="w-10 h-10 rounded-full cursor-pointer" @click="isDropdownOpen = !isDropdownOpen">
            @else
                <div @click="isDropdownOpen = !isDropdownOpen"
                    class="shrink-0 size-[38px] rounded-full flex justify-center items-center bg-primary-600 dark:bg-primary-700">
                    <span class="text-gray-100 dark:text-white font-semibold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                </div>
            @endif

            <!-- Dropdown Menu -->
            <div x-show="isDropdownOpen" x-cloak
                class="absolute right-0 mt-2 w-48 bg-white dark:bg-neutral-700 rounded-md shadow-lg py-1 z-20 p-1.5 space-y-0.5">
                <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-primary-600 hover:text-white focus:outline-none focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300"
                    href="{{ route('profile.edit') }}">
                    <x-heroicon-o-user class="shrink-0 size-4" />
                    Profile
                </a>
                <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-primary-600 hover:text-white focus:outline-none focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300"
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
