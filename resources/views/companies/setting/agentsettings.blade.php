<x-app-layout>
    <div class="container mx-auto py-6 space-y-8">
        <!-- Success and Error Messages -->
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md shadow-sm" role="alert">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md shadow-sm" role="alert">
            {{ session('error') }}
        </div>
        @endif

        <!-- Add New Agent Type Form -->
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Add New Agent Type</h2>
            <form action="{{ route('agent-types.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-gray-700 font-medium">Agent Type Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required />
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition">
                    Add Agent Type
                </button>
            </form>
        </section>

        <!-- List of Existing Agent Types -->
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Existing Agent Types</h2>
            @if($agentTypes->isEmpty())
            <p class="text-gray-600">No agent types found.</p>
            @else
            <ul class="divide-y divide-gray-200">
                @foreach($agentTypes as $type)
                <li class="py-3 text-gray-700">{{ $type->name }}</li>
                @endforeach
            </ul>
            @endif
        </section>

        <!-- Delete Agent Type Form -->
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Delete Agent Type</h2>
            <form action="{{ route('agent-types.delete') }}" method="POST" class="space-y-4">
                @csrf
                @method('DELETE')
                <div>
                    <label for="agent_type_id" class="block text-gray-700 font-medium">Select Agent Type to Delete</label>
                    <select
                        name="agent_type_id"
                        id="agent_type_id"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required>
                        @foreach($agentTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition">
                    Delete Agent Type
                </button>
            </form>
        </section>
    </div>

</x-app-layout>