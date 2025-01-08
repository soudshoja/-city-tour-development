<div class="past-booking mb-3" x-data="">
    <div class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-t-lg font-semibold">
        PAST BOOKINGS
    </div>
    <hr class="dark:border-gray-200">
    <div class="p-4 rounded-b-lg bg-white dark:bg-gray-600 overflow-hidden shadow-sm">
        <div class="flex justify-between mb-4">
            <div class="flex justify-between items-center w-auto">
                <div class="flex items-center mx-4">
                    <label for="startDate" class="mr-2">Start Date:</label>
                    <input type="date" id="startDate" x-model="startDate" class="border border-gray-300 p-2 rounded">
                </div>
                <div class="flex items-center mx-4">
                    <label for="endDate" class="mr-2">End Date:</label>
                    <input type="date" id="endDate" x-model="endDate" class="border border-gray-300 p-2 rounded">
                </div>
            </div>
            <button @click="filterBookings" class="bg-blue-500 hover:bg-blue-700 text-white px-2 rounded">Filter</button>
        </div>
        <div class="px-2 block">
            @if(count($pastBookings) == 0)
            <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">No Past Booking Found</div>
            @else
            @foreach($pastBookings as $booking)
            <div class="rounded-md border-2 shadow-lg p-2">
                <div class="text-start">
                    Booking Id: <strong> {{ $booking['BookingId'] }} </strong>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 ">
                    <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['ConfirmationNo'] }}</div>
                    <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['BookingDate']}}</div>
                    <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['Currency']}}</div>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['AgentMarkup']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['AgencyName']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['BookingStatus']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['BookingPrice']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['TripName']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['TBOHotelCode']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['CheckInDate']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['CheckOutDate']}}</di>
                    <di class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $booking['ClientReferenceNumber']}}</di>
                </div>
            </div>

            @endforeach
            @endif
        </div>
    </div>
</div>