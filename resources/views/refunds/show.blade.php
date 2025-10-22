<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Refund Details") }} - {{ $refund->refund_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Refund Information</h3>
                            <p class="mt-1 text-sm text-gray-600"><strong>Refund Number:</strong> {{ $refund->refund_number }}</p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Refund Date:</strong> {{ $refund->refund_date->format('Y-m-d') }}</p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Status:</strong> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ ucfirst($refund->status) }}</span></p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Method:</strong> {{ $refund->method }}</p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Reference:</strong> {{ $refund->reference ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Financial Summary</h3>
                            <p class="mt-1 text-sm text-gray-600"><strong>Total Refund Amount:</strong> {{ number_format($refund->total_refund_amount, 2) }}</p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Total Refund Charges:</strong> {{ number_format($refund->total_refund_charge, 2) }}</p>
                            <p class="mt-1 text-sm text-gray-600 font-bold"><strong>Net Refund:</strong> {{ number_format($refund->total_net_refund, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Remarks</h3>
                            <p class="mt-1 text-sm text-gray-600"><strong>Reason:</strong> {{ $refund->reason ?? 'N/A' }}</p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Remarks:</strong> {{ $refund->remarks ?? 'N/A' }}</p>
                            <p class="mt-1 text-sm text-gray-600"><strong>Internal Remarks:</strong> {{ $refund->remarks_internal ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Refunded Tasks</h3>

                    @foreach($refund->refundDetails as $detail)
                        <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
                            <h4 class="text-md font-semibold text-gray-800 mb-3">Task ID: {{ $detail->task_id }} - {{ $detail->task_description }} (From Invoice #{{ $detail->invoice->invoice_number }})</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Refunded To:</strong> {{ $detail->refundClient->first_name }} {{ $detail->refundClient->last_name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Original Selling Price:</strong> {{ number_format($detail->original_invoice_price, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Original Cost Price:</strong> {{ number_format($detail->original_task_cost, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Original Profit:</strong> {{ number_format($detail->original_task_profit, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Refund Fee to Client:</strong> {{ number_format($detail->refund_fee_to_client, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Supplier Charges:</strong> {{ number_format($detail->refund_task_supplier_charge, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>New Profit:</strong> {{ number_format($detail->new_task_profit, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 font-bold"><strong>Total Refund to Client:</strong> {{ number_format($detail->total_refund_to_client, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 font-bold"><strong>Net Refund for Task:</strong> {{ number_format($detail->net_refund, 2) }}</p>
                                </div>
                                @if($detail->refund_charges_invoice_id)
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Refund Charges Invoice:</strong> <a href="{{ route('invoices.show', $detail->refund_charges_invoice_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $detail->refundChargesInvoice->invoice_number }}</a></p>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-6">
                        <a href="{{ route('refunds.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Back to Refunds List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

