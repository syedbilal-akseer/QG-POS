@push('title')
    {{ $pageTitle ?? 'Default Title' }}
@endpush

<div>
    {{ $this->table }}
</div>
