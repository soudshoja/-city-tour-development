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
                    <input type="date" id="startDate" x-model="startDate" class="border border-gray-300 dark:border-gray-700 dark:bg-dark p-2 rounded">
                </div>
                <div class="flex items-center mx-4">
                    <label for="endDate" class="mr-2">End Date:</label>
                    <input type="date" id="endDate" x-model="endDate" class="border border-gray-300 dark:border-gray-700 dark:bg-dark p-2 rounded">
                </div>
            </div>
            <button id="filter-button" class="bg-blue-500 dark:bg-blue-600 hover:bg-blue-700 text-white text-lg px-2 rounded-md shadow-md w-48 font-semibold">Search</button>
        </div>
        <div class="px-2 block" id="booking-container">
            @if(count($pastBookings) == 0)
            <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">No Past Booking Found</div>
            @else
            @foreach($pastBookings as $booking)
            <div class="rounded-md border-black border dark:border-gray-600 shadow-lg p-2 dark:bg-gray-500">
                <div class="flex justify-between mb-2">
                    <div>
                        Booking Id: <strong> {{ $booking['BookingId'] }} </strong>
                    </div>
                    <div class="bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 p-2 text-white rounded-md">{{ $booking['AgencyName']}}</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="inline-flex justify-between">
                            <div class="flex sm:flex-col lg:flex-row gap-2">
                                <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md items-center font-bold">
                                    {{ $booking['ConfirmationNo'] }}
                                </div>
                                <div class="bg-gradient-to-r from-green-800 to-green-400 items-center p-2 text-white rounded-md font-bold">{{ $booking['BookingStatus']}}</div>
                            </div>
                            <div>
                                Booking Price : <strong> {{ $booking['BookingPrice'] }} {{ $booking['Currency']}} </strong>
                            </div>
                        </div>
                        <div class="">Agent Markup: {{ $booking['AgentMarkup'] }} {{ $booking['Currency'] }}</div>
                        <div class="">Trip Name: {{ $booking['TripName']}}</div>
                        <div class="">Hotel Code: {{ $booking['TBOHotelCode']}}</div>
                        <div class="">Client Reference Number: {{ $booking['ClientReferenceNumber']}}</div>
                    </div>
                    <div class="grid w-auto border-gray-400 border w-full md:w-auto p-2 rounded-md">
                        <div class="">
                            <p>Booking Date: </p>
                            <strong>
                                {{ $booking['BookingDate']}}
                            </strong>
                        </div>
                        <div class="">
                            <p>Check In: </p>
                            <strong>
                                {{ $booking['CheckInDate']}}
                            </strong>
                        </div>
                        <div class="">
                            <p>Check Out: </p>
                            <strong>
                                {{ $booking['CheckOutDate']}}
                            </strong>
                        </div>

                    </div>
                </div>
            </div>

            @endforeach
            @endif
        </div>
    </div>
    <script>
        const filterButton = document.getElementById('filter-button');
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const bookingContainer = document.getElementById('booking-container');

        filterButton.addEventListener('click', () => {
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            // Validate date range (same logic as in the Alpine.js example)
            const oneDay = 24 * 60 * 60 * 1000;
            const firstDate = new Date(startDate);
            const secondDate = new Date(endDate);
            const diffDays = Math.round(Math.abs((firstDate - secondDate) / oneDay));

            console.log('range of dates: ', diffDays);

            if (diffDays > 100) {
                alert('Date range cannot exceed 100 days.');
                return;
            }

            let url = "{!! route('suppliers.tbo.booking-details', ['startDate' => '__startDate__', 'endDate' => '__endDate__']) !!}";
            url = url.replace('__startDate__', startDate);
            url = url.replace('__endDate__', endDate);

            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    bookingContainer.innerHTML = '';

                    if (data.length == 0 || typeof data.error !== 'undefined') {
                        bookingContainer.innerHTML = '<div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center w-full">No Past Booking Found</div>';
                    } else {
                        data.forEach(booking => {
                            const bookingDiv = document.createElement('div');
                            bookingDiv.className = 'rounded-md border-black border dark:border-gray-600 shadow-lg p-2 dark:bg-gray-500';
                            bookingDiv.innerHTML = `
                                <div class="flex justify-between mb-2">
                                    <div>
                                        Booking Id: <strong> ${booking.BookingId} </strong>
                                    </div>
                                    <div class="bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 p-2 text-white rounded-md">${booking.AgencyName}</div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4">
                                    <div class="grid grid-cols-1 gap-2">
                                        <div class="inline-flex justify-between">
                                            <div class="flex sm:flex-col lg:flex-row gap-2">
                                                <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md items-center font-bold">
                                                    ${booking.ConfirmationNo}
                                                </div>
                                                <div class="bg-gradient-to-r from-green-800 to-green-400 items-center p-2 text-white rounded-md font-bold">${booking.BookingStatus}</div>
                                            </div>
                                            <div>
                                                Booking Price : <strong> ${booking.BookingPrice} ${booking.Currency} </strong>
                                            </div>
                                        </div>
                                        <div class="">Agent Markup: ${booking.AgentMarkup} ${booking.Currency}</div>
                                        <div class="">Trip Name: ${booking.TripName}</div>
                                        <div class="">Hotel Code: ${booking.TBOHotelCode}</div>
                                        <div class="">Client Reference Number: ${booking.ClientReferenceNumber}</div>
                                    </div>
                                    <div class="grid w-auto border-gray-400 border w-full md:w-auto p-2 rounded-md">
                                        <div class="">
                                            <p>Booking Date: </p>
                                            <strong>${booking.BookingDate}</strong>
                                        </div>
                                        <div class="">
                                            <p>Check In: </p>
                                            <strong>${booking.CheckInDate}</strong>
                                        </div>
                                        <div class="">
                                            <p>Check Out: </p>
                                            <strong>${booking.CheckOutDate}</strong>
                                        </div>

                                    </div>
                                </div>`;
                            bookingContainer.appendChild(bookingDiv);
                        });
                    }

                })
                .catch(error => {
                    alert(error);
                });
        });
    </script>
</div>