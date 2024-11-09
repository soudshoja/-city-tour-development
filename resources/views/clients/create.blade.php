<x-app-layout>
    <!-- Breadcrumbs -->
    <x-breadcrumbs :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Add Client'] ]" />
    <!-- ./Breadcrumbs -->


    <div class="grid grid-cols-3 gap-4">
        <!--  client details -->
        <div class="col-span-2 panel p-3">
            <div x-data="{ Form: false }">
                <a href="javascript:void(0);" @click.prevent="Form = ! Form"
                    class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                    <span
                        class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                        Entering client details
                    </span>
                </a>

                <div x-show="Form" class="my-5 px-5">
                    <form action="{{ route('clients.store') }}" method="POST">
                        @csrf
                        <!-- Name field -->
                        <div class="flex flex-col sm:flex-row">
                            <label for="name" class="mb-0  sm:w-1/4 sm:mr-2">Name</label>
                            <input id="name" name="name" type="text" required placeholder="Enter name"
                                class="form-input flex-1">
                        </div>
                        <!-- ./Name field -->

                        <!-- Email field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label for="email" class="mb-0  sm:w-1/4 sm:mr-2">Email</label>
                            <input id="email" name="email" type="email" required placeholder="Enter Email"
                                class="form-input flex-1">
                        </div>
                        <!-- ./Email field -->

                        <!-- passport number field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label for="passport_no" class="mb-0  sm:w-1/4 sm:mr-2">Passport Number</label>
                            <input id="passport_no" name="passport_no" type="text" required
                                placeholder="Enter Passport Number" class="form-input flex-1">
                        </div>

                        <!-- ./passport number field -->


                        <!-- Address field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label for="address" class="mb-0  sm:w-1/4 sm:mr-2">Address</label>
                            <input id="address" name="address" type="text" required placeholder="Enter Address"
                                class="form-input flex-1">
                        </div>

                        <!-- ./Address field -->


                        <!-- Status field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label class=" sm:w-1/4 sm:mr-2">Choose Status</label>
                            <div class="flex-1">
                                <div class="mb-2">
                                    <label class="inline-flex cursor-pointer">
                                        <input type="radio" name="status" value="active"
                                            class="peer form-radio outline-success">
                                        <span class="peer-checked:text-success pl-2">Active</span>
                                    </label>

                                </div>

                                <div class="mb-2">
                                    <label class="inline-flex cursor-pointer">
                                        <input type="radio" name="status" value="inactive"
                                            class="peer form-radio outline-danger">
                                        <span class="peer-checked:text-danger pl-2">Inactive</span>
                                    </label>
                                </div>

                            </div>
                        </div>
                        <!-- ./Status field -->


                        <!-- submit button -->
                        <div class="mt-5 flex justify-center">
                            <button
                                class="w-[80%] inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:text-white focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <span
                                    class="w-full px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                    Submit
                                </span>
                            </button>
                        </div>


                        <!-- ./submit button -->

                    </form>

                </div>
            </div>

        </div>
        <!-- ./client details -->

        <!--  upload client -->
        <div class="panel p-3">
            <a href=""
                class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                <span
                    class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                    Upload Client
                </span>
            </a>
        </div>
        <!-- ./upload client -->

    </div>

</x-app-layout>