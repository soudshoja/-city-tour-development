<div class="flex flex-col gap-4">
    <!-- Hotel Selection -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Hotel</label>
        <x-searchable-dropdown
            name="hotel_id"
            :items="$hotels"
            :selectedId="$task->hotelDetails->hotel_id ?? ''"
            :selectedName="$task->hotelDetails->hotel->name ?? ''"
            placeholder="Select a hotel" />
    </div>

    <!-- Room Type & Room Number -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Room Type</label>
            <input type="text"
                name="room_type"
                value="{{ $task->hotelDetails->room_type ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Room Number</label>
            <input type="text"
                name="room_number"
                value="{{ $task->hotelDetails->room_number ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <!-- Meal Type -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Meal Type</label>
        <input type="text"
            name="meal_type"
            value="{{ $task->hotelDetails->meal_type ?? '' }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
    </div>

    <!-- Check In & Check Out -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Check In</label>
            <input type="date"
                name="check_in"
                value="{{ $task->hotelDetails->check_in ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Check Out</label>
            <input type="date"
                name="check_out"
                value="{{ $task->hotelDetails->check_out ?? '' }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>
</div>
