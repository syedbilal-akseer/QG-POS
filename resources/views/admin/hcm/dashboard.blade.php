<x-app-layout>
    @section('title', 'HCM Dashboard')
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">Human Capital Management</h1>
            
            <!-- Hiring Overview -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recruitment Snapshot</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Open Roles -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Open Roles</h3>
                            <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                                <svg class="w-6 h-6 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline">
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">12</p>
                            <span class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">+2 this week</span>
                        </div>
                    </div>

                    <!-- Pending Approvals -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Approvals</h3>
                            <div class="p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                                <svg class="w-6 h-6 text-yellow-500 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline">
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">5</p>
                            <span class="ml-2 text-sm font-medium text-gray-500 dark:text-gray-400">Requisitions</span>
                        </div>
                    </div>

                    <!-- Active Candidates -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Candidates</h3>
                            <div class="p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                                <svg class="w-6 h-6 text-purple-500 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline">
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">48</p>
                            <span class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">+12%</span>
                        </div>
                    </div>

                    <!-- Time-to-Hire -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Time-to-Hire</h3>
                            <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                                <svg class="w-6 h-6 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline">
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">24 <span class="text-lg font-normal text-gray-500 dark:text-gray-400">days</span></p>
                            <span class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">-2 days</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two Column Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Recent Requisitions -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Requisitions</h3>
                        <a href="#" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium">View All</a>
                    </div>
                    <div class="flow-root">
                        <ul role="list" class="-my-5 divide-y divide-gray-200 dark:divide-gray-700">
                            <li class="py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-200 font-bold text-xs">SM</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">Senior Marketing Manager</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">Marketing • Full-time</p>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">Pending</span>
                                    </div>
                                </div>
                            </li>
                            <li class="py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-200 font-bold text-xs">FE</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">Frontend Developer</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">Engineering • Remote</p>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Approved</span>
                                    </div>
                                </div>
                            </li>
                            <li class="py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-pink-100 dark:bg-pink-900 flex items-center justify-center text-pink-600 dark:text-pink-200 font-bold text-xs">HR</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">HR Coordinator</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">Human Resources • Contract</p>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Approved</span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Upcoming Interviews -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Today's Interviews</h3>
                        <a href="#" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium">View Calendar</a>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex-shrink-0 text-center w-12">
                                <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">10:00</span>
                                <span class="block text-xs font-medium text-gray-400 dark:text-gray-500">AM</span>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">Sarah Jenkins</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Frontend Developer • Technical Round</p>
                                <div class="mt-2 flex items-center space-x-2">
                                    <img class="h-5 w-5 rounded-full" src="https://ui-avatars.com/api/?name=Mike+Ross" alt="">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Interviewer: Mike Ross</span>
                                </div>
                            </div>
                            <button class="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">Join</button>
                        </div>

                        <div class="flex items-start space-x-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex-shrink-0 text-center w-12">
                                <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">02:30</span>
                                <span class="block text-xs font-medium text-gray-400 dark:text-gray-500">PM</span>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">David Chang</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Product Designer • Portfolio Review</p>
                                <div class="mt-2 flex items-center space-x-2">
                                    <img class="h-5 w-5 rounded-full" src="https://ui-avatars.com/api/?name=Jessica+Pearson" alt="">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Interviewer: Jessica Pearson</span>
                                </div>
                            </div>
                            <button class="text-xs bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-200 px-2 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-500">View</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div>
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="{{ route('admin.hcm.hiring.requisition') }}" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer text-gray-900 dark:text-white">
                        <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center text-blue-600 dark:text-blue-200 mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </div>
                        <span class="text-sm font-medium">New Requisition</span>
                    </a>
                    
                    <a href="#" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer text-gray-900 dark:text-white">
                        <div class="h-10 w-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center text-purple-600 dark:text-purple-200 mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </div>
                        <span class="text-sm font-medium">Add Candidate</span>
                    </a>
                    
                    <a href="#" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer text-gray-900 dark:text-white">
                        <div class="h-10 w-10 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center text-green-600 dark:text-green-200 mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span class="text-sm font-medium">Approve Request</span>
                    </a>
                    
                    <a href="#" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer text-gray-900 dark:text-white">
                        <div class="h-10 w-10 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center text-orange-600 dark:text-orange-200 mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <span class="text-sm font-medium">Post Job</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
