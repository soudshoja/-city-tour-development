<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Create Refund") }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('refunds.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label for="refund_date" class="block text-sm font-medium text-gray-700">Refund Date</label>
                                <input type="date" name="refund_date" id="refund_date" value="{{ old('refund_date', now()->toDateString()) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                @error('refund_date')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="refund_method" class="block text-sm font-medium text-gray-700">Refund Method</label>
                                <select name="refund_method" id="refund_method" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                                    <option value="">Select Method</option>
                                    <option value="Bank" {{ old('refund_method') == 'Bank' ? 'selected' : ''}}>Bank</option>
                                    <option value="Cash" {{ old('refund_method') == 'Cash' ? 'selected' : ''}}>Cash</option>
                                    <option value="Online" {{ old('refund_method') == 'Online' ? 'selected' : ''}}>Online</option>
                                    <option value="Credit" {{ old('refund_method') == 'Credit' ? 'selected' : ''}}>Credit</option>
                                </select>
                                @error('refund_method')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="reason" class="block text-sm font-medium text-gray-700">Reason for Refund</label>
                            <textarea name="reason" id="reason" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="overall_remarks" class="block text-sm font-medium text-gray-700">Overall Remarks</label>
                            <textarea name="overall_remarks" id="overall_remarks" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">{{ old('overall_remarks') }}</textarea>
                            @error('overall_remarks')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="overall_remarks_internal" class="block text-sm font-medium text-gray-700">Internal Remarks</label>
                            <textarea name="overall_remarks_internal" id="overall_remarks_internal" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">{{ old('overall_remarks_internal') }}</textarea>
                            @error('overall_remarks_internal')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Tasks for Refund</h3>

                        <div id="tasks-container">
                            @foreach($tasksData as $index => $task)
                                <div class="task-refund-section border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50" data-task-index="{{ $index }}">
                                    <h4 class="text-md font-semibold text-gray-800 mb-3">Task Reference: {{ $task['reference'] }} (Invoice #{{ $task['invoice_number'] }})</h4>
                                    <input type="hidden" name="tasks_for_refund[{{ $index }}][task_id]" value="{{ $task['id'] }}">
                                    <input type="hidden" name="tasks_for_refund[{{ $index }}][invoice_id]" value="{{ $task['invoice_id'] }}">
                                    <input type="hidden" name="tasks_for_refund[{{ $index }}][original_selling_price]" value="{{ $task['original_selling_price'] }}">
                                    <input type="hidden" name="tasks_for_refund[{{ $index }}][original_cost_price]" value="{{ $task['original_cost_price'] }}">
                                    <input type="hidden" name="tasks_for_refund[{{ $index }}][original_profit]" value="{{ $task['original_profit'] }}">

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <label for="refund_client_id_{{ $index }}" class="block text-sm font-medium text-gray-700">Refund Client</label>
                                            <select name="tasks_for_refund[{{ $index }}][refund_client_id]" id="refund_client_id_{{ $index }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                                                @foreach($uniqueClients as $client)
                                                    <option value="{{ $client->id }}" {{ old('tasks_for_refund.' . $index . '.refund_client_id', $task['client_id']) == $client->id ? 'selected' : ''}}>{{ $client->first_name }} {{ $client->last_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('tasks_for_refund.' . $index . '.refund_client_id')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="refund_fee_to_client_{{ $index }}" class="block text-sm font-medium text-gray-700">Refund Fee to Client</label>
                                            <input type="number" step="0.01" name="tasks_for_refund[{{ $index }}][refund_fee_to_client]" id="refund_fee_to_client_{{ $index }}" value="{{ old('tasks_for_refund.' . $index . '.refund_fee_to_client', $task['calculated_refund_fee_to_client']) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                            @error('tasks_for_refund.' . $index . '.refund_fee_to_client')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="refund_task_supplier_charge_{{ $index }}" class="block text-sm font-medium text-gray-700">Refund Task Supplier Charges</label>
                                            <input type="number" step="0.01" name="tasks_for_refund[{{ $index }}][refund_task_supplier_charge]" id="refund_task_supplier_charge_{{ $index }}" value="{{ old('tasks_for_refund.' . $index . '.refund_task_supplier_charge', $task['calculated_supplier_charges']) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                            @error('tasks_for_refund.' . $index . '.refund_task_supplier_charge')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="new_task_profit_{{ $index }}" class="block text-sm font-medium text-gray-700">New Task Profit</label>
                                            <input type="number" step="0.01" name="tasks_for_refund[{{ $index }}][new_task_profit]" id="new_task_profit_{{ $index }}" value="{{ old('tasks_for_refund.' . $index . '.new_task_profit', 0) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                            @error('tasks_for_refund.' . $index . '.new_task_profit')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="total_refund_to_client_{{ $index }}" class="block text-sm font-medium text-gray-700">Total Refund to Client</label>
                                            <input type="number" step="0.01" name="tasks_for_refund[{{ $index }}][total_refund_to_client]" id="total_refund_to_client_{{ $index }}" value="{{ old('tasks_for_refund.' . $index . '.total_refund_to_client', $task['task_total']) }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                            @error('tasks_for_refund.' . $index . '.total_refund_to_client')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="remarks_{{ $index }}" class="block text-sm font-medium text-gray-700">Task Remarks</label>
                                            <input type="text" name="tasks_for_refund[{{ $index }}][remarks]" id="remarks_{{ $index }}" value="{{ old('tasks_for_refund.' . $index . '.remarks') }}" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                            @error('tasks_for_refund.' . $index . '.remarks')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Process Refund
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tasksContainer = document.getElementById('tasks-container');

            tasksContainer.addEventListener('input', function (event) {
                if (event.target.matches('input[name*="refund_fee_to_client"]') ||
                    event.target.matches('input[name*="refund_task_supplier_charge"]') ||
                    event.target.matches('input[name*="new_task_profit"]') ||
                    event.target.matches('input[name*="total_refund_to_client"]')) {

                    const taskSection = event.target.closest('.task-refund-section');
                    const index = taskSection.dataset.taskIndex;

                    const originalSellingPrice = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][original_selling_price]"]`).value) || 0;
                    const originalCostPrice = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][original_cost_price]"]`).value) || 0;
                    const originalProfit = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][original_profit]"]`).value) || 0;

                    let refundFeeToClient = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][refund_fee_to_client]"]`).value) || 0;
                    let refundTaskSupplierCharge = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][refund_task_supplier_charge]"]`).value) || 0;
                    let newTaskProfit = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][new_task_profit]"]`).value) || 0;
                    let totalRefundToClient = parseFloat(taskSection.querySelector(`input[name="tasks_for_refund[${index}][total_refund_to_client]"]`).value) || 0;

                    // You can add dynamic recalculation logic here if needed
                }
            });
        });
    </script>
</x-app-layout>