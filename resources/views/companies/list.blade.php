<x-app-layout>
    <div class="bg-white rounded-md shadow-md  mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900">Companies</h1>
    </div>
    <div class="bg-white shadow-md rounded-md p-4 my-4">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <table class="" id="companiesTable">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr class="leading-10 hover:bg-gray-100">
                            <td>{{ $company['id'] }}</td>
                            <td>{{ $company['name'] }}</td>
                            <td>{{ $company['email'] }}</td>
                            <td>
                                @can('view', $company)
                                    <a href="{{ route('companies.show', $company['id']) }}"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">View</a>
                                @endcan
                                @can('update', $company)
                                    <a href="{{ route('companies.edit', $company['id']) }}"
                                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Edit</a>
                                @endcan
                                {{-- @can('delete', $company)
                            <form action="{{ route('companies.destroy', $company['id']) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-900">Delete</button>
                            </form>
                            @endcan --}}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <script>
        let companiesTable = new DataTable('#companiesTable', {});
    </script>
</x-app-layout>
