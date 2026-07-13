<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
    <!-- Sidebar component -->
    <div
        class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200  text-gray-900 dark:bg-neutral-900 dark:border-neutral-700 dark:text-white px-6 pb-4">
        <div class="flex h-20 shrink-0 items-center">
            <x-application-logo />
        </div>
        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    @livewire('sidebar.links')
                </li>
            </ul>
        </nav>
    </div>
</div>
