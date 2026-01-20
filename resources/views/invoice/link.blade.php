<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Invoices Link</h2>
            <div data-tooltip="Number of invoices"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $invoices->total() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            <a href="{{ route('invoices.create') }}">
                <div data-tooltip-left="Create new invoice"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <x-admin-card title="invoice links" :companyId="request('company_id')" />

    <div class="panel rounded-lg">
        <x-search :action="route('invoices.link')" searchParam='search' placeholder='Quick search for invoices link' />

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Invoice Number</th>
                            <th>Invoice Link</th>
                            <th>Payment Type</th>
                            <th>Client</th>
                            <th>Action</th>
                            <th>Amount</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($invoices->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                        </tr>
                        @else
                        @foreach ($invoices as $invoice)
                        @php
                            $invoiceDetail = $invoice->invoiceDetails->first();
                        @endphp
                        <tr data-price="{{ $invoice->total }}"
                            data-supplier-id="{{ $invoiceDetail && $invoiceDetail->task && $invoiceDetail->task->supplier ? $invoiceDetail->task->supplier->id : '' }}"
                            data-branch-id="{{ $invoice->agent->branch->id }}"
                            data-agent-id="{{ $invoice->agent_id }}"
                            data-status="{{ $invoice->status }}"
                            data-type="{{ $invoiceDetail && $invoiceDetail->task ? $invoiceDetail->task->type : '' }}"
                            data-client-id="{{ $invoice->client ? $invoice->client->id : null }}"
                            data-task-id="{{ $invoice->id }}" class="taskRow">
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-300">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                @if ($invoice->status === 'paid by refund')
                                    <span class="text-gray-500 italic dark:text-gray-400">Settled by refund</span>
                                @elseif ($invoice->payment_type)
                                    <a href="{{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])}}" class="text-blue-500 hover:underline" target="_blank">
                                        {{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])}}
                                    </a>
                                @else
                                    <span class="text-gray-500 italic dark:text-gray-400">Invoice link available after setting payment type</span>
                                @endif
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                {{ $invoice->payment_type ? ucwords($invoice->payment_type) : 'N/A' }}
                            </td>
                            <td x-data="{ editClientPhone: false}">
                                <p class="cursor-pointer text-blue-500 dark:text-blue-400 hover:underline"
                                    @click="editClientPhone = !editClientPhone" data-tooltip-left="Edit client number">
                                    {{ $invoice->client->full_name }}
                                </p>
                                <div x-cloak x-show="editClientPhone" class="fixed bg-gray-800 inset-0 bg-opacity-75 flex items-center justify-center z-50">
                                    <div @click.away="editClientPhone = false"
                                        class="p-4 bg-white w-full max-w-md rounded relative dark:bg-gray-900">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Update Phone Number</h2>
                                                <p class="text-gray-600 dark:text-gray-400 italic text-xs mt-1">
                                                    Please update the client's phone number to ensure accurate communication</p>
                                            </div>
                                            <button @click="editClientPhone = false" class="absolute top-0 right-0 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                &times;
                                            </button>
                                        </div>
                                        <form method="POST" action="{{ route('clients.update', $invoice->client->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="first_name" id="client" value="{{ $invoice->client->first_name }}">
                                            <div class="mb-4 flex flex-col">
                                                <label class="block text-gray-700 mb-2" for="phone_{{ $invoice->client->id }}">Phone Number</label>
                                                <div class="flex gap-4 mb-4">
                                                    <div class="w-2/5">
                                                        <x-searchable-dropdown
                                                            name="country_code"
                                                            :items="\App\Models\Country::all()->map(fn($country) => [
                                                                    'id' => $country->dialing_code,
                                                                    'name' => $country->dialing_code . ' ' . $country->name
                                                                ])"
                                                            :selectedName="optional($invoice->client)->country_code"
                                                            placeholder="Dial Code"
                                                            :showAllOnOpen="true" />
                                                    </div>
                                                    <div class="w-3/5">
                                                        <input
                                                            type="text"
                                                            name="phone"
                                                            id="phone_{{ $invoice->client->id }}"
                                                            value="{{ $invoice->client->phone }}"
                                                            class="w-full border border-gray-300 rounded px-3"
                                                            required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex justify-between mt-3 gap-2">
                                                <button type="button" @click="editClientPhone = false" class="rounded-full shadow-md border border-gray-200 hover:bg-gray-300 px-4 py-2">Cancel</button>
                                                <button type="submit" class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($invoice->status === 'paid by refund')
                                    <span class="relative inline-flex cursor-default" data-tooltip="Settled by refund">
                                        <span class="badge badge-outline-info">Paid by Refund</span>
                                    </span>
                                @elseif ($invoice->payment_type)
                                    <form action="{{ route('resayil.share-invoice-link') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="client_id" id="client"
                                            value="{{ $invoice->client->id }}">
                                        <input type="hidden" name="invoiceNumber"
                                            value="{{ $invoice->invoice_number }}">
                                        <button type="submit" class="badge badge-outline-success">
                                            Share via WhatsApp
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('invoice.edit', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                    target="_blank">
                                        <button type="button" class="badge badge-outline-warning">
                                            Set payment type first
                                        </button>
                                    </a>
                                @endif
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                {{ $invoice->currency }}
                                {{ $invoice->amount }}
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                {{ $invoice->due_date }}
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                @if ($invoice->status === 'paid')
                                <span
                                    class="badge badge-outline-success">{{ $invoice->status }}</span>
                                @elseif ($invoice->status === 'paid by refund')
                                <span
                                    class="badge badge-outline-info">{{ $invoice->status }}</span>
                                @else
                                <span
                                    class="badge badge-outline-danger">{{ $invoice->status }}</span>
                                @endif
                            </td>
                        </tr>

                        @foreach ($invoice->invoicePartials as $partial)
                        @if ($partial->type === 'split')
                        <tr data-price="{{ $partial->total }}"
                            data-supplier-id="{{ $invoiceDetail->task->supplier->id }}"
                            data-branch-id="{{ $invoice->agent->branch->id }}"
                            data-agent-id="{{ $invoice->agent_id }}"
                            data-status="{{ $partial->status }}"
                            data-type="{{ $partial->type }}"
                            data-client-id="{{ $invoice->client ? $invoice->client->id : null }}"
                            data-task-id="{{ $invoice->id }}" class="taskRow">
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-300">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                <a href="{{ route('invoice.split', ['invoiceNumber' => $invoice->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id])}}" class="text-green-500 hover:underline" target="_blank">
                                    {{ route('invoice.split', ['invoiceNumber' => $invoice->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id])}}
                                </a>
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                {{ ucwords($partial->type) }}
                            </td>
                            <td x-data="{ editClientPhone: false }">
                                <p
                                    class="cursor-pointer text-blue-500 hover:underline dark:text-blue-400"
                                    @click="editClientPhone = !editClientPhone" data-tooltip-left="Edit Client Phone">
                                    {{ $partial->client->full_name }}
                                </p>
                                <div x-cloak x-show="editClientPhone" class="fixed bg-gray-800 inset-0 bg-opacity-75 flex items-center justify-center z-50">
                                    <div @click.away="editClientPhone = false" class="p-4 bg-white w-full max-w-md rounded relative">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-bold text-gray-800">Update Phone Number</h2>
                                                <p class="text-gray-600 italic text-xs mt-1">Please update the client's phone number to ensure accurate communication</p>
                                            </div>
                                            <button @click="editClientPhone = false" class="absolute top-0 right-0 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                &times;
                                            </button>
                                        </div>
                                        <form method="POST" action="{{ route('clients.update', $partial->client->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="first_name" id="client" value="{{ $partial->client->first_name }}">
                                            <div class="mb-4 flex flex-col">
                                                <label class="block text-gray-700 mb-2" for="phone_{{ $partial->client->id }}">Phone Number</label>
                                                <div class="flex gap-4 mb-4">
                                                    <div class="w-2/5">
                                                        <x-searchable-dropdown
                                                            name="country_code"
                                                            :items="\App\Models\Country::all()->map(fn($country) => [
                                                                    'id' => $country->dialing_code,
                                                                    'name' => $country->dialing_code . ' ' . $country->name
                                                                ])"
                                                            :selectedName="optional($partial->client)->country_code"
                                                            placeholder="Dial Code"
                                                            :showAllOnOpen="true" />
                                                    </div>
                                                    <div class="w-3/5">
                                                        <input type="text" name="phone"
                                                            id="phone_{{ $partial->client->id }}" value="{{ $partial->client->phone }}"
                                                            class="w-full border border-gray-300 rounded px-3" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex justify-between mt-3 gap-2">
                                                <button type="button" @click="editClientPhone = false" class="rounded-full shadow-md border border-gray-200 hover:bg-gray-300 px-4 py-2">Cancel</button>
                                                <button type="submit" class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <form action="{{ route('resayil.share-invoice-link') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="client_id" id="client" value="{{ $partial->client_id }}">
                                    <input type="hidden" name="invoiceNumber" value="{{ $partial->invoice->invoice_number }}">
                                    <button type="submit" class="badge badge-outline-success">
                                        Share via WhatsApp
                                    </button>
                                </form>
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                {{ $invoice->currency }} {{ $partial->amount }}
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                {{ $partial->expiry_date }}
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                @if ($partial->status === 'paid')
                                <span
                                    class="badge badge-outline-success">{{ $partial->status }}</span>
                                @else
                                <span
                                    class="badge badge-outline-danger">{{ $partial->status }}</span>
                                @endif
                            </td>
                        </tr>
                        @endif
                        @endforeach
                        @endforeach
                        @endif
                    </tbody>
                </table>

            </div>
            <x-pagination :data="$invoices" />
        </div>
    </div>

    @include('invoice.tasksjs')
    <script>
        function openInvoiceModal(invoiceNumber) {
            const modal = document.getElementById("viewInvoiceModal");
            const contentDiv = document.getElementById("invoiceInvoiceContent");
            const companyId = "{{ $companyId ?? '' }}";

            // Clear previous content
            contentDiv.innerHTML = "";

            // Open the modal
            modal.classList.remove("hidden");
            url = "{{ route('invoice.show', ['companyId' => ':companyId', 'invoiceNumber' => ':invoiceNumber']) }}".replace(':companyId', companyId).replace(':invoiceNumber', invoiceNumber);

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