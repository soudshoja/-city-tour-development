<div class="flex justify-between items-center gap-5 pt-10 my-3 ">
    <div class="flex items-center gap-5 ">
        <h2 class="text-3xl font-bold">Refund From Client Credit</h2>
        <div data-tooltip="number of refunds"
            class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
            <span class="text-xl font-bold text-white">{{ $totalRefundClients }}</span>
        </div>
    </div>
</div>

<div class="tableCon">
    <div class="content-70">
        <div class="panel oxShadow rounded-lg">

            <div class="">
                <div class=""></div>
                <div class="">
                    <table id="" class=" whitespace-nowrap dataTable-table">
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
                            @if ($refundClients->isEmpty())
                            <tr>
                                <td colspan="8" class="text-center p-3 text-sm font-semibold text-gray-500 ">
                                    No data for now.... Create new!</td>
                            </tr>
                            @else
                            @foreach ($refundClients as $refund)
                            <tr>
                                <td class="p-3 text-sm font-semibold text-gray-500">
                                    {{ $refund->amount }}
                                </td>
                                <td class="p-3 text-sm font-semibold text-gray-500">
                                    {{ $refund->client->name ?? '' }}
                                </td>
                                <td class="p-3 text-sm font-semibold text-gray-500">
                                    KWD {{ number_format($refund->total_nett_refund, 2) }}
                                </td>
                                <td class="p-3 text-sm font-semibold text-gray-500">
                                    {{ $refund->remarks ?? 'No Remarks' }}
                                </td>
                                <td class="p-3 text-sm font-semibold text-gray-500">
                                    {{ $refund->created_at }}
                                </td>
                                <td class="p-3 text-sm font-semibold text-gray-500">
                                    <span
                                        class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                                        {{ $refund->status === 'completed' ? 'badge-outline-success' : '' }}
                                                        {{ $refund->status === 'pending' ? 'badge-outline-warning' : '' }}
                                                        {{ $refund->status === 'failed' ? 'badge-outline-danger' : '' }}">
                                        {{ $refund->status === null ? 'Not Set' : ucwords($refund->status) }}

                                    </span>

                                    @if ($refund->status !== 'completed')
                                    @can('complete', [App\Models\RefundClient::class, $refund])
                                    <a href="{{ route('refunds.refund-client.complete', $refund->id) }}"
                                        class="cursor-pointer ml-2 badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-primary">
                                        Mark as Completed
                                    </a>
                                    @endcan
                                    @endif
                                </td>
                                <td class="p-3 text-sm">
                                    @can('delete', [App\Models\RefundClient::class, $refund])
                                    <div x-data="{ deleteRefundClient: false }" >
                                        <button 
                                            @click="deleteRefundClient = true"
                                            class="flex items-center space-x-2 group cursor-pointer">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class=" group-hover:stroke-red-500" stroke="#000000">
                                                <path d="M9.1709 4C9.58273 2.83481 10.694 2 12.0002 2C13.3064 2 14.4177 2.83481 14.8295 4" stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M20.5001 6H3.5" stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M18.8332 8.5L18.3732 15.3991C18.1962 18.054 18.1077 19.3815 17.2427 20.1907C16.3777 21 15.0473 21 12.3865 21H11.6132C8.95235 21 7.62195 21 6.75694 20.1907C5.89194 19.3815 5.80344 18.054 5.62644 15.3991L5.1665 8.5" stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M9.5 11L10 16"  stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M14.5 11L14 16"  stroke-width="1.5" stroke-linecap="round" />
                                            </svg>
                                        </button>
                                        <div x-cloak x-show="deleteRefundClient" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50">
                                            <div class="bg-white rounded-lg p-6 shadow-lg">
                                                <h2 class="text-lg font-bold">Are you sure you want to delete this refund?</h2>
                                                <p class="mt-2">This action cannot be undone.</p>
                                                <div class="mt-4 flex justify-end space-x-2">
                                                    <button @click="deleteRefundClient = false" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
                                                    <form action="{{ route('refunds.refund-client.delete', $refund->id) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endcan
                                </a>
                                </td>

                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>