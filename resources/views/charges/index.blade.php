<x-app-layout>
    <div class="bg-white rounded-md p-2">
        <div class="charge-header flex justify-between">
            <h2 class="text-xl font-semibold">Charges</h2>
            <a href="{{ route('charges.create') }}" class="btn btn-primary">Add Charge</a>
        </div>
        <div class="charge-body md:block">
            <table class="hidden md:block w-full mt-4">
                <thead>
                    <tr class="">
                        <th class="">Charge ID</th>
                        <th class="">Charge Name</th>
                        <th class="">Charge Type</th>
                        <th class="">Description</th>
                        <th class="">Amount</th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charges as $charge)
                    <tr class="hover:bg-gray-100 md:hover:bg-transparent">
                        <td class="">{{ $charge->id }}</td>
                        <td class="">{{ $charge->name }}</td>
                        <td class="">{{ $charge->type }}</td>
                        <td class="">{{ $charge->description }}</td>
                        <td class="">{{ $charge->amount }}</td>
                        <td class="flex flex-col gap-2">
                            <a href="{{ route('charges.edit', $charge->id) }}" class="btn btn-primary">Edit</a>
                            <form action="{{ route('charges.destroy', $charge->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>