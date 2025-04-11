<x-app-layout>

    <div class="p-8 flex flex-col gap-2">
        <!-- @can('create', App\Models\Company::class)
            <div class="col-span-2 bg-white shadow-lg rounded-lg p-6 dark:bg-dark">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold mb-4">Add New Company</h2>
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                        <img src="{{ asset('images/registeruser.jpg') }}" alt="User Registration"
                            class="w-full h-full object-cover rounded-full" />
                    </div>
                </div>

                <form method="POST" action="{{ route('companies.store') }}"
                    class="p-2 bg-gray-200 dark:bg-gray-500 rounded-lg">
                    @csrf
                    <input type="text" name="name" placeholder="Company Name"
                        class="mb-5 w-full border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400" />
                    <input type="email" name="email" placeholder="Company email"
                        class="mb-5 w-full border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400" />
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div class="mb-6">
                            <input type="text" name="code" placeholder="Company Code"
                                class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400">
                        </div>

                        <div class="mb-6">
                            <select id="country-select" name="country_id"
                                class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                <option value="" disabled selected>Select a country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" data-dial-code="{{ $country->dialing_code }}">
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>

                        </div>

                    </div>

                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <input type="text" name="address" placeholder="Address"
                                class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400">
                        </div>
                        <div>
                            <input type="text" id="phone" name="phone" placeholder="Phone"
                                class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400">
                        </div>
                    </div>

                    <h2 class="text-lg font-semibold mb-4 mt-8">Set Password</h2>

                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <input type="password" name="password" placeholder="password"
                                class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400"
                                required autocomplete="on">
                        </div>
                        <div>
                            <input type="password" name="password_confirmation" placeholder="confirm password"
                                class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400"
                                required autocomplete="on">
                        </div>
                    </div>

                    <div class="mb-6">

                        <div class="flex flex-col">
                            <div class="flex items-center space-x-4">
                                <label class="text-lg font-semibold mb-2">Select a status:</label>

                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="status" value="1" class="status-radio peer hidden"
                                        id="active" />
                                    <span
                                        class="flex items-center justify-center w-6 h-6 border border-gray-500 dark:border-white rounded-full peer-checked:border-[#00ab55] peer-checked:bg-[#00ab55] peer-checked:text-white peer-checked:font-semibold">
                                        <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                    </span>
                                    <span
                                        class="ml-2 text-lg text-gray-700 peer-checked:text-[#00ab55] peer-checked:font-semibold dark:text-white">Active</span>
                                </label>

                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="status" value="0" class="status-radio peer hidden"
                                        id="inactive" />
                                    <span
                                        class="flex items-center justify-center w-6 h-6 border border-gray-500 dark:border-white rounded-full peer-checked:border-[#e7515a] peer-checked:bg-[#e7515a] peer-checked:text-white peer-checked:font-semibold">
                                        <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                    </span>
                                    <span
                                        class="ml-2 text-lg text-gray-700 dark:text-white peer-checked:text-[#e7515a] peer-checked:font-semibold">Inactive</span>
                                </label>
                            </div>
                        </div>




                    </div>

                    <div class="flex items-center justify-between mt-8">
                        <button type="submit"
                            class="justify-center text-center text-black bg-koromiko-300 hover:bg-koromiko-400 focus:ring-4 focus:outline-none focus:ring-koromiko-400 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center mb-2 w-full border-0 uppercase shadow-md"
                            ;>
                            Add Company
                        </button>
                    </div>
                </form>
            </div>
        @endcan -->

        <div>
            <div class="flex justify-between items-center gap-5 my-3">
                <div class="flex items-center gap-5 ">
                    <h2 class="text-3xl font-bold">Add New</h2>
                </div>
            </div>

            <div class="w-full grid grid-cols-1 md:grid-cols-3 mt-5 gap-5">
           
           
                <div class="grid grid-cols-2 gap-5 col-span-1">
                    
                    <div class="w-full space-y-4">
                    @can('create', App\Models\Company::class)
                        <div data-form="companyForm"
                            class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                            <button class="text-left flex rounded-lg w-full">
                                <span class="text-md font-bold dark:text-white">Company</span>
                            </button>
                            <img src="{{ asset('images/registeruser.jpg') }}" alt="Company" class="w-10 h-10">
                        </div>
                    @endcan

                        @can('create', App\Models\Branch::class)
                            <div data-form="branchForm"
                                class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                                <button class="text-left flex rounded-lg w-full ">
                                    <span class="text-md font-bold dark:text-white">Branch</span>
                                </button>
                                <img src="{{ asset('images/BranchPic.png') }}" alt="Branch" class="w-10 h-10">
                            </div>
                        @endcan

                        @can('create', App\Models\Agent::class)
                            <div data-form="agentForm"
                                class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                                <button class="text-left flex rounded-lg w-full ">
                                    <span class="text-md font-bold dark:text-white">Agent</span>
                                </button>
                                <img src="{{ asset('images/AgentPic.png') }}" alt="Agent" class="w-10 h-10">
                            </div>
                        @endcan
                    </div>

                    <div class="w-full space-y-4">

                        @can('create', App\Models\Account::class)
                            <div data-form="accountantForm"
                                class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                                <button class="text-left flex rounded-lg w-full ">
                                    <span class="text-md font-bold dark:text-white">Accountant</span>
                                </button>
                                <img src="{{ asset('images/AccountantPic.png') }}" alt="Accountant" class="w-10 h-10">
                            </div>
                        @endcan

                        @can('create', App\Models\Client::class)
                            <div data-form="clientForm"
                                class="flex items-center justify-between px-5 py-2 bg-white dark:bg-gray-700 BoxShadow rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                                <button class="text-left flex rounded-lg w-full ">
                                    <span class="text-md font-bold dark:text-white">Client</span>
                                </button>
                                <img src="{{ asset('images/ClientPic.png') }}" alt="Client" class="w-10 h-10">
                            </div>
                        @endcan
                    </div>
                </div>

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
                    @if(auth()->user()->company !== null)
                    <div id="formDiv"
                        class="hidden h-auto bg-white dark:bg-gray-700 rounded-lg p-3 BoxShadow flex flex-col justify-between w-full">
                        <div class="my-5">
                            <!-- Company Form -->
                            <div id="companyForm" class="form hidden flex w-full h-auto">
                                <div class="w-full h-auto">
                                    <div class="flex items-center mb-5">
                                        <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                            <img src="{{ asset('images/registeruser.jpg') }}" alt="Company" class="w-10 h-10">
                                        </div>
                                        <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Company</h2>
                                    </div>

                                    <form method="POST" action="{{ route('companies.store') }}" class="w-full">
                                        @csrf
                                        <!-- First two fields: 1 column each -->
                                        <div class="mb-4 flex items-center relative">
                                            <input type="text" name="name" placeholder="Company Name" class="custom-input w-full" />
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Enter the company name.</span>
                                            </span>
                                        </div>
                                        <div class="mb-4 flex items-center relative">
                                            <input type="email" name="email" placeholder="Company Email" class="custom-input w-full" />
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Provide a valid email for the company.</span>
                                            </span>
                                        </div>

                                        <!-- Next two fields: 2 columns -->
                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div class="flex items-center relative">
                                                <input type="text" name="code" placeholder="Company Code" class="custom-input" />
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter a unique company code.</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center relative">
                                                <select name="country_id" class="custom-input">
                                                    <option value="" disabled selected>Select a country</option>
                                                    @foreach ($countries as $country)
                                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                                    @endforeach
                                                </select>
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Select the company's country.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Address and Phone: 2 columns -->
                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div class="flex items-center relative">
                                                <input type="text" name="address" placeholder="Address" class="custom-input" />
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Provide the company's address.</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center relative">
                                                <input type="text" name="phone" placeholder="Phone" class="custom-input" />
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter the company's phone number.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Password fields: 2 columns -->
                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div class="flex items-center relative">
                                                <input type="password" name="password" placeholder="Password" class="custom-input" />
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Set a secure password for the company.</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center relative">
                                                <input type="password" name="password_confirmation" placeholder="Confirm Password" class="custom-input" />
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Confirm the password.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn-success w-full text-white px-4 py-2 rounded-lg">Submit</button>
                                    </form>
                                </div>
                            </div>
                            <!-- Branch Form -->
                            <div id="branchForm" class="form hidden flex w-full h-auto">
                                <div class="w-full h-auto">
                                    <div class="flex items-center mb-5">
                                        <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                            <img src="{{ asset('images/BranchPic.png') }}" alt="Branch"
                                                class="w-10 h-10">
                                        </div>
                                        <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Branch</h2>
                                    </div>

                                    <form action="{{ route('companies.createBranch') }}" method="POST"
                                        class="w-full">
                                        @csrf
                                        <input type="hidden" name="company_id"
                                            value="{{ auth()->user()->company->id }}">

                                        <div class="mb-4 flex items-center relative">
                                            <input type="text" name="name" id="create_branch_name"
                                                class="custom-input" placeholder="Branch name *">
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Enter the branch name.</span>
                                            </span>
                                        </div>

                                        <div class="mb-4 flex items-center relative">
                                            <input type="email" name="email" id="branch_email"
                                                class="custom-input" placeholder="Branch Email *">
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Provide a valid email for branch
                                                    communication.</span>
                                            </span>
                                        </div>

                                        <div class="mb-6 flex items-center relative">
                                            <input type="password" name="password" class="custom-input" required
                                                placeholder="Branch Password *" autocomplete="on">
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Password must be at least 8 characters long and
                                                    include numbers.</span>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="mb-6 flex items-center relative">
                                                <div class="relative">
                                                    <select name="dial_code" id="dial_code"
                                                        class="custom-input w-50 px-2 pr-8 border border-[#6B7280] rounded-md appearance-none">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->dialing_code }}">
                                                                {{ $country->dialing_code }} ({{ $country->name }})
                                                            </option>
                                                        @endforeach
                                                        <!-- Add more country codes as needed -->
                                                    </select>
                                                    <!-- Custom dropdown arrow -->
                                                    <span
                                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-500">
                                                        ▼
                                                    </span>
                                                </div>
                                                <!-- Custom dropdown arrow -->

                                                <input type="tel" name="phone" id="branch_phone"
                                                    class="custom-input" placeholder="Phone number *">
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter the branch's contact number.</span>
                                                </span>
                                            </div>

                                            <div class="mb-6 flex items-center relative">
                                                <input type="text" name="address" id="branch_address"
                                                    class="custom-input" placeholder="Address *">
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Provide the branch's physical
                                                        location.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <button type="submit"
                                            class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
                                            Submit
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <style>
                                .tooltip-container {
                                    position: relative;
                                    display: inline-block;
                                }

                                .tooltip {
                                    position: absolute;
                                    top: 50%;
                                    right: 120%;
                                    transform: translateY(-50%);
                                    background-color: black;
                                    color: white;
                                    padding: 5px 10px;
                                    border-radius: 5px;
                                    font-size: 12px;
                                    white-space: nowrap;
                                    opacity: 0;
                                    visibility: hidden;
                                    transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
                                }

                                .tooltip-icon {
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    width: 15px;
                                    height: 15px;
                                    border-radius: 50%;
                                    background-color: white;
                                    color: red;
                                    font-weight: bold;
                                    font-size: 14px;
                                    text-align: center;
                                    border: 1px solid red;
                                    /* Added red border */
                                }

                                .tooltip-container:hover .tooltip {
                                    opacity: 1;
                                    visibility: visible;
                                }

                                /* Style for placeholder star */
                                ::placeholder {
                                    color: gray;
                                }

                                input::placeholder {
                                    font-weight: 400;
                                    font-size: 14px;
                                }

                                /* Add red star inside placeholder */
                                input::placeholder {
                                    content: '*';
                                }
                            </style>

                            <!-- Agent Form -->
                            <div id="agentForm" class="form hidden flex w-full h-auto">
                                <div class="w-full h-auto">
                                    <div class="flex items-center mb-5">
                                        <div class="rounded-full p-2 border-2 border-gray-300 dark:border-gray-600">
                                            <img src="{{ asset('images/AgentPic.png') }}" alt="Agent"
                                                class="w-10 h-10">
                                        </div>
                                        <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Agent</h2>
                                    </div>

                                    <form action="{{ route('agents.store') }}" method="POST"
                                        class="w-full">
                                        @csrf
                                        <input type="hidden" name="company_id"
                                            value="{{ auth()->user()->company->id }}">

                                        <div class="mb-4 flex items-center relative">
                                            <input type="text" name="name" class="custom-input"
                                                placeholder="Agent Name *">
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Enter the agent's full name.</span>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="mb-4 flex items-center relative">
                                                <input type="email" name="email" class="custom-input"
                                                    placeholder="Agent Email *">
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Provide a valid email for agent
                                                        communication.</span>
                                                </span>
                                            </div>

                                            <div class="mb-4 flex items-center relative">
                                                <div class="relative">
                                                    <select name="dial_code" id="dial_code"
                                                        class="custom-input w-50 px-2 pr-8 border border-[#6B7280] rounded-md appearance-none">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->dialing_code }}">
                                                                {{ $country->dialing_code }} ({{ $country->name }})
                                                            </option>
                                                        @endforeach
                                                        <!-- Add more country codes as needed -->
                                                    </select>
                                                    <!-- Custom dropdown arrow -->
                                                    <span
                                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-500">
                                                        ▼
                                                    </span>
                                                </div>

                                                <input type="tel" name="phone" class="custom-input"
                                                    placeholder="Agent Number *">
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter the agent's contact number.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mb-6 flex items-center relative">
                                            <input type="password" name="password" class="custom-input" required
                                                placeholder="Agent Password *" autocomplete="on">
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Password must be at least 8 characters long and
                                                    include numbers.</span>
                                            </span>
                                        </div>

                                        <div class="flex w-full my-3 gap-5">
                                            <div class="custom-select w-full border rounded-lg relative">
                                                <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">
                                                    Select Agent Type</div>
                                                <div
                                                    class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid grid-cols-2 gap-2 py-3">
                                                    @foreach ($agentTypes as $type)
                                                        <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800 border border-gray-300 cursor-pointer"
                                                            data-value="{{ $type->id }}">
                                                            {{ $type->name }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <input type="hidden" name="type_id" id="selectedType">
                                            </div>

                                            <div class="custom-select w-full border rounded-lg relative">
                                                <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">
                                                    Select Branch</div>
                                                <div
                                                    class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                                    @foreach ($branches as $branch)
                                                        <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800 border border-gray-300 cursor-pointer"
                                                            data-value="{{ $branch->id }}">
                                                            {{ $branch->name }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <input type="hidden" name="branch_id" id="selectedBranch">
                                            </div>
                                        </div>

                                        <button type="submit"
                                            class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
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
                                            <img src="{{ asset('images/AccountantPic.png') }}" alt="Accountant"
                                                class="w-10 h-10">
                                        </div>
                                        <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Accountant</h2>
                                    </div>
                                    <form action="{{ route('companies.createAccountant') }}" method="POST"
                                        class="w-full space-y-4">
                                        @csrf
                                        <!-- Hidden Company ID -->
                                        <input type="hidden" name="company_id"
                                            value="{{ auth()->user()->company->id }}">

                                        <!-- Form Grid Layout -->
                                        <div class="grid grid-cols-1 gap-4">
                                            <!-- Accountant Name -->
                                            <div class="flex items-center relative">
                                                <input type="text" id="accountant_name" name="name"
                                                    class="custom-input" placeholder="Accountant Name *" required>
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter the accountant's full name.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Accountant Email -->
                                            <div class="flex items-center relative">
                                                <input type="email" id="accountant_email" name="email"
                                                    class="custom-input" placeholder="Accountant Email *" required>
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Provide a valid email for accountant
                                                        communication.</span>
                                                </span>
                                            </div>

                                            <!-- Accountant Phone -->
                                            <div class="flex items-center relative">
                                                <div class="relative">
                                                    <select name="dial_code" id="dial_code"
                                                        class="custom-input w-50 px-2 pr-8 border border-[#6B7280] rounded-md appearance-none">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->dialing_code }}">
                                                                {{ $country->dialing_code }} ({{ $country->name }})
                                                            </option>
                                                        @endforeach
                                                        <!-- Add more country codes as needed -->
                                                    </select>
                                                    <!-- Custom dropdown arrow -->
                                                    <span
                                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-500">
                                                        ▼
                                                    </span>
                                                </div>

                                                <input type="tel" id="accountant_phone" name="phone"
                                                    class="custom-input" placeholder="Accountant Phone *" required>
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter the accountant's contact number.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <button type="submit"
                                            class="btn-success w-full text-white px-4 py-2 rounded-lg mt-4">
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
                                            <img src="{{ asset('images/ClientPic.png') }}" alt="Client"
                                                class="w-10 h-10">
                                        </div>
                                        <h2 class="font-bold text-xl pl-4 text-gray-800 dark:text-white">Adding New Client</h2>
                                    </div>

                                    <form action="{{ route('clients.store') }}" method="POST"
                                        class="w-full">
                                        @csrf
                                        <input type="hidden" name="company_id"
                                            value="{{ auth()->user()->company->id }}">

                                        <div class="mb-4 flex items-center relative">
                                            <input type="text" name="name" class="custom-input"
                                                placeholder="Client Name *">
                                            <span class="tooltip-container ml-2 cursor-pointer">
                                                <span class="tooltip-icon">!</span>
                                                <span class="tooltip">Enter the full name of the client.</span>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="mb-4 flex items-center relative">
                                                <input type="email" name="email" class="custom-input"
                                                    placeholder="Client Email *">
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter a valid email address for client
                                                        communication.</span>
                                                </span>
                                            </div>

                                            <div class="mb-4 flex items-center relative">
                                                <div class="relative">
                                                    <select name="dial_code" id="dial_code"
                                                        class="custom-input w-50 px-2 pr-8 border border-[#6B7280] rounded-md appearance-none">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->dialing_code }}">
                                                                {{ $country->dialing_code }} ({{ $country->name }})
                                                            </option>
                                                        @endforeach
                                                        <!-- Add more country codes as needed -->
                                                    </select>
                                                    <!-- Custom dropdown arrow -->
                                                    <span
                                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-500">
                                                        ▼
                                                    </span>
                                                </div>

                                                <input type="tel" name="phone" class="custom-input"
                                                    placeholder="Client Phone *" required>
                                                <span class="tooltip-container ml-2 cursor-pointer">
                                                    <span class="tooltip-icon">!</span>
                                                    <span class="tooltip">Enter a valid phone number.</span>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Agent Selection -->


                                        <!-- Options Container -->
                                        {{-- <div
                                                class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($agents) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3"> --}}
                                        <select
                                            class="custom-select w-full border rounded-lg px-4 py-2 dark:text-white dark:bg-gray-700"
                                            name="agent_id" id="agent_id">
                                            <option value="" disabled> Select Agent </option>
                                            @foreach ($agents as $agent)
                                                <option class="" value="{{ $agent->id }}">
                                                    {{ $agent->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <!-- ./Agent Selection -->
                                        <button type="submit"
                                            class="btn-success mt-5 w-full text-white px-4 py-2 rounded-lg">
                                            Submit
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>

                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
<script>
    document.addEventListener("DOMContentLoaded", function() {

        // Add event listeners for all data-form buttons
        document.querySelectorAll('[data-form]').forEach((button) => {
            button.addEventListener('click', () => {
                const initialDiv = document.getElementById('initialDiv');
                const formDiv = document.getElementById('formDiv');

                if (initialDiv) initialDiv.classList.add('hidden');
                if (formDiv) formDiv.classList.remove('hidden');

                // Hide all forms inside the form container
                document.querySelectorAll('.form').forEach((form) => form.classList.add(
                    'hidden'));

                // Show the specific form based on the data-form attribute
                const formId = button.getAttribute('data-form');
                console.log('formId: ', formId);
                const formToShow = document.getElementById(formId);
                console.log('formToShow: ', formToShow);
                if (formToShow) {
                    formToShow.classList.remove('hidden');
                } else {
                    console.error(`Form with ID '${formId}' not found.`);
                }
            });
        });

        // Check if URL has the 'openForm' parameter
        const urlParams = new URLSearchParams(window.location.search);
        const openForm = urlParams.get("openForm");

        if (openForm) {
            // Find the button that corresponds to the form and trigger a click
            const buttonToClick = document.querySelector(`[data-form="${openForm}"]`);
            if (buttonToClick) {
                buttonToClick.click();
            } else {
                console.error(`Button with data-form='${openForm}' not found.`);
            }
        }



        const countrySelect = document.getElementById("country-select");
        const phoneInput = document.getElementById("phone");

        if (!countrySelect || !phoneInput) {
            console.error("Country select or phone input not found in the DOM.");
            return;
        }

        countrySelect.addEventListener("change", function() {
            const selectedOption = countrySelect.options[countrySelect.selectedIndex];
            let dialCode = selectedOption.getAttribute("data-dial-code");

            if (dialCode) {
                dialCode = dialCode.replace(/[^+\d]/g, ""); // Keep only + and digits

                // Remove any existing dial code from the phone input
                phoneInput.value = phoneInput.value.replace(/^\+\d+/, "").trim();

                // Set the new dial code
                phoneInput.value = dialCode + "";
                phoneInput.focus();
            }
        });




    });
</script>
