<x-app-layout>



    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Invoices List</h2>
            <!-- total Invoice number -->
            <div data-tooltip="number of invoices"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalInvoices }}</span>
            </div>
        </div>
        <!-- add new Invoice & refresh page -->
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


            <!-- add new invoice -->
            <a href="{{ route('invoice.create') }}">
                <div data-tooltip="Create new Invoice"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">


                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>

                </div>
            </a>

        </div>


    </div>
    <!-- ./page title -->




    <!-- page content -->
    <div class="tableCon">
        <div class="content-70">
            <!-- Table  -->
            <div class="panel oxShadow rounded-lg">
                <div class="customResponsiveClass flex flex-col md:flex-row justify-between p-2 gap-3">
                    <!--  search icon -->
                    <div class="relative w-full">
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
                                <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round">
                                </path>
                            </svg>
                        </button>
                    </div>

                    <!-- ./search icon -->
                    <div class="flex customCenter gap-5 w-full justify-end">
                        <button id="toggleFilters"
                            class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                            <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                <path fill="#333333"
                                    d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                            </svg>
                            <span class="text-xs md:text-sm dark:text-black">Filters</span>
                        </button>
                    </div>
                </div>

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <!-- <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="form-checkbox hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </th> -->
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Invoice Number</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Client name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Payment Type</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Amount</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($invoices->isEmpty())
                                    <tr>
                                        <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">
                                            No data for now.... Create new!</td>
                                    </tr>
                                @else
                                    @foreach ($invoices as $invoice)
                                        {{-- @foreach ($invoice->invoiceDetails as $invoiceDetail) --}}
                                        {{-- @foreach ($invoices as $invoice) --}}
                                        @php
                                            // Retrieve the first invoice detail; adjust as needed if you want a different one.
                                            $invoiceDetail = $invoice->invoiceDetails->first();
                                        @endphp

                                        <tr data-price="{{ $invoice->total }}"
                                            data-supplier-id="{{ $invoiceDetail->task->supplier->id }}"
                                            data-branch-id="{{ $invoice->agent->branch->id }}"
                                            data-agent-id="{{ $invoice->agent_id }}"
                                            data-status="{{ $invoice->status }}"
                                            data-type="{{ $invoiceDetail->task->type }}"
                                            data-client-id="{{ $invoice->client ? $invoice->client->id : null }}"
                                            data-task-id="{{ $invoice->id }}" class="taskRow">
                                            <!-- <td>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </td> -->
                                            <td class="p-3 text-sm flex gap-2">
                                                {{-- <a href="javascript:void(0);"
                                                    class="viewInvoice text-blue-500 hover:underline"
                                                    onclick="openInvoiceModal('{{ $invoice->invoice_number }}')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                        height="20" viewBox="0 0 24 24">
                                                        <g fill="none" stroke="currentColor" stroke-width="1">
                                                            <path
                                                                d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z"
                                                                opacity=".5"></path>
                                                            <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path>
                                                        </g>
                                                    </svg>
                                                </a> --}}
                                                <a data-tooltip="View Invoice" target="_blank"
                                                    href="{{ url('/invoice/' . $invoice->invoice_number) }}"
                                                    class="viewInvoice text-blue-500 hover:underline">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                        height="20" viewBox="0 0 24 24">
                                                        <g fill="none" stroke="currentColor" stroke-width="1">
                                                            <path
                                                                d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z"
                                                                opacity=".5"></path>
                                                            <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path>
                                                        </g>
                                                    </svg>
                                                </a>
                                                @if ($invoice->status !== 'paid')
                                                    <a data-tooltip="View Detail/ Edit"
                                                        href="{{ route('invoice.edit', ['invoiceNumber' => $invoice->invoice_number]) }}"
                                                        class="text-sm font-medium text-blue-600 hover:underline">

                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                            height="20" viewBox="0 0 24 24">
                                                            <path fill="none" stroke="#00ab55"
                                                                stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="1.5"
                                                                d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                                opacity=".5" />
                                                        </svg>
                                                    </a>
                                                @endif
                                                <div id="viewInvoiceModal"
                                                    class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
                                                    <div class="relative">
                                                        <!-- Modal Content -->
                                                        <div class="w-full">

                                                        </div>
                                                        <div id="invoiceInvoiceContent" class="">
                                                            <!-- Invoice content will be loaded here dynamically -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ $invoice->invoice_number }}</td>

                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ $invoice->agent->name }}</td>
                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ $invoice->client->name }}</td>
                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ ucwords($invoice->payment_type) }}</td>
                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ $invoice->currency }} {{ $invoice->amount }}</td>
                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                @if ($invoice->status === 'paid')
                                                    <span
                                                        class="badge badge-outline-success">{{ $invoice->status }}</span>
                                                @else
                                                    <span
                                                        class="badge badge-outline-danger">{{ $invoice->status }}</span>
                                                @endif
                                            </td>



                                        </tr>
                                        {{-- @endforeach --}}
                                    @endforeach
                                @endif
                            </tbody>
                        </table>

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

        <div class="content-30 hidden" id="showRightDiv">
            <div id="taskDetails" class="panel w-full xl:mt-0 rounded-lg h-auto"></div>
            <div id="filterBox" class="panel w-full xl:mt-0 rounded-lg h-auto ">

                <div class="flex justify-between items-center gap-5 mb-5 FiltersHeader">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Filters</h1>
                    <div
                        class="filter-badge flex items-center gap-3 DarkBGcolor  
                           FilltersAppliedPy FilltersAppliedPx  dark:bg-gray-700 dark:hover:bg-gray-600 rounded-full px-4 py-2 shadow">
                        <span class="font-semibold">0 applied</span>
                        <button id="clearFilters" data-tooltip="Clear Filters" class="transition">
                            &times;
                        </button>
                    </div>
                </div>

                <div class="w-full mb-5">
                    <div class="flex justify-between">
                        <div class="flex flex-wrap gap-4">

                            <div id="selected-statuses" class="flex flex-wrap gap-2">
                                <!-- Selected statuses will appear here dynamically -->
                            </div>

                            <!-- Selected Types -->
                            <div id="selected-types" class="flex flex-wrap gap-2">
                                <!-- Selected types will appear here dynamically -->
                            </div>

                            <!-- Selected Suppliers -->
                            <div id="selected-suppliers" class="flex flex-wrap gap-2">
                                <!-- Selected suppliers will be displayed here dynamically -->
                            </div>

                            <!-- Selected Agents -->
                            <div id="selected-agents" class="flex flex-wrap gap-2">
                                <!-- Selected agents will be displayed here dynamically -->
                            </div>

                            <!-- Selected Branches -->
                            <div id="selected-branches" class="flex flex-wrap gap-2">
                                <!-- Selected branches will be displayed here dynamically -->
                            </div>

                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    <div class="w-full flex gap-5">
                        <div class="w-full gap-5 space-y-8">

                            <div
                                class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5 FilltersAppliedPx FilltersAppliedPy shadow-md hover:shadow-lg">
                                <div class="flex items-center">
                                    <input data-tooltip="filter by price" type="range" min="1"
                                        max="1000" value="500" id="priceRange"
                                        class="w-full h-2 bg-gray-200 dark:bg-gray-600 rounded-lg appearance-none">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <span id="ShowTaskFilters"
                                            class="font-medium text-gray-800 dark:text-gray-100">0</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Filter by Status -->
                            <div class="flex gap-4 items-center">
                                <!-- Left Icon -->
                                <div data-tooltip="Status"
                                    class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                    <!-- SVG Icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 12 12" class="icon">
                                        <path fill-rule="evenodd"
                                            d="M6 10a4 4 0 1 0 0-8a4 4 0 0 0 0 8m0 2A6 6 0 1 0 6 0a6 6 0 0 0 0 12"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>



                                <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                    <select name="status_id" id="status_id"
                                        class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                        <option selected value="" class="">Select Status</option>
                                        <option value="paid">Paid</option>
                                        <option value="unpaid">Unpaid</option>
                                    </select>

                                </div>

                            </div>

                            <!-- Filter by Type -->
                            <div class="flex gap-4 items-center">
                                <!-- Left Icon -->
                                <div data-tooltip="Type"
                                    class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                    <!-- SVG Icon -->
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20"
                                        height="20" viewBox="0 0 24 24">
                                        <path
                                            d="M7.5 20q-1.45 0-2.475-1.025T4 16.5t1.025-2.475T7.5 13h11q1.45 0 2.475 1.025T22 16.5t-1.025 2.475T18.5 20zm-2-9q-1.45 0-2.475-1.025T2 7.5t1.025-2.475T5.5 4h11q1.45 0 2.475 1.025T20 7.5t-1.025 2.475T16.5 11z">
                                        </path>
                                    </svg>
                                </div>

                                <!-- Select Dropdown -->
                                <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                    <select name="type_id" id="type_id"
                                        class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                        <option selected value="" class="">Select Type</option>
                                        @foreach ($types as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>


                            <!-- Filter by Supplier -->
                            <div class="flex gap-4 items-center">
                                <!-- Left Icon -->
                                <div data-tooltip="Supplier"
                                    class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                    <!-- SVG Icon -->
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20"
                                        height="20" viewBox="0 0 24 24">
                                        <path
                                            d="M16.923 15.02q-.154-.59-.6-1.1q-.446-.512-1.135-.766l-6.992-2.62q-.136-.05-.27-.061t-.307-.012H7v-2.34q0-.385.177-.742q.177-.358.5-.575l4.885-3.479q.224-.159.458-.229q.234-.069.478-.069t.49.07t.45.228l4.885 3.479q.323.217.5.575T20 8.12v6.898zM14.5 8.441q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m-2 0q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m2 2q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m-2 0q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m1.01 11.23q.198.055.481.048q.284-.006.48-.06L21 19.5q0-.696-.475-1.136q-.475-.441-1.179-.441h-5.158q-.498 0-1.02-.06q-.524-.061-.977-.22l-1.572-.526q-.161-.056-.236-.211t-.025-.315q.05-.139.202-.21q.152-.072.313-.016l1.433.502q.408.146.893.217q.486.07 1.053.07h1.202q.283 0 .453-.162t.17-.456q0-.388-.309-.809q-.308-.421-.716-.565l-6.021-2.21q-.137-.042-.273-.074q-.137-.032-.292-.032H6.385v6.737zM2.384 19.922q0 .46.308.768q.309.309.769.309h.846q.46 0 .768-.309q.309-.308.309-.768v-6q0-.46-.309-.768q-.309-.309-.768-.309h-.846q-.46 0-.769.309q-.308.309-.308.768z" />
                                    </svg>
                                </div>

                                <!-- Select Dropdown -->
                                <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                    <select name="supplier_id" id="supplier_id"
                                        class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                        <option selected value="" class="">Select Supplier</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Filter by Agent -->
                            <div class="flex gap-4 items-center">
                                <!-- Left Icon -->
                                <div data-tooltip="Agent"
                                    class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                    <!-- SVG Icon -->
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20"
                                        height="16" viewBox="0 0 640 512">
                                        <path
                                            d="M96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64s-64 28.7-64 64s28.7 64 64 64m448 0c35.3 0 64-28.7 64-64s-28.7-64-64-64s-64 28.7-64 64s28.7 64 64 64m32 32h-64c-17.6 0-33.5 7.1-45.1 18.6c40.3 22.1 68.9 62 75.1 109.4h66c17.7 0 32-14.3 32-32v-32c0-35.3-28.7-64-64-64m-256 0c61.9 0 112-50.1 112-112S381.9 32 320 32S208 82.1 208 144s50.1 112 112 112m76.8 32h-8.3c-20.8 10-43.9 16-68.5 16s-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48v-28.8c0-63.6-51.6-115.2-115.2-115.2m-223.7-13.4C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4" />
                                    </svg>
                                </div>

                                <!-- Select Dropdown -->
                                <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                    <select name="agent_id" id="agent_id"
                                        class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                        <option selected value="" class="">Select Agent</option>
                                        @foreach ($agents as $agent)
                                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Filter by Branch -->
                            <div class="flex gap-4 items-center">
                                <!-- Left Icon -->
                                <div data-tooltip="Branch"
                                    class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                    <!-- SVG Icon -->
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20"
                                        height="20" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd"
                                            d="M3.464 3.464C2 4.93 2 7.286 2 12s0 7.071 1.464 8.535C4.93 22 7.286 22 12 22s7.071 0 8.535-1.465C22 19.072 22 16.714 22 12s0-7.071-1.465-8.536C19.072 2 16.714 2 12 2S4.929 2 3.464 3.464M8.03 5.97a.75.75 0 0 1 0 1.06l-.22.22H8c1.68 0 3.155.872 4 2.187a4.75 4.75 0 0 1 4-2.187h.19l-.22-.22a.75.75 0 0 1 1.06-1.06l1.5 1.5a.75.75 0 0 1 0 1.06l-1.5 1.5a.75.75 0 1 1-1.06-1.06l.22-.22H16A3.25 3.25 0 0 0 12.75 12v6a.75.75 0 0 1-1.5 0v-6A3.25 3.25 0 0 0 8 8.75h-.19l.22.22a.75.75 0 1 1-1.06 1.06l-1.5-1.5a.75.75 0 0 1 0-1.06l1.5-1.5a.75.75 0 0 1 1.06 0"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>

                                <!-- Select Dropdown -->
                                <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                    <select name="branch_id" id="branch_id"
                                        class="selectize w-full appearance-none bg-transparent outline-none cursor-pointer focus:outline-none focus:ring-0">
                                        <option selected value="" class="">Select Branch</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- ./right -->
            </div>
        </div>
    </div>
    <!--./page content-->
    @include('invoice.tasksjs')
    <script>
        function openInvoiceModal(invoiceNumber) {
            const modal = document.getElementById("viewInvoiceModal");
            const contentDiv = document.getElementById("invoiceInvoiceContent");

            // Clear previous content
            contentDiv.innerHTML = "";

            // Open the modal
            modal.classList.remove("hidden");
            url =
                "{{ route('invoice.show', ['invoiceNumber' => ':invoiceNumber']) }}".replace(
                    ":invoiceNumber",
                    invoiceNumber
                );

            // Fetch the invoice details
            fetch(url)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.text();
                })
                .then((data) => {
                    contentDiv.innerHTML = data;

                    // Close the modal when the backdrop is clicked
                    modal.addEventListener("click", (event) => {
                        if (event.target === modal) {
                            closeInvoiceModal();
                        }
                    });


                })
                .catch((error) => {
                    console.error("Error fetching invoice details:", error);
                    contentDiv.innerHTML =
                        '<p class="text-center text-red-500">Failed to load invoice details.</p>';

                });
        }

        function closeInvoiceModal() {
            const modal = document.getElementById("viewInvoiceModal");
            modal.classList.add("hidden");
        }
    </script>
</x-app-layout>
