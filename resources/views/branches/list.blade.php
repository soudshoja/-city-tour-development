<x-app-layout>
    <div>
        <!-- Breadcrumbs -->
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Branches List']
        ]" />

        <!-- ./Breadcrumbs -->

        <!-- session status -->
        @if (session('success'))
        <div class="bg-green-500 text-white p-4 rounded mb-4">
            {{ session('success') }}
        </div>
        @endif

        @if (session('error'))
        <div class="bg-red-500 text-white p-4 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        <!-- ./session status -->

        <!-- Controls Section -->
        <div
            class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0 text-gray-700 dark:text-gray-300">

            <!-- left side -->
            <div
                class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <!-- left side -->
                <div class="flex gap-2 mr-2">
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                        href="#">
                        <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            Branches</span>


                    </a>
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700"
                        href="#"><span id="totalBranches"></span>
                    </a>
                </div>

            </div>


            <!-- right side -->
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <!-- Search Box -->
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>



                <!-- Add User Button -->

                <button type="button" onclick="addBranch()"
                    class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                    <svg class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Branch
                </button>
            </div>
        </div>
        <!-- ./Controls Section -->


        <div class="bg-white rounded-md shadow-md p-2 flex justify-start gap-2 my-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="">
                <path d="M12 17V11" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="1" cy="1" r="1" transform="matrix(1 0 0 -1 11 9)" fill="#1C274C" />
                <path d="M7 3.33782 C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            User with this icon is a new user and has not logged in yet. Please inform the user to login and change the password.
        </div>
        <!-- Table Section -->
        <div class=" overflow-x-auto bg-white shadow rounded-lg">
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <table class="AgentTable CityMobileTable w-full">
                    <thead class="sticky top-0">
                        <tr>
                            <th class="px-4 py-2">
                                <svg id="selectAllSVG" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg" class="dark:fill-white">
                                    <path
                                        d="M8.0374 14.1437C7.78266 14.2711 7.47314 14.1602 7.35714 13.9001L3.16447 4.49844C2.49741 3.00261 3.97865 1.45104 5.36641 2.19197L11.2701 5.344C11.7293 5.58915 12.2697 5.58915 12.7289 5.344L18.6326 2.19197C20.0204 1.45104 21.5016 3.00261 20.8346 4.49844L19.2629 8.02275C19.0743 8.44563 18.7448 8.78997 18.3307 8.99704L8.0374 14.1437Z"
                                        fill="#1C274C" class="dark:fill-white" />
                                    <path opacity="0.5"
                                        d="M8.6095 15.5342C8.37019 15.6538 8.26749 15.9407 8.37646 16.185L10.5271 21.0076C11.1174 22.3314 12.8818 22.3314 13.4722 21.0076L17.4401 12.1099C17.6313 11.6812 17.1797 11.2491 16.7598 11.459L8.6095 15.5342Z"
                                        fill="#1C274C" class="dark:fill-gray-400" />
                                </svg>

                                <input type="checkbox" id="selectAll" class="form-checkbox CheckBoxColor hidden">
                            </th>
                            <th class="flex px-4 py-2 cursor-pointer" id="nameHeader">

                                <svg id="sortIcon" class="mr-1 w-5 w-5" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M13 7L3 7" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M10 12H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M8 17H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <path
                                        d="M11.3161 16.6922C11.1461 17.07 11.3145 17.514 11.6922 17.6839C12.07 17.8539 12.514 17.6855 12.6839 17.3078L11.3161 16.6922ZM16.5 7L17.1839 6.69223C17.0628 6.42309 16.7951 6.25 16.5 6.25C16.2049 6.25 15.9372 6.42309 15.8161 6.69223L16.5 7ZM20.3161 17.3078C20.486 17.6855 20.93 17.8539 21.3078 17.6839C21.6855 17.514 21.8539 17.07 21.6839 16.6922L20.3161 17.3078ZM19.3636 13.3636L20.0476 13.0559L19.3636 13.3636ZM13.6364 12.6136C13.2222 12.6136 12.8864 12.9494 12.8864 13.3636C12.8864 13.7779 13.2222 14.1136 13.6364 14.1136V12.6136ZM12.6839 17.3078L17.1839 7.30777L15.8161 6.69223L11.3161 16.6922L12.6839 17.3078ZM21.6839 16.6922L20.0476 13.0559L18.6797 13.6714L20.3161 17.3078L21.6839 16.6922ZM20.0476 13.0559L17.1839 6.69223L15.8161 7.30777L18.6797 13.6714L20.0476 13.0559ZM19.3636 12.6136H13.6364V14.1136H19.3636V12.6136Z"
                                        fill="#1C274C" />
                                </svg>
                                <span>Name</span>
                            </th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2">phone number</th>

                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-300">
                        @foreach ($branches as $branch)
                        <tr class="">
                            <td class="px-4 py-2">
                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox">
                            </td>
                            <td class="px-4 py-2">{{ $branch->name }}</td>
                            <td class="px-4 py-2">{{ $branch->email }}</td>
                            <td class="px-4 py-2 inline-flex justify-between w-full">
                                {{ $branch->phone }}
                                @if($branch->user->first_login)
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="">
                                    <path d="M12 17V11" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <circle cx="1" cy="1" r="1" transform="matrix(1 0 0 -1 11 9)" fill="#1C274C" />
                                    <path d="M7 3.33782 C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                @endif
                            </td>
                        </tr>


                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ./Table Section -->

    <!-- add company modal -->
    <div id="addBranchModal" onclick="closeModalIbg(event)"
        class="fixed z-10 inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm hidden">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">

            <!-- Close Button (Top Right) -->
            <button onclick="closeAddBranchModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Modal Title -->
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Add New Branch
            </h2>

            <!-- Modal Form -->
            <!-- Registration Form -->
            <!-- Registration Form -->
            <form method="POST" action="{{ route('branches.store') }}">
                @csrf

                <!-- Name Field -->
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                    <input id="name" name="name" type="text" :value="old('name')" required autofocus autocomplete="name"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="branch Name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Email Field -->
                <div class="mb-4">
                    <label for="email"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                    <input id="email" type="email" name="email" :value="old('email')" required autocomplete="username"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="branch Email" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <!-- phone Field -->
                <div class="mb-4">
                    <label for="phone" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone
                        Number</label>
                    <input id="phone" name="phone" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="branch Contact" />
                </div>

                <!-- Address Field -->
                <div class="mb-4">
                    <label for="address"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                    <input id="address" name="address" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="branch Address" />
                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                </div>





                <!-- Already Registered Link -->
                <div class="flex items-center justify-between mt-4">

                    <!-- Submit Button -->
                    <x-primary-button class="px-8">
                        {{ __('Register') }}
                    </x-primary-button>


                </div>
            </form>
            <!-- ./Registration Form -->

        </div>
    </div>
    <!-- ./add company modal -->


    <script>
        // BSZ95 New code
        document.addEventListener("DOMContentLoaded", function() {
            // Access the data passed from the controller
            const companiesCount = @json($branchesCount);
            document.getElementById("totalBranches").innerText = companiesCount;
        });

        // Add Company Modal
        function addBranch() {
            document.getElementById("addBranchModal").classList.remove("hidden");
        }

        function closeAddBranchModal() {
            document.getElementById("addBranchModal").classList.add("hidden");
        }

        function closeModalIbg(event) {
            if (event.target.id === "addBranchModal") {
                document.getElementById("addBranchModal").classList.add("hidden");
            }
        }
    </script>

</x-app-layout>