<x-app-layout>
    <div class="container mx-auto p-4 md:p-6">
        <!-- Client Info Section -->
        <div
            class="panel dark:bg-gray-800 shadow-md rounded-lg p-4 flex flex-col md:flex-row items-center justify-between">
            <!-- Client Picture and Name Section -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-x-4">
                <!-- Client Picture -->
                <div class="flex-shrink-0">
                    <img src="{{ asset('images/userPic.svg') }}" alt="Client Picture" class="rounded-full w-24 h-24">
                </div>

                <!-- Client Name and Number -->
                <!-- Client Name and Number -->
                <div class="text-center-mobile-left-desktop">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $client->name }}</h2>
                    <p class="text-gray-500 dark:text-gray-400">{{ $client->email }}</p>
                </div>




            </div>

            <!-- Button -->
            <div class="mt-4 md:mt-0">
                <x-primary-button> Create Payment Link </x-primary-button>
            </div>
        </div>

        <!-- Client Orders Section -->
        <div class="panel dark:bg-gray-800 shadow-md rounded-lg p-4 mt-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Orders</h3>
            @if($client->orders && $client->orders->count() > 0)
            <ul>
                @foreach($client->orders as $order)
                <li class="border-b border-gray-200 dark:border-gray-600 py-2">
                    <strong class="text-gray-900 dark:text-gray-100">Order #{{ $order->id }}:</strong> <span
                        class="text-gray-500 dark:text-gray-400">{{ $order->details }}</span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="text-gray-500 dark:text-gray-400">No orders found for this client.</p>
            @endif
        </div>

        <!-- Invoice List Section -->
        <div class="panel dark:bg-gray-800 shadow-md rounded-lg p-4 mt-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Invoice List</h3>
            @if($client->invoices && $client->invoices->count() > 0)
            <ul>
                @foreach($client->invoices as $invoice)
                <li class="border-b border-gray-200 dark:border-gray-600 py-2">
                    <strong class="text-gray-900 dark:text-gray-100">Invoice #{{ $invoice->id }}:</strong> <span
                        class="text-gray-500 dark:text-gray-400">{{ $invoice->amount }} - {{ $invoice->status }}</span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="text-gray-500 dark:text-gray-400">No invoices found for this client.</p>
            @endif
        </div>
    </div>
</x-app-layout>