<button {{ $attributes->merge(['type' => 'submit', 'class' => 'py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border-primary-700 bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-700 dark:hover:bg-primary-600 focus:outline-none focus:bg-primary-700 disabled:opacity-50 disabled:pointer-events-none transition ease-in-out duration-150 uppercase tracking-widest shadow-sm']) }}>
    {{ $slot }}
</button>
