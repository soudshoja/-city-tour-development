<x-app-layout>
    <nav>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('transactions.index') }}" class="customBlueColor hover:underline">Transactions</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Journal Entry</span>
            </li>
        </ul>
    </nav>
    <header class="p-2 bg-white rounded shadow my-2 text-xl font-bold mb-4">
        Journal Entry
    </header>
    <main class="p-2 bg-white rounded shadow">
        <body>
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Date/Time</th>
                        <th>Description</th>
                        <th>Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($journalEntries as $entry)
                    <tr>
                        <td class="text-center">{{ $entry->transaction_id }}</td>    
                        <td class="text-center">{{ $entry->created_at }}</td>    
                        <td class="text-center">{{ $entry->description }}</td>
                        <td class="text-center">{{ $entry->account->name }}</td>
                        <td class="text-center">{{ $entry->debit }}</td>
                        <td class="text-center">{{ $entry->credit }}</td>
                        
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </body>
    </main>
</x-app-layout>