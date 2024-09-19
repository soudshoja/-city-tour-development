<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">company List</h1>
        
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">Name</th>
                    <th class="py-2 px-4 border-b">Code</th>
                    <th class="py-2 px-4 border-b">Nationality</th>
                    <th class="py-2 px-4 border-b">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($companies as $company)
                    <tr>
                        <td class="py-2 px-4 border-b">{{ $company->name }}</td>
                        <td class="py-2 px-4 border-b">{{ $company->code }}</td>
                        <td class="py-2 px-4 border-b">{{ $company->nationality }}</td>
                        <td class="py-2 px-4 border-b">
                            <a href="{{ route('companiesshow.show', $company->id) }}" class="bg-blue-500 text-white py-1 px-2 rounded">Show</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
