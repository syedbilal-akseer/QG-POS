<x-app-layout>
    @section('title', 'Candidate Pool')

    <div class="py-6 h-screen flex flex-col">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 w-full flex-1 flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Candidate Pool</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Drag and drop candidates across stages</p>
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm">
                    Add Candidate
                </button>
            </div>

            <!-- Kanban Board -->
            <div class="flex-1 overflow-x-auto pb-4">
                <div class="flex gap-6 h-full min-w-max">
                    <!-- Stage: Applied -->
                    <div class="w-80 bg-gray-100 dark:bg-gray-800 rounded-xl p-4 flex flex-col h-full ring-1 ring-gray-200 dark:ring-gray-700">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">Applied</h3>
                            <span class="bg-white dark:bg-gray-700 px-2 py-1 rounded text-xs font-bold text-gray-500 dark:text-gray-300">5</span>
                        </div>
                        
                        <div class="space-y-3 overflow-y-auto flex-1 pr-2">
                            <!-- Card -->
                            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600 hover:shadow-md cursor-grab active:cursor-grabbing transition-shadow">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-gray-900 dark:text-white">Emily Clark</h4>
                                    <span class="text-xs text-gray-400">2h ago</span>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-3">Senior React Developer</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>4.5/5 Rating</span>
                                    <img class="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600" src="https://ui-avatars.com/api/?name=Emily+Clark" alt="">
                                </div>
                            </div>
                            
                            <!-- Card -->
                            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600 hover:shadow-md cursor-grab active:cursor-grabbing transition-shadow">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-gray-900 dark:text-white">James Wilson</h4>
                                    <span class="text-xs text-gray-400">1d ago</span>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-3">Product Designer</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>3.0/5 Rating</span>
                                    <img class="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600" src="https://ui-avatars.com/api/?name=James+Wilson" alt="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stage: Screening -->
                    <div class="w-80 bg-gray-100 dark:bg-gray-800 rounded-xl p-4 flex flex-col h-full ring-1 ring-gray-200 dark:ring-gray-700">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">Screening</h3>
                            <span class="bg-white dark:bg-gray-700 px-2 py-1 rounded text-xs font-bold text-gray-500 dark:text-gray-300">2</span>
                        </div>
                        <div class="space-y-3 overflow-y-auto flex-1 pr-2">
                            <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600 hover:shadow-md cursor-grab">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-gray-900 dark:text-white">Sarah Connor</h4>
                                    <span class="text-xs text-gray-400">3d ago</span>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-3">HR Coordinator</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded">Phone Screen</span>
                                    <img class="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600" src="https://ui-avatars.com/api/?name=Sarah+Connor" alt="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stage: Interview -->
                    <div class="w-80 bg-gray-100 dark:bg-gray-800 rounded-xl p-4 flex flex-col h-full ring-1 ring-gray-200 dark:ring-gray-700">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">Interview</h3>
                            <span class="bg-white dark:bg-gray-700 px-2 py-1 rounded text-xs font-bold text-gray-500 dark:text-gray-300">3</span>
                        </div>
                        <div class="space-y-3 overflow-y-auto flex-1 pr-2">
                             <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-sm border-l-4 border-blue-500 hover:shadow-md cursor-grab">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-gray-900 dark:text-white">Michael Chen</h4>
                                    <span class="text-xs text-gray-400">Today</span>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-3">Frontend Developer</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span class="text-blue-600 dark:text-blue-400 font-bold">10:00 AM Today</span>
                                    <img class="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600" src="https://ui-avatars.com/api/?name=Michael+Chen" alt="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stage: Hired -->
                    <div class="w-80 bg-gray-100 dark:bg-gray-800 rounded-xl p-4 flex flex-col h-full ring-1 ring-gray-200 dark:ring-gray-700">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-200">Hired</h3>
                            <span class="bg-white dark:bg-gray-700 px-2 py-1 rounded text-xs font-bold text-gray-500 dark:text-gray-300">1</span>
                        </div>
                         <div class="space-y-3 overflow-y-auto flex-1 pr-2">
                             <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-sm border-l-4 border-green-500 hover:shadow-md cursor-grab">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-gray-900 dark:text-white">Jessica Lee</h4>
                                    <span class="text-xs text-gray-400">1w ago</span>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-3">Marketing Manager</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span class="text-green-600 dark:text-green-400 font-bold">Onboarding</span>
                                    <img class="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600" src="https://ui-avatars.com/api/?name=Jessica+Lee" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
