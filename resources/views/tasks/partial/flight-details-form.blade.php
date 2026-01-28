<div class="flex flex-col gap-4" @change="updateFlightDetail($event)" @dropdown-select="updateFlightDetail($event)">
    @forelse($task->flightDetail as $index => $flight)
    <div class="border border-gray-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Flight {{ $index + 1 }}</h4>

        <div class="grid grid-cols-1 gap-4">
            <!-- Airport From & Terminal From -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Airport From</label>
                    <x-searchable-dropdown
                        name="flights[{{ $index }}][airport_from_id]"
                        :items="$airports"
                        :selectedId="$flight->airport_from_id ?? ''"
                        :selectedName="$flight->airportFrom ? $flight->airportFrom->iata_code . ' - ' . $flight->airportFrom->name : ($flight->airport_from ?? '')"
                        placeholder="Select airport" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Terminal From</label>
                    <input type="text"
                        name="flights[{{ $index }}][terminal_from]"
                        value="{{ $flight->terminal_from }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Departure Time -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Departure Time</label>
                <input type="datetime-local"
                    name="flights[{{ $index }}][departure_time]"
                    value="{{ $flight->departure_time ? \Carbon\Carbon::parse($flight->departure_time)->format('Y-m-d\TH:i') : '' }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            <!-- Airport To & Terminal To -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Airport To</label>
                    <x-searchable-dropdown
                        name="flights[{{ $index }}][airport_to_id]"
                        :items="$airports"
                        :selectedId="$flight->airport_to_id ?? ''"
                        :selectedName="$flight->airportTo ? $flight->airportTo->iata_code . ' - ' . $flight->airportTo->name : ($flight->airport_to ?? '')"
                        placeholder="Select airport" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Terminal To</label>
                    <input type="text"
                        name="flights[{{ $index }}][terminal_to]"
                        value="{{ $flight->terminal_to }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Arrival Time -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Arrival Time</label>
                <input type="datetime-local"
                    name="flights[{{ $index }}][arrival_time]"
                    value="{{ $flight->arrival_time ? \Carbon\Carbon::parse($flight->arrival_time)->format('Y-m-d\TH:i') : '' }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            <!-- Airline -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Airline</label>
                <x-searchable-dropdown
                    name="flights[{{ $index }}][airline_id_new]"
                    :items="$airlines"
                    :selectedId="$flight->airline_id_new ?? ''"
                    :selectedName="$flight->airline ? $flight->airline->iata_designator . ' - ' . $flight->airline->name : ($flight->airline_id ?? '')"
                    placeholder="Select airline" />
            </div>

            <!-- Flight Number & Class Type -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Flight Number</label>
                    <input type="text"
                        name="flights[{{ $index }}][flight_number]"
                        value="{{ $flight->flight_number }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Class Type</label>
                    <select name="flights[{{ $index }}][class_type]"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">Select class</option>
                        <option value="economy" {{ $flight->class_type === 'economy' ? 'selected' : '' }}>Economy</option>
                        <option value="business" {{ $flight->class_type === 'business' ? 'selected' : '' }}>Business</option>
                        <option value="first" {{ $flight->class_type === 'first' ? 'selected' : '' }}>First Class</option>
                    </select>
                </div>
            </div>

            <!-- Duration & Baggage -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Duration</label>
                    <input type="text"
                        name="flights[{{ $index }}][duration_time]"
                        value="{{ $flight->duration_time }}"
                        placeholder="2h 30m"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Baggage Allowed</label>
                    <input type="text"
                        name="flights[{{ $index }}][baggage_allowed]"
                        value="{{ $flight->baggage_allowed }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Seat Number & Ticket Number -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Seat Number</label>
                    <input type="text"
                        name="flights[{{ $index }}][seat_no]"
                        value="{{ $flight->seat_no }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Ticket Number</label>
                    <input type="text"
                        name="flights[{{ $index }}][ticket_number]"
                        value="{{ $flight->ticket_number }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Hidden ID field to identify which flight record to update -->
            <input type="hidden" name="flights[{{ $index }}][id]" value="{{ $flight->id }}">
        </div>
    </div>
    @empty
    <p class="text-sm text-gray-500 italic">No flight details available</p>
    @endforelse
</div>
