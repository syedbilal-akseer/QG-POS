@push('title')
    Customer Visits
@endpush
<div>
    <div class="mb-10">
        @livewire(App\Livewire\Widgets\CustomerVisitStatsOverview::class)
    </div>

    {{ $this->table }}
</div>
