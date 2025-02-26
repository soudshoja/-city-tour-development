<x-app-layout>
    <div class="container mx-auto mt-10">
        <!-- Title -->
        <h1 class="text-3xl font-bold text-center mb-6">Create New Records</h1>

        <!-- Options Buttons -->
        <div class="flex justify-center gap-4 mb-6">
            <button class="btn-option bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600" data-form="branchForm">Branch</button>
            <button class="btn-option bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600" data-form="agentForm">Agent</button>
            <button class="btn-option bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600" data-form="accountantForm">Accountant</button>
            <button class="btn-option bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600" data-form="clientForm">Client</button>
        </div>

        <!-- Forms -->
        <!-- Branch Form -->
        <div id="branchForm" class="form">
            <form action="{{ route('users.create') }}" method="POST" class="p-4 border border-gray-300 rounded-lg shadow">
                @csrf
                <!-- Hidden Company ID -->
                <input type="hidden" name="company_id" value="{{ auth()->user()->company_id}}">

                <h2 class="text-xl font-bold mb-4">Create Branch</h2>
                <div class="mb-4">
                    <label for="branch_name" class="block font-medium">Branch Name</label>
                    <input type="text" name="name" id="branch_name" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="branch_email" class="block font-medium">Email</label>
                    <input type="email" name="email" id="branch_email" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="branch_phone" class="block font-medium">Phone</label>
                    <input type="text" name="phone" id="branch_phone" class="w-full px-3 py-2 border border-gray-300 rounded">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Submit</button>
            </form>
        </div>

        <!-- Agent Form -->
        <div id="agentForm" class="form hidden">
            <form action="{{ route('companies.createAgent') }}" method="POST" class="p-4 border border-gray-300 rounded-lg shadow">
                @csrf
                <h2 class="text-xl font-bold mb-4">Create Agent</h2>
                <div class="mb-4">
                    <label for="agent_name" class="block font-medium">Agent Name</label>
                    <input type="text" name="name" id="agent_name" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="agent_email" class="block font-medium">Email</label>
                    <input type="email" name="email" id="agent_email" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="agent_password" class="block font-medium">Password</label>
                    <input type="password" name="password" id="agent_password" class="w-full px-3 py-2 border border-gray-300 rounded" required>

                </div>

                <div class="mb-4">
                    <label for="agent_phone" class="block font-medium">Phone</label>
                    <input type="text" name="phone" id="agent_phone" class="w-full px-3 py-2 border border-gray-300 rounded">
                </div>
                <div class="mb-4">
                    <label for="agent_type" class="block font-medium">Agent Type</label>
                    <input type="text" name="type" id="agent_type" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="agent_branch" class="block font-medium">Branch</label>
                    <select name="branch_id" id="agent_branch" class="w-full px-3 py-2 border border-gray-300 rounded">
                        <option value="">Select Branch</option>
                        @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Submit</button>
            </form>
        </div>

        <!-- Accountant Form -->
        <div id="accountantForm" class="form hidden">
            <form action="{{ route('companies.createAccountant') }}" method="POST" class="p-4 border border-gray-300 rounded-lg shadow">
                @csrf
                <h2 class="text-xl font-bold mb-4">Create Accountant</h2>
                <div class="mb-4">
                    <label for="accountant_name" class="block font-medium">Accountant Name</label>
                    <input type="text" name="name" id="accountant_name" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="accountant_email" class="block font-medium">Email</label>
                    <input type="email" name="email" id="accountant_email" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="accountant_phone" class="block font-medium">Phone</label>
                    <input type="text" name="phone" id="accountant_phone" class="w-full px-3 py-2 border border-gray-300 rounded">
                </div>
                <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">Submit</button>
            </form>
        </div>

        <!-- Client Form -->
        <div id="clientForm" class="form hidden">
            <form action="{{ route('companies.createClient') }}" method="POST" class="p-4 border border-gray-300 rounded-lg shadow">
                @csrf
                <h2 class="text-xl font-bold mb-4">Create Client</h2>
                <div class="mb-4">
                    <label for="client_name" class="block font-medium">Client Name</label>
                    <input type="text" name="name" id="client_name" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="client_email" class="block font-medium">Email</label>
                    <input type="email" name="email" id="client_email" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="client_phone" class="block font-medium">Phone</label>
                    <input type="text" name="phone" id="client_phone" class="w-full px-3 py-2 border border-gray-300 rounded">
                </div>
                <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Submit</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.btn-option').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.form').forEach(form => form.classList.add('hidden'));
                const formId = button.getAttribute('data-form');
                document.getElementById(formId).classList.remove('hidden');
            });
        });
    </script>

</x-app-layout>