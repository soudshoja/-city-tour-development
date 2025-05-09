<x-app-layout>
    <div class="container mx-auto px-4">
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li>
                <a href="{{ route('payment.link.index') }}" class="hover:text-blue-500 hover:underline">Payment Links</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <span class="text-gray-500">Create New</span>
            </li>
        </ul>
        <div class="p-6 bg-white rounded-lg shadow-md">
            <form action="{{ route('payment.link.store') }}" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" name="amount" id="amount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <input type="text" name="notes" id="notes" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                        <select name="currency" id="currency" class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->iso_code }}" {{ $currency->country ? $currency->country->name == 'Kuwait' ? 'selected' : '' : ''}}>{{ $currency->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payment-gateway" class="block text-sm font-medium text-gray-700">Payment Gateway</label>
                        <select name="payment_gateway" id="payment-gateway" class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($paymentGateways as $gateway)
                                <option value="{{ $gateway->name }}">{{ $gateway->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="client" class="block text-sm font-medium text-gray-700">Client</label>
                        <select name="client_id" id="payment_client_id" class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Client</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(auth()->user()->role_id == App\Models\Role::COMPANY)
                    <div>
                        <label for="agent" class="block text-sm font-medium text-gray-700">Agent</label>
                        <select name="agent_id" id="payment_agent_id" class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Agent</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-md shadow hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Create Payment Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>