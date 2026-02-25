<x-layout :pageTitle="$pageTitle">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">Power BI RLS Diagnostic Report</h2>

            <!-- User Information -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">1. User Information</h3>
                <div class="space-y-2 text-sm">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">User ID:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">{{ $user->id }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">Name:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">Email:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">{{ $user->email }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">Role:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">{{ $user->role ?? 'N/A' }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">Role Name:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">{{ $roleName }}</div>
                    </div>
                </div>
            </div>

            <!-- RLS Configuration -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">2. Power BI RLS Configuration</h3>
                <div class="space-y-2 text-sm">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">Effective Identity:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded">
                            {{ $effectiveIdentity }}
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">RLS Roles:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded">
                            {{ json_encode($rlsRoles) }}
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">Expected DAX Filter:</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded">
                            [Name] = "{{ $effectiveIdentity }}"
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Type Detection -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">3. User Type Detection</h3>
                <div class="space-y-2 text-sm">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isAdmin():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isAdmin() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isAdmin() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isSalesPerson():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isSalesPerson() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isSalesPerson() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isHOD():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isHOD() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isHOD() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isManager():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isManager() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isManager() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isSalesHead():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isSalesHead() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isSalesHead() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isCmdKhi():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isCmdKhi() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isCmdKhi() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="font-medium text-gray-600 dark:text-gray-400">isCmdLhr():</div>
                        <div class="col-span-2 text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 rounded {{ $user->isCmdLhr() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $user->isCmdLhr() ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expected Behavior -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">4. Expected Behavior</h3>
                <div class="prose dark:prose-invert max-w-none">
                    @if($user->isAdmin() || $user->isSalesHead())
                        <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-4 mb-4">
                            <p class="font-semibold text-blue-900 dark:text-blue-100">Admin / Sales Head</p>
                            <p class="text-sm text-blue-800 dark:text-blue-200">Should see <strong>ALL</strong> salesperson data</p>
                            <p class="text-xs text-blue-700 dark:text-blue-300 mt-2">
                                Effective identity is set to service account which doesn't match any salesperson name,
                                so RLS filter won't restrict data.
                            </p>
                        </div>
                    @elseif($user->isSalesPerson() || $user->isHOD() || $user->isManager())
                        <div class="bg-green-50 dark:bg-green-900 border-l-4 border-green-500 p-4 mb-4">
                            <p class="font-semibold text-green-900 dark:text-green-100">Salesperson / HOD / Manager</p>
                            <p class="text-sm text-green-800 dark:text-green-200">Should see <strong>ONLY</strong> data for: <strong>{{ $user->name }}</strong></p>
                            <p class="text-xs text-green-700 dark:text-green-300 mt-2">
                                Effective identity is set to your name. Power BI RLS filter should match this against
                                the DIM_SALESREP[Name] column.
                            </p>
                        </div>
                    @else
                        <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-500 p-4 mb-4">
                            <p class="font-semibold text-yellow-900 dark:text-yellow-100">Other Role</p>
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">Behavior depends on location or specific role permissions</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Power BI Configuration Checklist -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">5. Power BI Configuration Checklist</h3>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">1</div>
                        <div class="ml-3 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">Power BI Desktop: Role Configuration</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">
                                Modeling → Manage roles → Verify "National" role exists
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">2</div>
                        <div class="ml-3 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">Power BI Desktop: DAX Filter</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs mt-1 font-mono">
                                DIM_SALESREP[Name] = USERPRINCIPALNAME()
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">3</div>
                        <div class="ml-3 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">Power BI Desktop: Test with "View as"</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">
                                Modeling → View as → Check "National" → Enter "{{ $effectiveIdentity }}"
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">4</div>
                        <div class="ml-3 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">Data Verification: Check DIM_SALESREP table</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">
                                Verify that "{{ $effectiveIdentity }}" exists EXACTLY in the DIM_SALESREP[Name] column (check case, spaces)
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">5</div>
                        <div class="ml-3 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">Power BI Service: Publish Report</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">
                                File → Publish → Publish to Power BI
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">6</div>
                        <div class="ml-3 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">Power BI Service: Dataset Security</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">
                                Workspace → Dataset → ... → Security → Verify "National" role is configured
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Logs -->
            @if(isset($recentLogs) && count($recentLogs) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">6. Recent Laravel Logs</h3>
                <div class="bg-gray-900 text-gray-100 p-4 rounded font-mono text-xs overflow-x-auto max-h-96 overflow-y-auto">
                    @foreach($recentLogs as $log)
                    <div class="mb-2 {{ str_contains($log, 'ERROR') ? 'text-red-400' : 'text-gray-300' }}">{{ $log }}</div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex space-x-4">
                <a href="{{ route('admin.bi-dashboard') }}"
                   class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    View Power BI Dashboard
                </a>
                <button onclick="window.location.reload()"
                        class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors">
                    Refresh Diagnostic
                </button>
                <a href="{{ route('admin.bi-dashboard.clear-cache') }}"
                   class="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium transition-colors">
                    Clear Power BI Cache
                </a>
            </div>
        </div>
    </div>
</x-layout>
