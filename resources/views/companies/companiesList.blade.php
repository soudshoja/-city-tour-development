<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse">
        <li>
            <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] ltr:before:mr-1 rtl:before:ml-1">
            <span>Companies List</span>
        </li>
    </ul>

    <!-- Success Alert -->
    @if (session('success'))
    <div class="my-5 flex items-center rounded bg-success-light p-3.5 text-success dark:bg-success-dark-light">
        <span class="ltr:pr-2 rtl:pl-2"><strong class="ltr:mr-1 rtl:ml-1">Success!
            </strong>{{ session('success') }}</span>
        <button type="button" class="hover:opacity-80 ltr:ml-auto rtl:mr-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    @endif


    <div class="mt-5 panel">

        <div class="flex mb-5">
            <p>Click <a href="#" class="text-primary">here</a> to download the excel template</p>
        </div>
        <!-- Flex container for buttons and search input, with responsive handling for mobile -->
        <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">

            <!-- Buttons on the left -->
            <div class="flex space-x-2">
                <x-primary-button>Upload Excel</x-primary-button>
                <x-primary-button>PRINT</x-primary-button>
                <x-primary-button>Export CSV</x-primary-button>
            </div>

            <!-- Search input on the right -->
            <div class="w-full md:w-auto">
                <input type="text" placeholder="Search..."
                    class="w-full md:w-auto pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
            </div>
        </div>
    </div>

    <div class="mt-5 panel">
        <div class="overflow-x-auto">
            <table class="CityMobileTable table-fixed">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th>Nationality</th>
                        <th>Actions</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                    <tr>
                        <td>{{ $company->name }}</td>
                        <td>{{ $company->code }}</td>
                        <td>
                            <svg id="toggle-{{ $company->id }}" class="toggle-svg cursor-pointer" viewBox="0 0 44 24"
                                width="44" height="24"
                                onclick="toggleStatus({{ $company->id }}, '{{ $company->status }}')">
                                <rect width="44" height="24" rx="12"
                                    fill="{{ $company->status === 'active' ? 'green' : '#ccc' }}"></rect>
                                <circle cx="{{ $company->status === 'active' ? '32' : '12' }}" cy="12" r="10"
                                    fill="white">
                                </circle>
                            </svg>
                        </td>
                        <td>{{ $company->nationality }}</td>
                        <td class="md:absolute overflow-visible">
                            <!-- Actions dropdown menu -->
                            <div x-data="{ open: false }" class="inline-block text-left">
                                <a href="javascript:void(0)" @click="open = !open" class="cursor-pointer">
                                    <svg width="24" height="24" class="fill-current text-[#1C274C] dark:text-white"
                                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd"
                                            d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224Z"
                                            class="fill-current" />
                                        <path
                                            d="M15.5227 12C15.5227 13.6569 14.1694 15 12.4999 15C10.8304 15 9.47705 13.6569 9.47705 12C9.47705 10.3431 10.8304 9 12.4999 9C14.1694 9 15.5227 10.3431 15.5227 12Z"
                                            class="fill-current" />
                                    </svg>
                                </a>
                                <div x-show="open" @click.outside="open = false"
                                    class="relative mt-1 w-48 bg-white dark:bg-gray-800 shadow-lg rounded-md z-10 max-h-40 overflow-y-auto">
                                    <ul class="py-1">
                                        <li><a href="{{ route('companiesshow.show', $company->id) }}"
                                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Show
                                                Company</a></li>
                                        <li><a href="javascript:void(0);"
                                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                @click="suspendCompany({{ $company->id }})">Suspend Company</a></li>
                                        <li><a href="javascript:void(0);"
                                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                @click="terminateCompany({{ $company->id }})">Terminate Company</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>

                    </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

    </div>



</x-app-layout>