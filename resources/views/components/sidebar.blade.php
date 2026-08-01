<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:flex-col sidebar-rail">
    <!-- Sidebar component -->
    <div
        class="sidebar-rail-inner flex grow flex-col gap-y-5 overflow-y-auto overflow-x-hidden border-r border-gray-200 bg-white text-gray-900 shadow-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-white px-3 pb-4">
        <div class="sidebar-logo flex h-20 shrink-0 items-center overflow-hidden">
            <x-application-logo />
        </div>
        <nav class="app-sidebar-nav flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    @livewire('sidebar.links')
                </li>
            </ul>
        </nav>
    </div>
</div>

<style>
    /* Compact icon-only rail by default; expands over the page on hover so
       the dashboard content keeps the extra width the rest of the time.
       Scoped to .sidebar-rail so the mobile drawer (responsive-sidebar,
       which reuses the same @livewire('sidebar.links') partial) is untouched. */
    .sidebar-rail {
        width: 5rem;
        transition: width 200ms ease-in-out;
    }

    .sidebar-rail:hover {
        width: 18rem;
    }

    .sidebar-rail-inner {
        transition: padding-left 200ms ease-in-out, padding-right 200ms ease-in-out;
    }

    .sidebar-rail:hover .sidebar-rail-inner {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }

    /* Logo is wider than the collapsed rail at its natural h-16 size, so
       shrink it to fit and grow it back once the rail expands on hover. */
    .sidebar-logo img {
        height: 2rem;
        width: auto;
        transition: height 200ms ease-in-out;
    }

    .sidebar-rail:hover .sidebar-logo img {
        height: 4rem;
    }

    .sidebar-rail .app-sidebar-nav span,
    .sidebar-rail .app-sidebar-nav svg.transform {
        display: none;
    }

    .sidebar-rail:hover .app-sidebar-nav span,
    .sidebar-rail:hover .app-sidebar-nav svg.transform {
        display: inline-block;
    }
</style>
