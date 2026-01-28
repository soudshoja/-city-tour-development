<div class="flex flex-col gap-4" @change="updateVisaDetail($event)">
    <!-- Visa Type & Application Number -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Visa Type</label>
            <input type="text"
                name="visa_type"
                value="{{ $task->visaDetails->visa_type ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Application Number</label>
            <input type="text"
                name="application_number"
                value="{{ $task->visaDetails->application_number ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <!-- Issuing Country & Expiry Date -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Issuing Country</label>
            <input type="text"
                name="issuing_country"
                value="{{ $task->visaDetails->issuing_country ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
            <input type="date"
                name="expiry_date"
                value="{{ $task->visaDetails->expiry_date ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <!-- Number of Entries & Stay Duration -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Number of Entries</label>
            <input type="text"
                name="number_of_entries"
                value="{{ $task->visaDetails->number_of_entries ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Stay Duration</label>
            <input type="text"
                name="stay_duration"
                value="{{ $task->visaDetails->stay_duration ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>
</div>
