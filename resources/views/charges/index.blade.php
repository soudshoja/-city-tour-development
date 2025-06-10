<x-app-layout>

    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Charges</h2>
            <div data-tooltip="number of Charges"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalCharges }}</span>
            </div>
        </div>
        <!-- add new charge & refresh page -->
        <div class="flex items-center gap-5">
            <div data-tooltip="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
            <div id="createCharge" data-tooltip="Add new charge"
                class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm"
                onclick="window.location.href='{{ route('charges.create') }}';">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#fff"
                        d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7">
                    </path>
                </svg>
            </div>
        </div>


    </div>
    <!-- ./page title -->

    <!-- page content -->
    <div class="tableCon">
        <div class="content-70">
            <!-- Table  -->
            <div class="panel oxShadow rounded-lg">
                <!--  search icon -->
                <div class="relative">
                    <!-- Search Input -->
                    <input type="text" placeholder="Find fast and search here..."
                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                        id="searchInput">


                    <!-- Search Button with SVG Icon -->
                    <button type="button"
                        class="btn DarkBGcolor absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                        id="searchButton">
                        <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5"
                                opacity="0.5"></circle>
                            <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                    </button>
                </div>
                <!-- ./search icon -->

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div x-data="chargeEditor()" x-init="init()">
                        <div class="dataTable-container h-max">
                            <table id="myTable" class="table-hover whitespace-nowrap dataTable-table w-full" x-data="{ open: {} }">
                                <thead>
                                    <tr>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Charge Name</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Type</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Paid By</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Amount</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Charge Type</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Description</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($charges as $charge)
                                    <tr class="cursor-pointer bg-gray-100 hover:bg-gray-200" @click="open[{{ $charge->id }}] = !open[{{ $charge->id }}]">
                                        <td class="p-3 font-bold text-gray-800" colspan="7">
                                            {{ $charge->name }}
                                        </td>
                                    </tr>

                                    @if ($charge->methods->isNotEmpty())
                                    @foreach ($charge->methods as $method)
                                    <tr x-show="open[{{ $charge->id }}]" x-transition>
                                        <td class="p-3 pl-6 text-sm text-gray-600">{{ $method->english_name }}</td>
                                        <td class="p-3 text-sm text-gray-600">Child Method</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $method->paid_by }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $method->service_charge }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $method->charge_type }}</td>
                                        <td class="p-3 text-sm text-gray-600">
                                            {{ trim($method->description ?? '') !== '' ? $method->description : 'Not Set' }}
                                        </td>
                                        <td class="p-3 text-sm flex items-center gap-3">
                                            <div class="relative group inline-block">
                                                <button @click="openModal({{ $method->id }}, 'methods')" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <title>Edit</title>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536M9 11l6.768-6.768a2.5 2.5 0 113.536 3.536L12.536 14.5H9v-3.5z" />
                                                    </svg>
                                                </button>
                                                <div
                                                    class="absolute bottom-full mb-1 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                    Edit
                                                </div>
                                            </div>
                                            <form method="POST" action="{{ route('paymentMethod.destroy', $method->id) }}" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0a2 2 0 00-2-2H9a2 2 0 00-2 2m12 0H3" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                    @else
                                    <tr x-show="open[{{ $charge->id }}]" x-transition>
                                        <td colspan="7" class="p-3 pl-6 italic text-sm text-red-500 text-center align-middle">
                                            No child method for this payment gateway
                                        </td>
                                    </tr>
                                    <tr x-show="open[{{ $charge->id }}]" x-transition>
                                        <td class="p-3 pl-6 text-sm text-gray-600">{{ $charge->name }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->type }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->paid_by }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->amount }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->charge_type }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->description }}</td>
                                        <td class="p-3 text-sm flex items-center gap-3">
                                            <!-- Edit Button -->
                                            <div class="relative group inline-block">
                                                <a href="{{ route('charges.edit', $charge->id) }}" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <title>Edit</title>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536M9 11l6.768-6.768a2.5 2.5 0 113.536 3.536L12.536 14.5H9v-3.5z" />
                                                    </svg>
                                                </a>
                                                <div class="absolute bottom-full mb-1 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                    Edit
                                                </div>
                                            </div>

                                            <!-- Delete Button -->
                                            <div class="relative group inline-block">
                                                <form method="POST" action="{{ route('charges.destroy', $charge->id)}}" onsubmit="return confirm('Are you sure?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <title>Delete</title>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0a2 2 0 00-2-2H9a2 2 0 00-2 2m12 0H3" />
                                                        </svg>
                                                    </button>
                                                </form>
                                                <div class="absolute bottom-full mb-1 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                    Delete
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center p-3 text-sm text-gray-500">No charges found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div x-show="editModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
                            <div class="bg-white p-6 rounded-lg w-full max-w-lg shadow" @click.away="editModal = false">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-lg font-bold mb-4">Edit Method Gateway</h2>
                                    <button @click="closeAll()" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                                        &times;
                                    </button>
                                </div>
                                <form :action="`/paymentMethod/${editData.id}`" method="POST">

                                    @csrf
                                    @method('PUT')

                                    <div class="mb-4">
                                        <label class="block text-sm font-medium">Payment Gateway</label>
                                        <input type="text" name="gateway" x-model="editData.gateway" class="w-full border px-3 py-2 rounded-full capitalize" readonly>
                                    </div>
                                    <div class="mb-4 flex gap-4">
                                        <div class="w-1/2">
                                            <label class="block text-sm font-medium">Arabic Name</label>
                                            <input type="text" name="arabic_name" x-model="editData.arabic_name" class="w-full border px-3 py-2 rounded-full" readonly />
                                        </div>
                                        <div class="w-1/2">
                                            <label class="block text-sm font-medium">English Name</label>
                                            <input type="text" name="english_name" x-model="editData.english_name" class="w-full border px-3 py-2 rounded-full" readonly />
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium">Service Charge</label>
                                        <input type="text" name="service_charge" x-model="editData.service_charge" class="w-full border px-3 py-2 rounded-full">

                                    </div>
                                    <div class="mb-4 flex gap-4">
                                        <div class="w-1/2">
                                            <label class="block text-sm font-medium">Paid By</label>
                                            <select name="paid_by" x-model="editData.paid_by" class="w-full border px-3 py-2 rounded-full">
                                                <option value="Company">Company</option>
                                                <option value="Client">Client</option>

                                            </select>
                                        </div>
                                        <div class="w-1/2">
                                            <label class="block text-sm font-medium">Charge Type</label>
                                            <select name="charge_type" x-model="editData.charge_type" class="w-full border px-3 py-2 rounded-full">
                                                <option value="Flat Rate">Flat Rate</option>
                                                <option value="Percent">Percent</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium">Description</label>
                                        <input type="text" name="description" x-model="editData.description" class="w-full border px-3 py-2 rounded-full" />
                                    </div>

                                    <div class="flex justify-between items-center mt-6">
                                        <button type="button" @click="editModal = false" class="bg-gray-300 px-4 py-2 rounded-full">Cancel</button>
                                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-full">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- ./table -->


                    <!-- pagination -->
                    <div class="dataTable-bottom justify-center">
                        <nav class="dataTable-pagination">
                            <ul class="dataTable-pagination-list flex gap-2 mt-4">
                                <li class="pager" id="prevPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5"
                                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                                <!-- Dynamic page numbers will be injected here -->
                                <li class="pager" id="nextPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5"
                                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                            </ul>


                        </nav>
                    </div>
                    <!-- ./pagination -->
                </div>
            </div>

            <!-- ./Table  -->

        </div>
        <!-- right -->
        <div class="content-30 hidden">

            <div class="flex lg:flex-col md:flex-row justify-center text-center gap-5">
                <!-- customize -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                        <path fill="#333333"
                            d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                    </svg>
                    <span class="text-sm">Customize</span>
                </button>
                <!-- ./customize -->

                <!-- filter -->
                <button class="flex px-5 py-3 gap-2 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                    </svg>
                    <span class="text-sm">Filter</span>
                </button>
                <!-- ./filter -->

                <!-- export -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333"
                            d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                    </svg>
                    <span class="text-sm">Export</span>
                </button>
                <!-- ./export -->
            </div>
            <div class="mt-5 ">
                <!-- display charge details here-->
                <div id="chargeDetails" class="panel w-full xl:mt-0 rounded-lg h-auto hidden"></div>
                <!-- display charge details here-->

            </div>
        </div>
        <!-- ./right -->
    </div>
    <!--./page content-->
    <script>
        function chargeEditor() {
            return {
                editModal: false,
                editData: {},
                init() {},
                openModal(id, source = 'charges') {
                    const url = source === 'methods' ?
                        `/paymentMethod/${id}` // ✅ match your Laravel route
                        :
                        `/charges/${id}`;

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            this.editData = data;
                            this.editModal = true;
                        })
                        .catch(err => {
                            alert('Error loading data');
                            console.error(err);
                        });
                },
            }
        }
    </script>

</x-app-layout>