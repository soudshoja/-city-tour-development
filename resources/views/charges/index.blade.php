<x-app-layout>
    <div class="bg-white rounded-md p-2">
        <div class="charge-header flex justify-between">
            <h2 class="text-xl font-semibold">Charges</h2>
            <a href="{{ route('charges.create') }}" class="btn btn-primary">Add Charge</a>
        </div>
        <div class="charge-body">
            <table>
                <thead>
                    <tr>
                        <th>Charge ID</th>
                        <th>Charge Name</th>
                        <th>Charge Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charges as $charge)
                    <tr>
                        <td>{{ $charge->id }}</td>
                        <td>{{ $charge->name }}</td>
                        <td>{{ $charge->type }}</td>
                        <td>{{ $charge->description }}</td>
                        <td>{{ $charge->amount }}</td>
                        <td>
                            <a href="{{ route('charges.edit', $charge->id) }}" class="btn btn-primary">Edit</a>
                            <form action="{{ route('charges.destroy', $charge->id) }}" method="POST">
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