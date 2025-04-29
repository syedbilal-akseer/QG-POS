<x-filament::icon-button
    icon="heroicon-o-bell"
    class="-m-2.5 p-2.5 text-gray-400 dark:text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
    x-data="{}"
    x-on:click="$dispatch('open-modal', { id: 'database-notifications' })"
    label="Notifications"
>
    {{-- {{ $unreadNotificationsCount }} --}}
    <x-slot name="badge">
        {{ auth()->user()->unreadNotifications->count() }}
    </x-slot>
</x-filament::icon-button>


