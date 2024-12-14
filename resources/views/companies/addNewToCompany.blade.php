<x-app-layout>
    <div class="container mx-auto mt-2">
        <!-- Title -->
        <div class="panel">
            <h1 class="text-center font-bold mb-2 text-md sm:text-lg md:text-xl lg:text-3xl">
                Choose a Record To Add New
            </h1>
        </div>

        <!-- Options Buttons -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 justify-items-center my-4">
            <!-- cards -->
            <div class="w-full">
                <!--Up Cards div -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <!-- Branch card -->
                    <div class="bg-yellow-100/50 rounded-xl p-6 shadow relative flex flex-col justify-between">
                        <div>
                            <div class="text-xs uppercase font-medium text-yellow-500 dark:text-yellow-400 mb-2">Branches</div>
                            <button data-form="branchForm" class="text-center flex items-center justify-center border rounded-lg border-yellow-800 p-2 w-full mt-8">
                                <span class="text-lg font-bold text-yellow-800">Add New Branch</span>
                                <svg
                                    class="w-6 h-6 text-yellow-800 ml-2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M17 14.5L12 19.5L7 14.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                            </button>

                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <span class="text-gray-500 dark:text-gray-400 text-sm">Total Company Branches</span>
                            <span class="text-yellow-500 dark:text-yellow-400 text-sm">360</span>
                        </div>
                        <div class="absolute top-4 right-4 bg-white p-2 rounded-full shadow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd" d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224Z" fill="#1C274C"></path>
                                <path d="M15.5227 12C15.5227 13.6569 14.1694 15 12.4999 15C10.8304 15 9.47705 13.6569 9.47705 12C9.47705 10.3431 10.8304 9 12.4999 9C14.1694 9 15.5227 10.3431 15.5227 12Z" fill="#1C274C"></path>
                            </svg>
                        </div>
                    </div>
                    <!-- ./Branch card -->

                    <!-- Agent card -->
                    <div class="bg-blue-100/50 rounded-xl p-6 shadow relative flex flex-col justify-between">
                        <div>
                            <div class="text-xs uppercase font-medium text-blue-500 dark:text-blue-400 mb-2">Agents</div>
                            <button data-form="agentForm" class="text-center flex items-center justify-center border rounded-lg  border-blue-800 p-2 w-full mt-8">
                                <span class="text-lg font-bold text-blue-800 dark:text-blue-300">Add New Agent</span>

                                <svg
                                    class="w-6 h-6 text-blue-800 dark:text-blue-300 ml-2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M17 14.5L12 19.5L7 14.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>

                            </button>

                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <span class="text-gray-500 dark:text-gray-400 text-sm">Total Company Agents</span>
                            <span class="text-blue-500 dark:text-blue-400 text-sm">580</span>
                        </div>
                        <div class="absolute top-4 right-4 bg-white p-2 rounded-full shadow">
                            <a href="{{ route('agentsetting') }}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd" d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224Z" fill="#1C274C"></path>
                                    <path d="M15.5227 12C15.5227 13.6569 14.1694 15 12.4999 15C10.8304 15 9.47705 13.6569 9.47705 12C9.47705 10.3431 10.8304 9 12.4999 9C14.1694 9 15.5227 10.3431 15.5227 12Z" fill="#1C274C"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <!-- ./Agent card -->
                </div>
                <!-- ./Up Cards div-->

                <!-- down Cards div -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <!-- Accountant card -->
                    <div class="bg-red-100/50 dark:bg-red-900/50 rounded-xl p-6 shadow relative flex flex-col justify-between">
                        <div>
                            <div class="text-xs uppercase font-medium text-red-500 dark:text-red-400 mb-2">Accountants</div>
                            <button data-form="accountantForm" class="text-center flex items-center justify-center border rounded-lg border-yellow-800 p-2 w-full mt-8">
                                <span class="text-lg font-bold text-red-800 dark:text-red-300">Add New Accountant</span>
                                <svg
                                    class="w-6 h-6 text-red-800 ml-2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M17 14.5L12 19.5L7 14.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                            </button>

                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <span class="text-gray-500 dark:text-gray-400 text-sm">Total Company Accountants</span>
                            <span class="text-red-500 dark:text-red-400 text-sm">80</span>
                        </div>
                        <div class="absolute top-4 right-4 bg-white p-2 rounded-full shadow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd" d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224Z" fill="#1C274C"></path>
                                <path d="M15.5227 12C15.5227 13.6569 14.1694 15 12.4999 15C10.8304 15 9.47705 13.6569 9.47705 12C9.47705 10.3431 10.8304 9 12.4999 9C14.1694 9 15.5227 10.3431 15.5227 12Z" fill="#1C274C"></path>
                            </svg>
                        </div>
                    </div>
                    <!-- ./Accountant card -->

                    <!-- Client card -->
                    <div class="bg-green-100/50 dark:bg-green-900/50 rounded-xl p-6 shadow relative flex flex-col justify-between">
                        <div>
                            <div class="text-xs uppercase font-medium text-green-500 dark:text-green-400 mb-2">Clients</div>
                            <button data-form="clientForm" class="text-center flex items-center justify-center border rounded-lg 
                            border-green-800 p-2 w-full mt-8">
                                <span class="text-lg font-bold text-green-800 dark:text-green-300">Add New Client</span>
                                <svg
                                    class="w-6 h-6 text-green-800 dark:text-green-300 ml-2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M17 14.5L12 19.5L7 14.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        opacity="0.5"
                                        d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                            </button>

                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <span class="text-gray-500 dark:text-gray-400 text-sm">Total Company Clients</span>
                            <span class="text-green-500 dark:text-green-400 text-sm">6980</span>
                        </div>
                        <div class="absolute top-4 right-4 bg-white p-2 rounded-full shadow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd" d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224Z" fill="#1C274C"></path>
                                <path d="M15.5227 12C15.5227 13.6569 14.1694 15 12.4999 15C10.8304 15 9.47705 13.6569 9.47705 12C9.47705 10.3431 10.8304 9 12.4999 9C14.1694 9 15.5227 10.3431 15.5227 12Z" fill="#1C274C"></path>
                            </svg>
                        </div>
                    </div>
                    <!-- ./Client card -->
                </div>
                <!-- ./down div Cards -->


            </div>
            <!-- ./cards -->

            <!-- Right card -->
            <!-- Div to Show the Forms -->
            <div id="initialDiv" class="relative w-full h-96 bg-blue-100/50 bg-cover bg-center rounded-lg flex justify-center items-center">
                <div class="bg-white p-6 rounded-lg shadow-lg text-center flex flex-col items-center">
                    <!-- Header -->
                    <h1 class="text-xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-gray-800">
                        Who’s To Add Today?
                    </h1>

                    <!-- Description -->
                    <p class="text-sm sm:text-base md:text-lg lg:text-xl text-gray-600 mt-4">
                        Choose what your company needs, and the form will appear here.
                    </p>
                </div>
            </div>

            <!-- ./div to show the forms -->

            <!--  Forms -->
            <div id="formDiv" class="hidden h-auto bg-[#1C274C] rounded-xl p-5 shadow relative flex flex-col justify-between w-full">
                <!-- forms to display -->
                <div class="mt-3">
                    <!-- Branch Form -->
                    <div id="branchForm" class="form hidden flex w-full h-auto">
                        <div class="w-full h-auto flex items-center justify-center ">
                            <form action="{{ route('companies.createBranch') }}" method="POST" class="w-full">
                                <h2 class="text-white font-bold text-center my-3 text-xl">Add New <span class="text-yellow-500 dark:text-yellow-400">Branch</span> Here</h2>

                                @csrf
                                <!-- Hidden Company ID -->
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">

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
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">


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
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">

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
                                <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">


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


            <!-- ./Right card -->
        </div>
        <!-- ./ Options Buttons -->

    </div>

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