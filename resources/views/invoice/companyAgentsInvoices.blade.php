<x-app-layout>



    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Invoices List</h2>
            <!-- total Invoice number -->
            <div data-tooltip="number of invoices" class="relative w-12 h-12 flex items-center justify-center DarkBCcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalInvoices }}</span>
            </div>
        </div>
        <!-- add new Invoice & refresh page -->
        <div class="flex items-center gap-5">
            <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>


            <!-- add new invoice -->
            <a href="{{ route('invoice.create') }}">
                <div data-tooltip="Create new Invoice" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">


                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
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
                <!--  search icon -->
                <div class="relative">
                    <!-- Search Input -->
                    <input type="text" placeholder="Find fast and search here..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                    <!-- Search Button with SVG Icon -->
                    <button type="button" class="btn DarkBCcolor absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                        id="searchButton">
                        <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5" opacity="0.5"></circle>
                            <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                    </button>
                </div>

                <!-- ./search icon -->
                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="form-checkbox hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Edit</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Invoice Number</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Client name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Amount</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($invoices->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                                </tr>
                                @else
                                @foreach ($invoices as $invoice)
                                <tr>
                                    <td>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <a href="javascript:void(0);" class="viewInvoice text-blue-500 hover:underline" onclick="openInvoiceModal('{{ $invoice->invoice_number }}')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                <g fill="none" stroke="#333333" stroke-width="1.5">
                                                    <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5" />
                                                    <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                                </g>
                                            </svg>
                                        </a>
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
                                    <td>
                                        <a href="{{ route('invoice.edit', ['invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="text-sm font-medium text-blue-600 hover:underline">
                                            Edit
                                        </a>

                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $invoice->invoice_number }}</td>

                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $invoice->agent->name }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $invoice->client->name }}</td>

                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $invoice->amount }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        @if ($invoice->status === 'paid')
                                        <span class="badge badge-outline-success">{{ $invoice->status }}</span>
                                        @else
                                        <span class="badge badge-outline-danger">{{ $invoice->status }}</span>
                                        @endif
                                    </td>



                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>

                    </div>
                    <!-- ./table -->


                    <!-- pagination -->
                    @if ($invoices->count() > 15)
                    <div class="dataTable-bottom justify-center">
                        <nav class="dataTable-pagination">
                            <ul class="dataTable-pagination-list flex gap-2 mt-4">
                                <li class="pager" id="prevPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                                <!-- Dynamic page numbers will be injected here -->
                                <li class="pager" id="nextPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    @endif
                    <!-- ./pagination -->
                </div>
            </div>

            <!-- ./Table  -->

        </div>
        <!-- right -->

        <!-- ./right -->
    </div>
    <!--./page content-->

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