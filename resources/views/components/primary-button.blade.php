<button {{ $attributes->merge(['type' => 'submit', 'class' => 'py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-primary-700 dark:border-primary-900 bg-primary-600 text-white hover:bg-primary-700 focus:outline-none focus:bg-primary-700 disabled:opacity-50 disabled:pointer-events-none transition ease-in-out duration-150 uppercase tracking-widest shadow-sm']) }}>
    {{ $slot }}
</button>
