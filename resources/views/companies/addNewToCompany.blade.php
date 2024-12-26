<x-app-layout>


    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Add New</h2>
        </div>
    </div>
    <!-- ./page title -->

    <!-- page content -->

    <!-- add & forms -->
    <div class="w-full grid grid-cols-3 mt-5 gap-5">
        <!-- first div -->
        <div class="grid grid-cols-2 gap-5 col-span-1">
            <div class="w-full space-y-4">
                <div data-form="branchForm" class="flex items-center justify-between px-5 py-2 bg-white BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold">Branch</span>
                    </button>
                    <img src="{{ asset('images/BranchPic.png') }}" alt="Branch" class="w-10 h-10">
                </div>

                <div data-form="agentForm" class="flex items-center justify-between px-5 py-2 bg-white BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold">Agent</span>
                    </button>
                    <img src="{{ asset('images/AgentPic.png') }}" alt="Accountant" class="w-10 h-10">
                </div>

            </div>
            <div class="w-full space-y-4">
                <div data-form="accountantForm" class="flex items-center justify-between px-5 py-2 bg-white BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold">Accountant</span>
                    </button>
                    <img src="{{ asset('images/AccountantPic.png') }}" alt="Accountant" class="w-10 h-10">
                </div>

                <div data-form="clientForm" class="flex items-center justify-between px-5 py-2 bg-white BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold">Client</span>
                    </button>
                    <img src="{{ asset('images/ClientPic.png') }}" alt="Accountant" class="w-10 h-10">

                </div>
            </div>
        </div>
        <!-- ./first div -->

        <!-- second div -->
        <div class="col-span-2">
            <!-- Div to Show the Forms -->
            <div id="initialDiv" class="panel BoxShadow h-full p-5 shadow">
                <div class="justify-between items-center flex">
                    <div class=" items-center gap-5">
                        <h1 class="text-xl font-bold text-gray-800">
                            Who’s Joining The Company Today?
                        </h1>
                        <p class="text-gray-500 mt-2">
                            Add new Branch, Agent, Accountant or Client to your company
                    </div>

                    <img src="{{ asset('images/AddnewPic.png') }}">

                </div>
            </div>
            <!-- ./div to show the forms -->

            <!--  Forms -->
            <div id="formDiv" class="hidden h-auto bg-white rounded-lg p-3 BoxShadow  flex flex-col justify-between w-full">
                <!-- forms to display -->
                <div class="mt-2">
                    <!-- Branch Form -->
                    <div id="branchForm" class="form hidden flex w-full h-auto">
                        <div class="w-full h-auto">
                            <h2 class="font-bold mb-3 text-xl">Adding New Branch</h2>
                            <form action="{{ route('companies.createBranch') }}" method="POST" class="w-full">


                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">

                                <!-- Branch Name -->
                                <div class="mb-4 flex items-center ">
                                    <input type="text" name="name" id="branch_name" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg" required placeholder="Branch name ">
                                </div>

                                <!-- Email -->
                                <div class="mb-4 flex items-center">
                                    <input type="email" name="email" id="branch_email" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder=" Branch Email">
                                </div>

                                <!-- Password -->
                                <div class="mb-6">
                                    <input type="password" name="password"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg" placeholder=" Agent Password">
                                </div>


                                <div class="grid grid-cols-2 gap-4">
                                    <div class="mb-6">
                                        <input type="text" name="phone" id="branch_phone"
                                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg" placeholder=" phone number">
                                    </div>


                                    <!-- Address -->
                                    <div class="mb-6">
                                        <input type="text" name="address" id="branch_address"
                                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg" placeholder=" Address">
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btnCityGrayColor mt-3 w-full text-white px-4 py-2 rounded-lg ">
                                    Submit
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Agent Form -->
                    <div id="agentForm" class="form hidden flex w-full h-auto">
                        <!-- Right Section: Form -->
                        <div class="w-full h-auto flex items-center justify-center">
                            <form action="{{ route('companies.createAgent') }}" method="POST" class="w-full p-2">
                                <h2 class="text-white font-bold text-center my-3 text-xl">Add New
                                    <span class="text-blue-500 dark:text-blue-400">Agent</span> Here
                                </h2>

                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">


                                <!-- Agent Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="name" name="name" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder=" Agent Name">
                                </div>

                                <!-- Email & phone number -->
                                <div class="grid grid-cols-2 gap-4">

                                    <!-- Email -->
                                    <div class="mb-4 flex items-center">
                                        <input type="email" name="email"
                                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                            required placeholder=" Agent Email">
                                    </div>


                                    <!-- Phone -->
                                    <div class="mb-4">
                                        <input type="phone" name="phone"
                                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg" placeholder=" agent Number">
                                    </div>


                                </div>


                                <!-- Password -->
                                <div class="mb-6">
                                    <input type="password" name="password"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg" placeholder=" Agent Password">
                                </div>


                                <!-- Agent Type -->
                                <div class="flex w-full my-3">
                                    <!-- Label -->
                                    <div
                                        class="w-[40%] flex items-center justify-center border border-[#e0e6ed] bg-[#eee] px-4 py-2 rounded-l-md dark:border-[#17263c] dark:bg-[#1b2e4b]">
                                        Select Agent Type
                                    </div>
                                    <!-- Select Box -->
                                    <select
                                        name="type_id"
                                        class="w-[60%] px-4 py-2 rounded-r-md border-l-0 flex items-center justify-center border border-[#e0e6ed] dark:border-[#17263c] dark:bg-[#1b2e4b]"
                                        required>
                                        @foreach ($agentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                </div>



                                <!-- Branch Selection -->
                                <div class="mb-4">
                                    <select name="branch_id" id="agent_branch" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                                        <option value="">Select Branch</option>
                                        @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btnCityGrayColor mt-3 w-full text-white px-4 py-2 rounded-lg">
                                    Submit
                                </button>
                            </form>
                        </div>
                    </div>


                    <!-- Accountant Form -->
                    <div id="accountantForm" class="form hidden flex w-full h-auto">

                        <div class="w-full h-auto flex items-center justify-center">
                            <form action="{{ route('companies.createAccountant') }}" method="POST" class="w-full p-2">
                                <h2 class="text-white font-bold text-center my-3 text-xl">Add New <span class="text-red-500 dark:text-red-400">Accountant</span> Here</h2>

                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">

                                <!-- Accountant Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="name" name="name" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder="Accountant Name">
                                </div>


                                <!-- Accountant Email -->
                                <div class="mb-4 flex items-center">
                                    <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder="Accountant Email">
                                </div>


                                <!-- Accountant Phone -->
                                <div class="mb-4 flex items-center">
                                    <input type="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder="Accountant Email">
                                </div>



                                <!-- Submit Button -->
                                <button type="submit" class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg   ">
                                    Submit
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Client Form -->
                    <div id="clientForm" class="form hidden flex w-full h-auto">

                        <div class="w-full h-auto flex items-center justify-center">
                            <form action="{{ route('companies.createClient') }}" method="POST" class="w-full p-2">
                                <h2 class="text-white font-bold text-center my-3 text-xl">Add New <span class="text-green-500 dark:text-green-400">Client</span> Here</h2>

                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">


                                <!-- Client Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="name" name="name" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder="Client Name">
                                </div>


                                <!-- Client Email -->
                                <div class="mb-4 flex items-center">
                                    <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder="Client email">
                                </div>


                                <!-- Client Phone -->
                                <div class="mb-4 flex items-center">
                                    <input type="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg"
                                        required placeholder="Client phone">
                                </div>



                                <!-- Submit Button -->
                                <button type="submit" class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg   ">
                                    Submit
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
                <!-- ./forms to display -->

            </div>
            <!-- ./ Forms -->
        </div>
        <!-- ./second div -->
    </div>

    <!-- ./add & forms -->




    <!--./ page content -->
    <script>
        // Add event listeners for all data-form buttons
        document.querySelectorAll('[data-form]').forEach((button) => {
            button.addEventListener('click', () => {
                const initialDiv = document.getElementById('initialDiv');
                const formDiv = document.getElementById('formDiv');

                // Hide the initial div
                initialDiv.classList.add('hidden');

                // Show the form container
                formDiv.classList.remove('hidden');

                // Hide all forms inside the form container
                document.querySelectorAll('.form').forEach((form) => form.classList.add('hidden'));

                // Show the specific form based on the data-form attribute
                const formId = button.getAttribute('data-form');
                const formToShow = document.getElementById(formId);

                if (formToShow) {
                    formToShow.classList.remove('hidden');
                } else {
                    console.error(`Form with ID '${formId}' not found.`);
                }
            });
        });
    </script>

</x-app-layout>