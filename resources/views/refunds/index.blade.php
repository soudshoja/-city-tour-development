<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Refunds</h2>
            <div data-tooltip="Number of refunds"
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

    <div class="panel rounded-lg">
        <x-search
            :action="route('refunds.index')"
            searchParam="q"
            placeholder="Quick search for refunds" />

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Refund Number</th>
                            <th>Client</th>
                            <th>Total Refund</th>
                            <th>Description</th>
                            <th>Registered Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($refunds->isEmpty())
                            <tr>
                                <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-600">No data for now.... Create new!</td>
                            </tr>
                        @else
                            @foreach ($refunds as $refund)
                                <tr class="p-3 text-sm font-semibold text-gray-600">
                                    <td>{{ $refund->refund_number }}</td>
                                    <td class="max-w-[250px] whitespace-normal break-words">
                                        @php
                                            $uniqueClients = $refund->refundDetails->pluck('client.full_name')->unique()->values()->toArray();
                                        @endphp
                                        {{ implode(', ', $uniqueClients) }}
                                    </td>
                                    <td>{{ number_format($refund->total_nett_refund, 3) }} KWD</td>
                                    <td>{{ $refund->remarks }}</td>
                                    <td>{{ $refund->created_at }}</td>
                                    <td>
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
                                        @if (!$refund->invoice && $refund->status !== 'completed')
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
                                    <td>
                                        <div class="flex items-center space-x-2">
                                            <a data-tooltip-left="View refund" href="{{ route('refunds.show', [$refund->company_id, $refund->refund_number]) }}"
                                                target="_blank" class="text-sm font-medium text-blue-600 hover:underline">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                            </a>
                                            <a data-tooltip-left="Edit refund" href="{{ route('refunds.edit', [$refund->id]) }}"
                                                class="text-sm font-medium text-blue-600 hover:underline">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                    height="20" viewBox="0 0 24 24">
                                                    <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
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

            <x-pagination :data="$refunds" />
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

        function processCompleted(refundId) {
            // Optional: show console log for debugging
            console.log("Processing refund with ID:", refundId);

            fetch(`/refunds/${refundId}/complete-process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin' // ✅ ensures cookies/session are sent
            })
            .then(async response => {
                if (response.ok) {
                    // ✅ refund processed successfully
                    alert('Refund process completed successfully!');
                    window.location.href = '/refunds';
                } else {
                    // ❌ handle errors gracefully
                    const text = await response.text();
                    console.error('Server response:', text);
                    alert('Something went wrong. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Error processing refund:', error);
                alert('Error processing refund. Please try again.');
            });
        }
    </script>

</x-app-layout>