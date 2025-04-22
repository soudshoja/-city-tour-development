<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Payment Links</span>
        </li>
    </ul>
    <div class="p-2 bg-white rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Payment Links</h2>
            <a href="{{ route('payment.link.create') }}" class="btn btn-primary">Create Payment Link</a>
        </div>

        @if ($payments->isEmpty())
            <p class="text-gray-500">No payment links found.</p>
        @else
            <table class="min-w-full bg-white border border-gray-200 rounded shadow">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Link</th>
                        <th class="px-4 py-2 text-left">Amount</th>
                        <th class="px-4 py-2 text-left">Created At</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payments)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="{{ $payments->voucher_number }}" target="_blank" class="text-blue-500 hover:underline">{{ $payments->voucher_number }}</a>
                            </td>
                            <td class="px-4 py-2">{{ $payments->amount }}</td>
                            <td class="px-4 py-2">{{ $payments->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2">
                                <a href="" class="text-blue-500 hover:underline">Edit</a>
                                <form action="" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                    </tr>
                </tfoot>
            </table>
        @endif
        
    </div>
</x-app-layout>