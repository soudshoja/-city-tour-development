<div x-show="createClientModal" x-cloak class="fixed inset-0 z-50 bg-gray-800 bg-opacity-30 flex items-center justify-center">
    <div @click.away="createClientModal = false; showOptions = true; showForm = false"
        class="bg-white w-full max-w-xl rounded-xl shadow-xl p-6 space-y-6 border">

        <!-- Upload + Fill Options -->
        <div x-show="showOptions" class="space-y-4">
            <label class="block text-sm font-medium text-gray-700">Upload Passport</label>
            <p class="text-gray-600 italic text-xs">Please choose appropriate file to proceed</p>
            <button id="upload-passport-btn"
                class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-full">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" />
                </svg>
                Upload
            </button>
            <button @click="showOptions = false; showForm = true"
                class="w-full flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white py-2 px-4 rounded-full">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M5 6h14M5 12h14M5 18h14" />
                </svg>
                Fill Form
            </button>
        </div>

        <!-- Client Form -->
        <div x-show="showForm" x-cloak>
            <div class="flex justify-between items-center border-b pb-2 mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Create Client</h2>
                <button @click="createClientModal = false; showOptions = true; showForm = false"
                    class="text-red-600 text-2xl font-bold leading-none hover:text-red-800">
                    &times;
                </button>
            </div>

            <form action="{{ route('clients.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="task_id" :value="modalTaskId">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Client's Name</label>
                    <input type="text" name="name" id="nameChat" :value="modalClientName"
                        class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                </div>

                <div class="flex gap-4">
                    <div class="w-2/3">
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="emailChat"
                            class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div class="w-1/3">
                        <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                        <input type="date" name="date_of_birthChat" id="date_of_birth_chat"
                            class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <div class="flex gap-2">
                        <select name="dial_code" id="dial_code"
                            class="w-1/3 border border-gray-300 rounded-md px-2 py-2">
                            @foreach ($countries as $country)
                            <option value="{{ $country->dialing_code }}">{{ $country->dialing_code }} ({{ $country->name }})</option>
                            @endforeach
                        </select>
                        <input type="text" name="phone" id="phoneChat"
                            class="w-2/3 border border-gray-300 rounded-md px-3 py-2"
                            placeholder="Enter phone" required>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="w-1/2">
                        <label class="block text-sm font-medium text-gray-700">Passport No</label>
                        <input type="text" name="passport" id="passport_noChat"
                            class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div class="w-1/2">
                        <label class="block text-sm font-medium text-gray-700">Civil No</label>
                        <input type="text" name="civil_noChat" id="civil_noChat"
                            class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" name="address" id="addressChat"
                        class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Agent</label>
                    <input type="text" name="agent_name" id="agent_idChat" :value="modalAgentName"
                        class="w-full border border-gray-300 rounded-md px-3 py-2" readonly>
                </div>

                <div class="flex justify-end gap-4 pt-4 border-t">
                    <button type="button" @click="createClientModal = false; showOptions = true; showForm = false"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-full text-gray-700">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-full hover:bg-blue-700">Register Client</button>
                </div>
            </form>
        </div>
    </div>
</div>
