<x-app-layout>
    <nav>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <span>Transactions</span>
            </li>
        </ul>
    </nav>
    <header class="p-2 bg-gray-100 rounded shadow text-2xl font-bold mb-4 text-center">
        Transactions History
    </header>
    <main class="transaction p-4 bg-white shadow rounded">
        <body>
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Description</th>
                        <!-- <th>Type</th> -->
                        <!-- <th>Amount</th> -->
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                    <tr>
                        <td class="text-center">{{ $transaction->id }}</td>
                        <td class="text-center">{{ $transaction->description }}</td>
                        <!-- <td class="text-center">{{ $transaction->transaction_type }}</td> -->
                        <!-- <td class="text-center">{{ $transaction->amount }}</td> -->
                        <td class="text-center">{{ $transaction->created_at }}</td>
                        <td>
                            <a href="{{ route('journal-entries.index', $transaction->id) }}" class="text-blue-500 hover:underline">
                                View Ledger
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </body>
    </main>
</x-app-layout>