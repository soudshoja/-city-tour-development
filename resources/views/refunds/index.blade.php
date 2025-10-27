<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Refunds</h2>
            <div data-tooltip="number of refunds"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalRefunds }}</span>
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
        </div>
    </div>

    <div class="tableCon">
        <div class="content-70">
            <div class="panel oxShadow rounded-lg">
                <div class="relative">
                    <input type="text" placeholder="Find fast and search here..."
                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                        id="searchInput">
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

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Refund Number</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Client</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Total Refund</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Description</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Registered Date</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Status</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($refunds->isEmpty())
                                <tr>
                                    <td colspan="8" class="text-center p-3 text-sm font-semibold text-gray-500 ">
                                        No data for now.... Create new!</td>
                                </tr>
                                @else
                                @foreach ($refunds as $refund)
                                <tr>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $refund->refund_number }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500 max-w-[250px] whitespace-normal break-words">
                                        @php
                                        $uniqueClients = $refund->refundDetails->pluck('client.full_name')->unique()->values()->toArray();
                                        @endphp
                                        {{ implode(', ', $uniqueClients) }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ number_format($refund->total_nett_refund, 2) }} KWD
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $refund->remarks }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $refund->created_at }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <span
                                            class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                                        {{ $refund->status === 'completed' ? 'badge-outline-success' : '' }}
                                                        {{ $refund->status === 'processed' ? 'badge-outline-assigned' : '' }}
                                                        {{ $refund->status === 'approved' ? 'badge-outline-success' : '' }}
                                                        {{ $refund->status === 'declined' ? 'badge-outline-danger' : '' }}
                                                        {{ $refund->status === 'pending' ? 'badge-outline-warning' : '' }}
                                                        {{ $refund->status === null ? 'badge-outline-danger' : '' }}">
                                            {{ $refund->status === null ? 'Not Set' : ucwords($refund->status) }}

                                        </span>
                                        @if ($refund->status !== 'completed' && $refund->invoice == null)
                                        <span
                                            class="cursor-pointer ml-2 badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-primary"
                                            onclick="confirmProcessCompleted({{ $refund->id }})">
                                            Mark as Completed
                                        </span>
                                        @elseif($refund->invoice)
                                        <span
                                            class="cursor-pointer ml-2 badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-primary">
                                            <a href="{{ route('invoice.show', ['companyId' => $refund->company_id, 'invoiceNumber' => $refund->invoice->invoice_number])}}">View Invoice</a>
                                        </span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-sm">
                                        <div class="flex items-center space-x-2">
                                            <a data-tooltip-left="View Refund"
                                                href="{{ route('refunds.show', [$refund->company_id, $refund->refund_number]) }}"
                                                target="_blank"
                                                class="text-sm font-medium text-blue-600 hover:underline">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="#2563eb"
                                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                            </a>
                                            <a data-tooltip-left="Edit Refund"
                                                href="{{ route('refunds.edit', [$refund->id]) }}"
                                                class="text-sm font-medium text-blue-600 hover:underline">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                    height="20" viewBox="0 0 24 24">
                                                    <path fill="none" stroke="#00ab55" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="1.5"
                                                        d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                        opacity=".5" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>

                    </div>

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
                </div>
            </div>
        </div>
    </div>

    @include('refunds.refund-client')
    <script>
        function confirmProcessCompleted(refundId) {
            if (confirm('Are you sure you want to mark this refund as completed?')) {
                if (confirm('This action cannot be undone. Do you want to proceed?')) {
                    processCompleted(refundId);
                }
            }
        }

        function processCompleted(taskId, refundId) {
            fetch(`/refunds/${refundId}/complete-process`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.ok ? window.location.href = '/refunds' : alert('Something went wrong.'))
                .catch(() => alert('Error processing refund.'));
            console.log(taskId, refundId);
        }
    </script>
</x-app-layout>