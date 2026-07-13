<div>
    <div class="mb-5 flex justify-between items-center">
        <h1 class="text-2xl font-bold dark:text-gray-100">Promotional Items</h1>
        <a href="{{ route('admin.promotional-items.bulk-upload-form') }}"
           class="px-4 py-2 rounded bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700">
            Bulk Upload (Excel)
        </a>
    </div>

    {{ $this->table }}
</div>