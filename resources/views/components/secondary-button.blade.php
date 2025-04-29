<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
