<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Payment Voucher</h2>
            <div data-tooltip="Number of payment voucher"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalRecords }}</span>
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

            <a href="{{ route('bank-payments.create') }}">
                <div data-tooltip-left="Create new payment voucher"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <x-admin-card title="payment voucher" :companyId="request('company_id')" />

    <div class="panel rounded-lg">
        <x-search
            :action="route('bank-payments.index')"
            searchParam="q"
            placeholder="Quick search for payment voucher" />

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Payment Ref</th>
                            <th>Type</th>
                            <th>Pay To</th>
                            <th>Doc Date</th>
                            <th>Description</th>
                            <th>Registered</th>
                            <th>Amount (KWD)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($bankPayments->isEmpty())
                            <tr>
                                <td colspan="8" class="text-center p-3 text-sm font-semibold text-gray-500">
                                    No data for now.... Create new!</td>
                            </tr>
                        @else
                            @foreach ($bankPayments as $bankpayment)
                                <tr>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $bankpayment->reference_number }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $bankpayment->reference_type }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $bankpayment->journalEntries->first()?->name ?? $bankpayment->name }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ \Carbon\Carbon::parse($bankpayment->transaction_date)->format('Y-m-d') }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $bankpayment->description }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ $bankpayment->created_at }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        KWD {{ number_format($bankpayment->amount, 3) }}
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <a data-tooltip-left="View payment voucher" href="{{ route('bank-payments.edit', $bankpayment->id) }}" class="text-blue-500 hover:underline">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                <g fill="none" stroke="currentColor" stroke-width="1">
                                                    <path
                                                        d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5"></path>
                                                    <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path>
                                                </g>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <x-pagination :data="$bankPayments" />
    </div>

</x-app-layout>
