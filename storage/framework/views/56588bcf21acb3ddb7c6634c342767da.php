<div x-data="{ 
            showTaskModal: false, 
            showTaxPopup: false,
            taskData: null, 
            loading: false,
            error: null,
            async fetchTaskDetails(id) {
                <!-- console.log('fetchTaskDetails called with ID:', id); -->
                this.loading = true;
                this.error = null;
                this.showTaskModal = true;
                try {
                    const response = await fetch(`/tasks/show/${id}`);
                    <!-- console.log('Response status:', response.status); -->
                    if (!response.ok) throw new Error('Failed to fetch task details');
                    this.taskData = await response.json();
                    <!-- console.log('Task data loaded:', this.taskData); -->
                } catch (error) {
                    this.error = error.message;
                    console.error('Error fetching task details:', error);
                } finally {
                    this.loading = false;
                }
            }
        }"
    @view-task.window="fetchTaskDetails($event.detail.id)">

    <!-- Modal Overlay -->
    <div x-show="showTaskModal"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="showTaskModal = false"
        @keydown.escape.window="showTaskModal = false"
        class="fixed inset-0 z-[10001] flex items-center justify-center bg-gray-500 bg-opacity-50 backdrop-blur-sm">

        <!-- Modal Content -->
        <div x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-hidden border border-gray-200 dark:border-gray-700">

            <!-- Modal Header with Gradient -->
            <div class="relative bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 px-8 py-6">
                <div class="absolute inset-0 bg-grid-pattern opacity-10"></div>
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 backdrop-blur-sm rounded-xl p-3 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white tracking-tight">Task Details</h3>
                            <span x-show="taskData" x-text="taskData?.reference" class="text-sm text-blue-100 font-mono bg-white/10 px-3 py-1 rounded-full mt-1 inline-block"></span>
                        </div>
                    </div>
                    <button @click="showTaskModal = false"
                        class="bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-xl p-2 transition-all duration-200 hover:rotate-90 transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-5 overflow-y-auto max-h-[calc(95vh-180px)] bg-gray-50 dark:bg-gray-900">

                <!-- Loading State -->
                <div x-show="loading" class="flex flex-col items-center justify-center py-12">
                    <div class="relative">
                        <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-200"></div>
                        <div class="animate-spin rounded-full h-16 w-16 border-4 border-t-blue-600 absolute top-0 left-0"></div>
                    </div>
                    <span class="mt-6 text-lg font-medium text-gray-600 dark:text-gray-400 animate-pulse">Loading task details...</span>
                </div>

                <!-- Error State -->
                <div x-show="error" class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 border-l-4 border-red-500 rounded-xl p-4 mb-4 shadow-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-7 w-7 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-bold text-red-800 dark:text-red-200">Error Loading Task</h3>
                            <p class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
                        </div>
                    </div>
                </div>

                <!-- Task Details Content -->
                <div x-show="taskData && !loading && !error" class="space-y-4">

                    <!-- Enhanced Information Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Basic Information Card -->
                        <div class="group relative bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-500">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-400/10 to-indigo-400/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative">
                                <div class="flex items-center mb-3">
                                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl p-2.5 shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="ml-3 font-bold text-gray-900 dark:text-white text-lg">Basic Information</h4>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Reference:</span>
                                        <span x-text="taskData?.reference" class="text-base font-mono font-semibold text-gray-900 dark:text-white bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded"></span>
                                    </div>
                                    <div class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Type:</span>
                                        <span x-text="taskData?.type" class="text-base font-semibold text-gray-900 dark:text-white capitalize"></span>
                                    </div>
                                    <div class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Status:</span>
                                        <span x-text="taskData?.status"
                                            :class="{
                                                      'text-green-700 bg-green-100 dark:bg-green-900/50 dark:text-green-300': taskData?.status === 'confirmed',
                                                      'text-blue-700 bg-blue-100 dark:bg-blue-900/50 dark:text-blue-300': taskData?.status === 'issued',
                                                      'text-yellow-700 bg-yellow-100 dark:bg-yellow-900/50 dark:text-yellow-300': taskData?.status === 'pending',
                                                      'text-red-700 bg-red-100 dark:bg-red-900/50 dark:text-red-300': taskData?.status === 'void',
                                                      'text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-300': !['confirmed', 'issued', 'pending', 'void'].includes(taskData?.status)
                                                  }"
                                            class="text-base font-bold capitalize px-3 py-1.5 rounded-full shadow-sm"></span>
                                    </div>
                                    <div x-show="taskData?.passenger_name" class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Passenger:</span>
                                        <span x-text="taskData?.passenger_name" class="text-base font-semibold text-gray-900 dark:text-white"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Client & Agent Card -->
                        <div class="group relative bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 dark:border-gray-700 hover:border-purple-400 dark:hover:border-purple-500">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-400/10 to-pink-400/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative">
                                <div class="flex items-center mb-3">
                                    <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl p-2.5 shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="ml-3 font-bold text-gray-900 dark:text-white text-lg">Client & Agent</h4>
                                </div>
                                <div class="space-y-2">
                                    <div x-show="taskData?.client" class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Client:</span>
                                        <span x-text="taskData?.client?.full_name" class="text-base font-semibold text-gray-900 dark:text-white text-right"></span>
                                    </div>
                                    <div x-show="taskData?.client?.phone" class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Phone:</span>
                                        <span x-text="taskData?.client?.phone" class="text-base font-mono font-semibold text-gray-900 dark:text-white"></span>
                                    </div>
                                    <div x-show="taskData?.agent" class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Agent:</span>
                                        <span x-text="taskData?.agent?.name" class="text-base font-semibold text-gray-900 dark:text-white text-right"></span>
                                    </div>
                                    <div x-show="taskData?.agent?.branch" class="flex items-start justify-between group/item hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg transition-colors">
                                        <span class="text-base text-gray-600 dark:text-gray-400 font-medium">Branch:</span>
                                        <span x-text="taskData?.agent?.branch?.name" class="text-base font-semibold text-gray-900 dark:text-white text-right"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Card -->
                        <div class="group relative bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-4 shadow-lg hover:shadow-2xl transition-all duration-300 border border-emerald-400 hover:scale-105 transform">
                            <div class="absolute inset-0 bg-black/5 rounded-2xl pointer-events-none"></div>
                            <div class="relative z-10">
                                <div class="flex items-center mb-3">
                                    <div class="bg-white/20 backdrop-blur-sm rounded-xl p-2.5 shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="ml-3 font-bold text-white text-lg">Financial Details</h4>
                                </div>
                                <div class="space-y-2" style="position: relative; z-index: 100;">
                                    <div class="flex items-center justify-between bg-white/10 p-3 rounded-xl hover:bg-white/20 transition-colors">
                                        <span class="text-base text-white/90 font-medium">Price:</span>
                                        <span x-text="taskData?.price ? Number(taskData.price).toFixed(3) + ' KWD' : 'N/A'" class="text-base font-bold text-white"></span>
                                    </div>
                                    <button type="button"
                                        @click="showTaxPopup = true;"
                                        :disabled="!taskData?.tax && (!taskData?.taxes_record || (Array.isArray(taskData?.taxes_record) && taskData.taxes_record.length === 0))"
                                        :class="(taskData?.tax || (taskData?.taxes_record && (!Array.isArray(taskData?.taxes_record) || taskData.taxes_record.length > 0))) ? 'cursor-pointer hover:bg-white/30 hover:shadow-lg' : 'cursor-not-allowed opacity-50'"
                                        class="w-full flex items-center justify-between bg-white/10 p-3 rounded-xl transition-all duration-200 border-0 text-left relative"
                                        style="z-index: 999 !important; position: relative;"
                                        :title="(taskData?.tax || (taskData?.taxes_record && (!Array.isArray(taskData?.taxes_record) || taskData.taxes_record.length > 0))) ? 'Click to view tax details' : 'No tax information available'">
                                        <span class="text-base text-white/90 font-medium">Tax:</span>
                                        <div class="flex items-center gap-2">
                                            <span x-text="taskData?.tax ? Number(taskData.tax).toFixed(3) + ' KWD' : 'N/A'" class="text-base font-bold text-white"></span>
                                            <svg x-show="taskData?.tax || taskData?.taxes_record" x-cloak class="w-5 h-5 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                    </button>
                                    <div class="flex items-center justify-between bg-white/10 p-3 rounded-xl hover:bg-white/20 transition-colors">
                                        <span class="text-base text-white/90 font-medium">Surcharge:</span>
                                        <span x-text="taskData?.surcharge ? Number(taskData.surcharge).toFixed(3) + ' KWD' : 'N/A'" class="text-base font-bold text-white"></span>
                                    </div>
                                    <div class="flex items-center justify-between bg-white/30 backdrop-blur-md p-3 rounded-xl border-2 border-white/50 shadow-xl mt-2">
                                        <span class="text-lg text-white font-bold">Total:</span>
                                        <span x-text="taskData?.total ? Number(taskData.total).toFixed(3) + ' KWD' : 'N/A'" class="text-xl font-black text-white drop-shadow-lg"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Information -->
                    <div x-show="taskData?.supplier" class="bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 rounded-2xl p-4 shadow-lg border border-orange-200 dark:border-orange-700">
                        <div class="flex items-center mb-3">
                            <div class="bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl p-2.5 shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <h4 class="ml-3 font-bold text-gray-900 dark:text-white text-lg">Supplier Information</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-md hover:shadow-lg transition-shadow">
                                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide">Supplier</span>
                                <p x-text="taskData?.supplier?.name" class="mt-2 text-base font-bold text-gray-900 dark:text-white"></p>
                            </div>
                            <div x-show="taskData?.issued_by" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-md hover:shadow-lg transition-shadow">
                                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide">Issued By</span>
                                <p x-text="taskData?.issued_by" class="mt-2 text-base font-bold text-gray-900 dark:text-white"></p>
                            </div>
                            <div x-show="taskData?.supplier_pay_date" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-md hover:shadow-lg transition-shadow">
                                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide">Pay Date</span>
                                <p x-text="taskData?.supplier_pay_date ? taskData.supplier_pay_date.split('T')[0] : ''" class="mt-2 text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Flight Details -->
                    <div x-show="taskData?.flight_details?.length > 0" class="bg-gradient-to-br from-blue-50 via-sky-50 to-cyan-50 dark:from-blue-900/20 dark:via-sky-900/20 dark:to-cyan-900/20 rounded-2xl p-4 shadow-xl border-2 border-blue-200 dark:border-blue-700">
                        <div class="flex items-center mb-4">
                            <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-3 shadow-lg animate-pulse">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path>
                                </svg>
                            </div>
                            <h4 class="ml-4 font-black text-gray-900 dark:text-white text-xl">Flight Details</h4>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(flight, index) in taskData?.flight_details" :key="flight.id">
                                <div class="relative bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border-l-4 border-blue-500 hover:border-blue-600 transition-all hover:shadow-2xl group">
                                    <div class="absolute top-4 right-4 bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md" x-text="'Flight ' + (index + 1)"></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
                                        <div x-show="flight.flight_number" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Flight Number</span>
                                            <p x-text="flight.flight_number" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="flight.airline_id || flight.airline" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Airline</span>
                                            <p x-text="(flight.airline?.name ? flight.airline.name + ' (' + flight.airline.iata_designator + ')' : flight.airline_id) || 'Unknown'" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="flight.country_id_from || flight.airport_from" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Departure</span>
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <p x-text="(flight.airport_from?.name ? flight.airport_from.name + ' (' + flight.airport_from.iata_code + ')' : flight.airport_from) || 'Unknown'" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                            </div>
                                        </div>
                                        <div x-show="flight.country_id_to || flight.airport_to" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Arrival</span>
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <p x-text="(flight.airport_to?.name ? flight.airport_to.name + ' (' + flight.airport_to.iata_code + ')' : flight.airport_to) || 'Unknown'" class="text-base font-bold text-gray-900 dark:text-white">
                                                </p>
                                            </div>
                                        </div>
                                        <div x-show="flight.class_type" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Class</span>
                                            <p x-text="flight.class_type" class="text-base font-bold text-gray-900 dark:text-white capitalize"></p>
                                        </div>
                                        <div x-show="flight.departure_time" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Departure Time</span>
                                            <p x-text="flight.departure_time" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                        </div>
                                        <div x-show="flight.arrival_time" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Arrival Time</span>
                                            <p x-text="flight.arrival_time" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                        </div>
                                        <div x-show="flight.seat_no" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Seat</span>
                                            <p x-text="flight.seat_no" class="text-base font-bold text-blue-600 dark:text-blue-400"></p>
                                        </div>
                                        <div x-show="flight.baggage_allowed" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Baggage</span>
                                            <p x-text="flight.baggage_allowed" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="flight.ticket_number" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Ticket #</span>
                                            <p x-text="flight.ticket_number" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                        </div>
                                        <div x-show="flight.farebase" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Farebase</span>
                                            <p x-text="flight.farebase" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="flight.equipment" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Equipment</span>
                                            <p x-text="flight.equipment" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Hotel Details -->
                    <div x-show="taskData?.hotel_details?.length > 0" class="bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50 dark:from-green-900/20 dark:via-emerald-900/20 dark:to-teal-900/20 rounded-2xl p-4 shadow-xl border-2 border-green-200 dark:border-green-700">
                        <div class="flex items-center mb-4">
                            <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-xl p-3 shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                </svg>
                            </div>
                            <h4 class="ml-4 font-black text-gray-900 dark:text-white text-xl">Hotel Details</h4>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(hotel, index) in taskData?.hotel_details" :key="hotel.id">
                                <div class="relative bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border-l-4 border-green-500 hover:border-green-600 transition-all hover:shadow-2xl">
                                    <div class="absolute top-4 right-4 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md" x-text="'Hotel ' + (index + 1)"></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                        <div x-show="hotel.hotel?.name" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Hotel Name</span>
                                            <p x-text="hotel.hotel.name" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="hotel.hotel?.country" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Country</span>
                                            <p x-text="hotel.hotel.country" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="hotel.check_in" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Check-in</span>
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                                </svg>
                                                <p x-text="hotel.check_in" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                            </div>
                                        </div>
                                        <div x-show="hotel.check_out" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Check-out</span>
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                                </svg>
                                                <p x-text="hotel.check_out" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                            </div>
                                        </div>
                                        <div x-show="hotel.room_type" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Room Type</span>
                                            <p x-text="hotel.room_type" class="text-base font-bold text-gray-900 dark:text-white capitalize"></p>
                                        </div>
                                        <div x-show="hotel.nights" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Nights</span>
                                            <p x-text="hotel.nights + ' night(s)'" class="text-base font-bold text-green-600 dark:text-green-400"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Visa Details -->
                    <div x-show="taskData?.visa_details?.length > 0" class="bg-gradient-to-br from-purple-50 via-violet-50 to-fuchsia-50 dark:from-purple-900/20 dark:via-violet-900/20 dark:to-fuchsia-900/20 rounded-2xl p-4 shadow-xl border-2 border-purple-200 dark:border-purple-700">
                        <div class="flex items-center mb-4">
                            <div class="bg-gradient-to-br from-purple-500 to-fuchsia-600 rounded-xl p-3 shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <h4 class="ml-4 font-black text-gray-900 dark:text-white text-xl">Visa Details</h4>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(visa, index) in taskData?.visa_details" :key="visa.id">
                                <div class="relative bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border-l-4 border-purple-500 hover:border-purple-600 transition-all hover:shadow-2xl">
                                    <div class="absolute top-4 right-4 bg-purple-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md" x-text="'Visa ' + (index + 1)"></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                        <div x-show="visa.visa_type" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Visa Type</span>
                                            <p x-text="visa.visa_type" class="text-base font-bold text-gray-900 dark:text-white capitalize"></p>
                                        </div>
                                        <div x-show="visa.country" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Country</span>
                                            <p x-text="visa.country" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="visa.processing_time" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Processing Time</span>
                                            <p x-text="visa.processing_time" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                        </div>
                                        <div x-show="visa.validity" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Validity</span>
                                            <p x-text="visa.validity" class="text-base font-bold text-purple-600 dark:text-purple-400"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Insurance Details -->
                    <div x-show="taskData?.insurance_details?.length > 0" class="bg-gradient-to-br from-yellow-50 via-amber-50 to-orange-50 dark:from-yellow-900/20 dark:via-amber-900/20 dark:to-orange-900/20 rounded-2xl p-4 shadow-xl border-2 border-yellow-200 dark:border-yellow-700">
                        <div class="flex items-center mb-4">
                            <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl p-3 shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <h4 class="ml-4 font-black text-gray-900 dark:text-white text-xl">Insurance Details</h4>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(insurance, index) in taskData?.insurance_details" :key="insurance.id">
                                <div class="relative bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border-l-4 border-yellow-500 hover:border-yellow-600 transition-all hover:shadow-2xl">
                                    <div class="absolute top-4 right-4 bg-yellow-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md" x-text="'Policy ' + (index + 1)"></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                        <div x-show="insurance.policy_number" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Policy Number</span>
                                            <p x-text="insurance.policy_number" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                        </div>
                                        <div x-show="insurance.coverage_amount" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Coverage Amount</span>
                                            <p x-text="insurance.coverage_amount" class="text-base font-bold text-yellow-600 dark:text-yellow-400"></p>
                                        </div>
                                        <div x-show="insurance.start_date" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">Start Date</span>
                                            <p x-text="insurance.start_date" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                        </div>
                                        <div x-show="insurance.end_date" class="space-y-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase">End Date</span>
                                            <p x-text="insurance.end_date" class="text-base font-bold text-gray-900 dark:text-white font-mono"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div x-show="taskData?.remarks || taskData?.created_at" class="bg-gradient-to-r from-gray-50 to-slate-100 dark:from-gray-800 dark:to-slate-800 rounded-2xl p-4 shadow-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center mb-3">
                            <div class="bg-gradient-to-br from-gray-500 to-slate-600 rounded-xl p-2.5 shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="ml-3 font-bold text-gray-900 dark:text-white text-lg">Additional Information</h4>
                        </div>
                        <div class="space-y-3">
                            <div x-show="taskData?.remarks" class="bg-white dark:bg-gray-900 rounded-xl p-3 shadow-md">
                                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide block mb-2">Remarks</span>
                                <p x-text="taskData?.remarks" class="text-base text-gray-900 dark:text-white leading-relaxed"></p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div x-show="taskData?.created_at" class="bg-white dark:bg-gray-900 rounded-xl p-3 shadow-md">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide block mb-2">Created</span>
                                    <div class="flex items-center space-x-2">
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        <p x-text="new Date(taskData?.created_at).toLocaleString()" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                    </div>
                                </div>
                                <div x-show="taskData?.updated_at" class="bg-white dark:bg-gray-900 rounded-xl p-3 shadow-md">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide block mb-2">Last Updated</span>
                                    <div class="flex items-center space-x-2">
                                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                        </svg>
                                        <p x-text="new Date(taskData?.updated_at).toLocaleString()" class="text-base font-bold text-gray-900 dark:text-white"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Enhanced Modal Footer -->
            <div class="relative bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 px-8 py-5 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Powered by City Tour</span>
                    </div>
                    <button @click="showTaskModal = false"
                        class="group px-6 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold flex items-center space-x-2 transform hover:scale-105">
                        <span>Close</span>
                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tax Details Popup -->
    <div x-show="showTaxPopup"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="showTaxPopup = false"
        @keydown.escape.window="showTaxPopup = false"
        class="fixed inset-0 z-[10002] flex items-center justify-center bg-gray-900 bg-opacity-75 backdrop-blur-sm">

        <!-- Tax Popup Content -->
        <div x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden border-2 border-emerald-500">

            <!-- Popup Header -->
            <div class="relative bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-4">
                <div class="absolute inset-0 bg-grid-pattern opacity-10"></div>
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 backdrop-blur-sm rounded-xl p-2 shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white tracking-tight">Tax Breakdown</h3>
                            <p class="text-sm text-emerald-100">Detailed tax information</p>
                        </div>
                    </div>
                    <button @click="showTaxPopup = false"
                        class="bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-xl p-2 transition-all duration-200 hover:rotate-90 transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Popup Body -->
            <div class="p-6 overflow-y-auto max-h-[calc(80vh-140px)] bg-gray-50 dark:bg-gray-900"> <!-- Total Tax Summary -->
                <div class="mb-6 bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl p-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-lg text-white/90 font-semibold">Total Tax:</span>
                        <span x-text="taskData?.tax ? Number(taskData.tax).toFixed(3) + ' KWD' : 'N/A'" class="text-2xl font-black text-white drop-shadow-lg"></span>
                    </div>
                </div>


                <!-- Tax Records -->
                <div x-show="taskData?.taxes_record && taskData?.taxes_record.toString().trim() !== ''" class="space-y-3">
                    <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                        </svg>
                        Tax Breakdown
                    </h4>

                    <!-- Display Raw Value -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-md border-l-4 border-emerald-500">
                        <pre x-text="taskData?.taxes_record" class="text-base font-mono text-gray-900 dark:text-white whitespace-pre-wrap break-words"></pre>
                    </div>
                </div>

                <!-- No Tax Records Message -->
                <div x-show="!taskData?.taxes_record || taskData?.taxes_record.toString().trim() === ''"
                    class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded-full mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">No detailed tax breakdown available</p>
                    <p x-show="taskData?.tax" class="text-sm text-gray-400 dark:text-gray-500 mt-2">Only total tax amount is recorded for this task</p>
                </div>
            </div>

            <!-- Popup Footer -->
            <div class="bg-gray-100 dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-end">
                    <button @click="showTaxPopup = false"
                        class="px-6 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:scale-105">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/partial/view-task-modal.blade.php ENDPATH**/ ?>