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
    <div class="w-full grid grid-cols-1 md:grid-cols-3 mt-5 gap-5">
        <!-- first div -->
        <div class="grid grid-cols-2 gap-5 col-span-1">
            <div class="w-full space-y-4">

                <!-- add branch-->
                <div data-form="branchForm" class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold dark:text-white">Branch</span>
                    </button>
                    <img src="{{ asset('images/BranchPic.png') }}" alt="Branch" class="w-10 h-10">
                </div>
                <!-- ./add branch -->

                <!-- add agent -->
                <div data-form="agentForm" class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold dark:text-white">Agent</span>
                    </button>
                    <img src="{{ asset('images/AgentPic.png') }}" alt="Agent" class="w-10 h-10">
                </div>
                <!-- ./add agent -->

            </div>

            <div class="w-full space-y-4">

                <!-- add accountant -->
                <div data-form="accountantForm" class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md md:text-sm font-bold dark:text-white">Accountant</span>
                    </button>
                    <img src="{{ asset('images/AccountantPic.png') }}" alt="Accountant" class="w-10 h-10">
                </div>
                <!-- ./add accountant -->

                <!-- add client -->
                <div data-form="clientForm" class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                    <button class="text-left flex rounded-lg w-full ">
                        <span class="text-md font-bold dark:text-white">Client</span>
                    </button>
                    <img src="{{ asset('images/ClientPic.png') }}" alt="Client" class="w-10 h-10">
                </div>
                <!-- ./add client -->
            </div>
        </div>
        <!-- ./first div -->

        <!-- second div -->
        <div class="col-span-1 md:col-span-2">
            <!-- Div to Show the Forms -->
            <div id="initialDiv" class="panel BoxShadow h-full p-5 shadow bg-white dark:bg-gray-700">
                <div class="justify-between items-center flex flex-col md:flex-row">
                    <div class="items-center gap-5 text-center md:text-left">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                            Who’s Joining The Company Today?
                        </h1>
                        <p class="text-gray-500 mt-4 dark:text-gray-300">
                            Add new Branch, Agent, Accountant or Client to your company
                        </p>
                    </div>
                    <img src="{{ asset('images/AddnewPic.png') }}" alt="Add New" class="mt-4 md:mt-0">
                </div>
            </div>
            <!-- ./div to show the forms -->

            <!--  Forms -->
            <div id="formDiv" class="hidden h-auto bg-white dark:bg-gray-700 rounded-lg p-3 BoxShadow flex flex-col justify-between w-full">
                <!-- forms to display -->
                <div class="my-5">
                    <!-- Branch Form -->
                    <div id="branchForm" class="form hidden flex w-full h-auto">
                        <div class="w-full h-auto">
                            <div class="flex items-center mb-5">
                                <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                    <img src="{{ asset('images/BranchPic.png') }}" alt="Branch" class="w-10 h-10">
                                </div>
                                <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Branch</h2>
                            </div>

                            <form action="{{ route('companies.createBranch') }}" method="POST" class="w-full">
                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">

                                <!-- Branch Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="text" name="name" id="branch_name"
                                        class="custom-input"
                                        required placeholder="Branch name ">
                                </div>

                                <!-- Email -->
                                <div class="mb-4 flex items-center">
                                    <input type="email" name="email" id="branch_email"
                                        class="custom-input" required placeholder="Branch Email">
                                </div>

                                <!-- Password -->
                                <div class="mb-6">
                                    <input type="password" name="password" class="custom-input" required placeholder="Branch Password">
                                </div>


                                <div class="grid grid-cols-2 gap-4">
                                    <div class="mb-6">
                                        <input type="tel" name="phone" id="branch_phone" class="custom-input" placeholder="phone number" pattern="[0-9]{3}-[0-9]{2}-[0-9]{3}">
                                    </div>


                                    <!-- Address -->
                                    <div class="mb-6">
                                        <input type="text" name="address" id="branch_address" class="custom-input" placeholder=" Address">
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
                                    Submit
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Agent Form -->
                    <div id="agentForm" class="form hidden flex w-full h-auto">
                        <!-- Right Section: Form -->
                        <div class="w-full h-auto">

                            <!-- Form Header -->
                            <div class="flex items-center mb-5">
                                <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                    <img src="{{ asset('images/AgentPic.png') }}" alt="Branch" class="w-10 h-10">
                                </div>
                                <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Agent</h2>
                            </div>

                            <form action="{{ route('companies.createAgent') }}" method="POST" class="w-full p-2">
                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">


                                <!-- Agent Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="name" name="name" class="custom-input"
                                        required placeholder="Agent Name">
                                </div>

                                <!-- Email & phone number -->
                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Email -->
                                    <div class="mb-4 flex items-center">
                                        <input type="email" name="email"
                                            class="custom-input"
                                            required placeholder="Agent Email">
                                    </div>


                                    <!-- Phone -->
                                    <div class="mb-4">
                                        <input type="tel" name="phone"
                                            class="custom-input" placeholder="Agent Number" pattern="[0-9]{3}-[0-9]{2}-[0-9]{3}">
                                    </div>
                                </div>


                                <!-- Password -->
                                <div class="mb-6">
                                    <input type="password" name="password"
                                        class="custom-input" placeholder="Agent Password">
                                </div>


                                <div class="flex w-full my-3 gap-5">

                                    <!-- Agent Type -->
                                    <div class="custom-select w-full border rounded-lg">
                                        <!-- Trigger -->
                                        <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">Select Agent Type</div>

                                        <!-- Options Container -->
                                        <div class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid grid-cols-2 gap-2 py-3">
                                            @foreach ($agentTypes as $type)
                                            <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800
                             border border-gray-300 cursor-pointer" data-value="{{ $type->id }}">
                                                {{ $type->name }}
                                            </div>
                                            @endforeach
                                        </div>

                                        <!-- Hidden input to store selected value -->
                                        <input type="hidden" name="type_id" id="selectedType">
                                    </div>
                                    <!-- ./Agent Type -->


                                    <!-- Branch Selection -->
                                    <div class="custom-select w-full border rounded-lg">
                                        <!-- Trigger -->
                                        <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">Select Branch</div>

                                        <!-- Options Container -->
                                        <div class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                            @foreach ($branches as $branch)
                                            <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800
                             border border-gray-300 cursor-pointer" data-value="{{ $branch->id }}">
                                                {{ $branch->name }}
                                            </div>
                                            @endforeach
                                        </div>

                                        <!-- Hidden input to store selected value -->
                                        <input type="hidden" name="branch_id" id="selectedBranch">
                                    </div>
                                    <!-- ./Branch Selection -->

                                </div>


                                <!-- Submit Button -->
                                <button type="submit" class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
                                    Submit
                                </button>
                            </form>

                        </div>
                    </div>

                    <!-- Accountant Form -->
                    <div id="accountantForm" class="form hidden flex w-full h-auto">

                        <div class="w-full h-auto">
                            <div class="flex items-center mb-5">
                                <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                    <img src="{{ asset('images/AccountantPic.png') }}" alt="Accountant" class="w-10 h-10">
                                </div>
                                <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Accountant</h2>
                            </div>
                            <form action="{{ route('companies.createAccountant') }}" method="POST" class="w-full">

                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">

                                <!-- Accountant Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="name" name="name" class="custom-input"
                                        required placeholder="Accountant Name">
                                </div>


                                <!-- Accountant Email -->
                                <div class="mb-4 flex items-center">
                                    <input type="email" name="email" class="custom-input"
                                        required placeholder="Accountant Email">
                                </div>


                                <!-- Accountant Phone -->
                                <div class="mb-4 flex items-center">
                                    <input type="tel" name="phone" class="custom-input"
                                        required placeholder="Accountant Email" pattern="[0-9]{3}-[0-9]{2}-[0-9]{3}">
                                </div>


                                <!-- Submit Button -->
                                <button type="submit" class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
                                    Submit
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Client Form -->
                    <div id="clientForm" class="form hidden flex w-full h-auto">
                        <div class="w-full h-auto">
                            <div class="flex items-center mb-5">
                                <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                    <img src="{{ asset('images/ClientPic.png') }}" alt="Client" class="w-10 h-10">
                                </div>
                                <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Client</h2>
                            </div>
                            <form action="{{ route('companies.createClient') }}" method="POST" class="w-full">
                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">

                                <!-- Client Name -->
                                <div class="mb-4 flex items-center">
                                    <input type="text" name="name" class="custom-input"
                                        required placeholder="Client Name">
                                </div>

                                <!-- Client Email -->
                                <div class="mb-4 flex items-center">
                                    <input type="email" name="email" class="custom-input"
                                        required placeholder="Client Email">
                                </div>

                                <!-- Client Phone -->
                                <div class="mb-4 flex items-center">
                                    <input type="number" name="phone" class="custom-input"
                                        required placeholder="Client Phone" pattern="[0-9]{3}-[0-9]{2}-[0-9]{3}">
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
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