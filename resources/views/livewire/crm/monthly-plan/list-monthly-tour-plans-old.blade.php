<div>

    <!-- Add New Plan Button -->
    <div class="flex justify-end mb-4">
        <x-primary-button wire:click="addNewPlan">
            {{ __('Add New Plan') }}
        </x-primary-button>
    </div>

    <!-- Tour Plans Table -->
    {{ $this->table }}
</div>
