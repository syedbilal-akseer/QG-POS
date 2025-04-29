<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

<div class="container mt-2 table-crm ">

    <!-- Header with Search and Add New Plan -->
    <div class="d-flex justify-content-between mb-3">
        <x-primary-button wire:click="addNewPlan">
            {{ __('Add New Plan') }}
        </x-primary-button>

        <div class="relative w-full max-w-xs">
            <!-- Search Input -->
            <input type="search" 
                   wire:model.live.debounce.500ms="tableSearch" 
                   placeholder="Search"
                   autocomplete="off"
                   class="block w-full bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded-lg focus:ring-gray-500 focus:border-gray-500 pr-10 p-2.5"/>
        
            <!-- Search Icon (Positioned on Right) -->
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none search-icon">
                <i class="fa fa-search text-gray-400"></i>
            </div>
        </div>
        
    </div>

    <!-- Table List Monthly Tour Plans -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td>{{ $plan->month }}</td>
                        <td>{{ ucfirst($plan->status) }}</td>
                        <td class="text-end">
                            <!-- Direct Link for Viewing Plan -->
                            <a href="{{ route('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $plan->id]) }}" 
                               class="btn btn-sm btn-details me-2">
                                <i class="fa fa-eye"></i> View Details
                            </a>
                        
                            <!-- Direct Link for Editing Plan -->
                            <a href="{{ route('monthlyTourPlans.addNewPlan', ['monthlyTourPlan' => $plan->id]) }}" 
                               class="btn btn-sm btn-edit me-2">
                                <i class="fa fa-pencil"></i> Edit
                            </a>
                        </td>
                        
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">No plans found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center">
        <div>
            Showing {{ $plans->firstItem() }} to {{ $plans->lastItem() }} of {{ $plans->total() }} results
        </div>
        <div>
            {{ $plans->links() }}
        </div>
    </div>
</div>
<script src="https://use.fontawesome.com/20fb3c6fa2.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>