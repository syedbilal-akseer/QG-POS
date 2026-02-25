<x-app-layout>
    @section('title', 'Performance Dashboard')

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900 mb-6">Performance & KPI Dashboard</h1>

            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-gray-500 text-sm font-medium">Department Goal Completion</h3>
                    <div class="mt-4 flex items-end justify-between">
                        <span class="text-3xl font-bold text-gray-900">85%</span>
                        <div class="h-2 w-24 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500" style="width: 85%"></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-gray-500 text-sm font-medium">Reviews Completed</h3>
                    <div class="mt-4 flex items-end justify-between">
                        <span class="text-3xl font-bold text-gray-900">124/150</span>
                        <span class="text-sm text-yellow-600 font-medium">On Track</span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-gray-500 text-sm font-medium">Top Performers</h3>
                    <div class="mt-4 flex items-end justify-between">
                        <span class="text-3xl font-bold text-gray-900">12</span>
                        <span class="text-sm text-blue-600 font-medium">View List</span>
                    </div>
                </div>
            </div>

            <!-- 9-Box Grid Placeholder -->
            <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-4">9-Box Performance Grid</h3>
                <div class="grid grid-cols-3 gap-1 max-w-md mx-auto aspect-square bg-gray-200 p-1">
                    <div class="bg-green-100 flex items-center justify-center text-xs font-bold text-green-800">High Potential</div>
                    <div class="bg-green-200 flex items-center justify-center text-xs font-bold text-green-900">Growth Star</div>
                    <div class="bg-green-300 flex items-center justify-center text-xs font-bold text-green-900">Star</div>
                    
                    <div class="bg-yellow-50 flex items-center justify-center text-xs font-bold text-yellow-800">Inconsistent</div>
                    <div class="bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-800">Key Player</div>
                    <div class="bg-green-200 flex items-center justify-center text-xs font-bold text-green-900">High Performer</div>
                    
                    <div class="bg-red-50 flex items-center justify-center text-xs font-bold text-red-800">Risk</div>
                    <div class="bg-yellow-50 flex items-center justify-center text-xs font-bold text-yellow-800">Effective</div>
                    <div class="bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-800">Trusted Pro</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
