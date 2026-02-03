<x-app-layout>
    <div class="container mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-700">Edit Refund #{{ $refund->refund_number }}</h1>
            <a href="{{ route('refunds.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-400 transition duration-200">
                ← Back
            </a>
        </div>

        @php
            $isReadOnly = strtolower($refund->status) === 'completed';
            $isEditing = true;
        @endphp

        <form action="{{ route('refunds.update', $refund->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mt-8 p-6 border rounded-lg bg-white">
                <div class="mb-6 rounded-lg p-4 {{ $isPaidInvoice ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                    <div class="flex items-center gap-4 flex-wrap text-sm font-semibold">
                        <div class="{{ $isPaidInvoice ? 'text-green-700' : 'text-red-800' }}">
                            Original Invoice: #{{ $firstInvoice?->invoice_number ?? 'N/A' }}
                        </div>
                        <span class="text-gray-400">|</span>
                        <div class="{{ $isPaidInvoice ? 'text-green-700' : 'text-red-800' }}">
                            Status: {{ ucfirst($firstInvoice?->status ?? 'N/A') }}
                        </div>
                    </div>
                    @if($isReadOnly)
                        <div class="mt-2 text-sm text-gray-600 italic">
                            This refund is locked because the refund has been marked as <strong>Completed</strong>.
                            You can no longer edit it.
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Client Info</h3>
                        <p><strong>Name:</strong> {{ $firstTask->client->full_name ?? 'N/A' }}</p>
                        <p><strong>Email:</strong> {{ $firstTask->client->email ?? 'N/A' }}</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg border">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Agent Info</h3>
                        <p><strong>Name:</strong> {{ $firstTask->agent->name ?? 'N/A' }}</p>
                        <p><strong>Email:</strong> {{ $firstTask->agent->email ?? 'N/A' }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Refund Date</label>
                    <input type="date" name="date" id="date"
                           value="{{ $refund->refund_date?->toDateString() ?? now()->toDateString() }}"
                           {{ $isReadOnly ? 'readonly disabled' : '' }}
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>

                @if ($isPaidInvoice)
                    <div class="mt-6 p-6 border rounded-lg bg-gray-50">
                        <h3 class="text-xl font-bold mb-4">Refund Method</h3>
                        <select name="method" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                            <option value="">Select</option>
                            <option value="Cash" {{ $refund->method == 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Bank" {{ $refund->method == 'Bank' ? 'selected' : '' }}>Bank</option>
                            <option value="Online" {{ $refund->method == 'Online' ? 'selected' : '' }}>Online</option>
                            <option value="Credit" {{ $refund->method == 'Credit' ? 'selected' : '' }}>{{ $firstTask->client->full_name }}'s Credit</option>
                        </select>
                    </div>
                @else
                    <div class="mt-6">
                        @include('refunds.partial.payment-gateway-selection')
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Remarks</label>
                        <input type="text" name="remarks" value="{{ old('remarks', $refund->remarks) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Internal Remarks</label>
                        <input type="text" name="remarks_internal" value="{{ old('remarks_internal', $refund->remarks_internal) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-gray-700 font-semibold mb-2">Reason</label>
                    <textarea name="reason" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg">{{ old('reason', $refund->reason) }}</textarea>
                </div>

                @unless($isReadOnly)
                    <button type="submit"
                            class="mt-6 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
                        Update Refund
                    </button>
                @endunless
            </div>

            @foreach($refund->refundDetails as $detail)
                @php
                    $task = $detail->task;
                    $sourceTask = $detail->computed_source_task;
                    $invoiceDetail = $detail->computed_invoice_detail;
                    $invoiceStatus = $detail->computed_invoice_status;
                @endphp

                <div class="task-refund-section bg-gray-50 border p-6 mt-8 rounded-lg shadow-sm">
                    <h3 class="text-xl font-bold mb-4">Refund Task #{{ $task->reference }}</h3>
                    <input type="hidden" name="tasks[{{ $loop->index }}][task_id]" value="{{ $task->id }}">

                    @if(in_array($invoiceStatus, ['paid', 'refunded', 'partial refund']))
                        @include('refunds.partial.paid-invoice-section', [
                            'task' => $task,
                            'sourceTask' => $sourceTask,
                            'invoiceDetail' => $invoiceDetail,
                            'refundDetail' => $detail,
                            'loopIndex' => $loop->index,
                            'isEditing' => $isEditing,
                            'isReadOnly' => $isReadOnly,
                        ])
                    @else
                        @include('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'sourceTask' => $sourceTask,
                            'invoiceDetail' => $invoiceDetail,
                            'refundDetail' => $detail,
                            'loopIndex' => $loop->index,
                            'isEditing' => $isEditing,
                            'isReadOnly' => $isReadOnly,
                        ])
                    @endif
                </div>
            @endforeach
        </form>
    </div>
</x-app-layout>
