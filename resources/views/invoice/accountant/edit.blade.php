<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Invoice') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Invoice Number -->
                            <div>
                                <label for="invoice_number" :value="__('Invoice Number')" />
                                <input id="invoice_number" class="block mt-1 w-full" type="text" name="invoice_number" value="{{ old('invoice_number', $invoice->invoice_number) }}" required autofocus />
                                @error('invoice_number')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Amount -->
                            <div>
                                <label for="amount" :value="__('Amount')" />
                                <input id="amount" class="block mt-1 w-full" type="number" step="0.01" name="amount" value="{{ old('amount', $invoice->amount) }}" required />
                                @error('amount')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div>
                                <label for="status" :value="__('Status')" />
                                <select id="status" name="status" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="pending" {{ old('status', $invoice->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="paid" {{ old('status', $invoice->status) == 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="cancelled" {{ old('status', $invoice->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                                @error('status')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <!-- Payment Method -->
                            <div>
                                <label for="payment_method" :value="__('Payment Method')" />
                                <select id="payment_method" name="payment_method" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="credit_card" {{ old('payment_method', $invoice->payment_method) == 'credit_card' ? 'selected' : '' }}>Credit Card</option>
                                    <option value="bank_transfer" {{ old('payment_method', $invoice->payment_method) == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="cash" {{ old('payment_method', $invoice->payment_method) == 'cash' ? 'selected' : '' }}>Cash</option>
                                </select>
                                @error('payment_method')
                                <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="flex items mt-6">
                            <button class="ml-4">
                                {{ __('Update Invoice') }}
                            </button>
                            <a href="{{ route('invoices.index') }}" class="ml-4 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>