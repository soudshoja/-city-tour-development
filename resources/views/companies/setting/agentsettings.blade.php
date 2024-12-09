<x-app-layout>
    <div class="container mx-auto py-6 space-y-8">

        <div class="grid grid-cols-3 gap-4 w-full">
            <!-- Left Column (Form Section) -->
            <div class="col-span-2 bg-white shadow p-5">
                <form action="{{ route('agent-types.create') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="flex gap-4">
                        <!-- Input field with increased size -->
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="flex-1 px-4 py-2 border rounded-lg"
                            placeholder="Agent Type Name"
                            required />

                        <!-- Button with smaller size -->
                        <button type="submit" class="w-auto bg-gray-200 text-black py-2 px-4 rounded-lg 
                             hover:bg-gray-300 transition flex">
                            <svg class="w-6 h-6 pr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                <path fill="currentColor" d="M512 0C229.232 0 0 229.232 0 512c0 282.784 229.232 512 512 512c282.784 0 512-229.216 512-512C1024 229.232 794.784 0 512 0m0 961.008c-247.024 0-448-201.984-448-449.01c0-247.024 200.976-448 448-448s448 200.977 448 448s-200.976 449.01-448 449.01M736 480H544V288c0-17.664-14.336-32-32-32s-32 14.336-32 32v192H288c-17.664 0-32 14.336-32 32s14.336 32 32 32h192v192c0 17.664 14.336 32 32 32s32-14.336 32-32V544h192c17.664 0 32-14.336 32-32s-14.336-32-32-32" />
                            </svg>
                            <span>Add Agent Type</span>
                        </button>
                    </div>

                </form>

                <!-- types list -->
                <div class="bg-gray-200 mt-5 p-5 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 mb-6">Agent Type List</h2>
                    @if($agentTypes->isEmpty())
                    <p class="text-gray-600">No agent types found.</p>
                    @else
                    <ul class="divide-y divide-gray-200">
                        @foreach($agentTypes as $type)
                        <li class="flex justify-between items-center py-3 text-gray-700">
                            <!-- Agent Type Name on the left -->
                            <span>{{ $type->name }}</span>

                            <!-- Delete Button on the right -->
                            <form action="{{ route('agent-types.delete') }}" method="POST" class="flex-shrink-0">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="agent_type_id" value="{{ $type->id }}">
                                <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                    Delete
                                </button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                </div>

            </div>

            <!-- Right Column (Settings Section) -->
            <div class="bg-white shadow p-5">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Agents Settings</h2>
            </div>
        </div>






    </div>

</x-app-layout>