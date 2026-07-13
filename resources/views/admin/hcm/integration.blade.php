<x-app-layout>
    @section('title', 'Integration')

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900 mb-6">System Integration & Mapping</h1>
            
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">FlowHCM Sync Status</h3>
                <div class="space-y-4">
                     <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg border border-green-100">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <div>
                                <p class="text-sm font-bold text-gray-900">Employee Data Sync</p>
                                <p class="text-xs text-gray-500">Last synced: 10 mins ago</p>
                            </div>
                        </div>
                        <button class="text-sm text-green-700 font-medium">Sync Now</button>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg border border-red-100">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <div>
                                <p class="text-sm font-bold text-gray-900">Department Mapping</p>
                                <p class="text-xs text-red-600">Error: Unmapped Department 'R&D'</p>
                            </div>
                        </div>
                         <button class="text-sm text-red-700 font-medium">Fix Issue</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
