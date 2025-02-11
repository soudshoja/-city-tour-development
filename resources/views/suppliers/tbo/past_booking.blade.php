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
                    <input type="date" id="startDate" class="border border-gray-300 dark:border-gray-700 dark:bg-dark p-2 rounded" value="{{ $startDate }}">
                </div>
                <div class="flex items-center mx-4">
                    <label for="endDate" class="mr-2">End Date:</label>
                    <input type="date" id="endDate" class="border border-gray-300 dark:border-gray-700 dark:bg-dark p-2 rounded" value="{{ $endDate }}">
                </div>
            </div>
            <button id="filter-button" class="bg-blue-500 dark:bg-blue-600 hover:bg-blue-700 text-white text-lg px-2 rounded-md shadow-md w-48 font-semibold">Search</button>
        </div>
        <div class="px-2 block grid gap-2" id="booking-container">
            @if(count($pastBookings) == 0)
            <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">No Past Booking Found</div>
            @else
            @foreach($pastBookings as $booking)
            <div class="rounded-md border-black border dark:border-gray-600 shadow-lg p-2 dark:bg-gray-500 group">
                <div class="flex justify-between mb-2">
                    <div>
                        Booking Id: <strong> {{ $booking['BookingId'] }} </strong>
                    </div>
                    <div class="inline-flex">
                        <div class="bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 p-2 text-white rounded-md">{{ $booking['AgencyName'] }}</div>
                        <a href="{{ route('suppliers.tbo.cancel-booking', ['confirmationNo' => $booking['ConfirmationNo']]) }}" class="p-2 mx-2 bg-red-500 rounded-md">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.3094 2.25002H13.6908C13.9072 2.24988 14.0957 2.24976 14.2737 2.27819C14.977 2.39049 15.5856 2.82915 15.9146 3.46084C15.9978 3.62073 16.0573 3.79961 16.1256 4.00494L16.2373 4.33984C16.2562 4.39653 16.2616 4.41258 16.2661 4.42522C16.4413 4.90933 16.8953 5.23659 17.4099 5.24964C17.4235 5.24998 17.44 5.25004 17.5001 5.25004H20.5001C20.9143 5.25004 21.2501 5.58582 21.2501 6.00004C21.2501 6.41425 20.9143 6.75004 20.5001 6.75004H3.5C3.08579 6.75004 2.75 6.41425 2.75 6.00004C2.75 5.58582 3.08579 5.25004 3.5 5.25004H6.50008C6.56013 5.25004 6.5767 5.24998 6.59023 5.24964C7.10488 5.23659 7.55891 4.90936 7.73402 4.42524C7.73863 4.41251 7.74392 4.39681 7.76291 4.33984L7.87452 4.00496C7.94281 3.79964 8.00233 3.62073 8.08559 3.46084C8.41453 2.82915 9.02313 2.39049 9.72643 2.27819C9.90445 2.24976 10.093 2.24988 10.3094 2.25002ZM9.00815 5.25004C9.05966 5.14902 9.10531 5.04404 9.14458 4.93548C9.1565 4.90251 9.1682 4.86742 9.18322 4.82234L9.28302 4.52292C9.37419 4.24941 9.39519 4.19363 9.41601 4.15364C9.52566 3.94307 9.72853 3.79686 9.96296 3.75942C10.0075 3.75231 10.067 3.75004 10.3553 3.75004H13.6448C13.9331 3.75004 13.9927 3.75231 14.0372 3.75942C14.2716 3.79686 14.4745 3.94307 14.5842 4.15364C14.605 4.19363 14.626 4.2494 14.7171 4.52292L14.8169 4.82216L14.8556 4.9355C14.8949 5.04405 14.9405 5.14902 14.992 5.25004H9.00815Z" fill="#FFF" />
                                <path d="M5.91509 8.45015C5.88754 8.03685 5.53016 7.72415 5.11686 7.7517C4.70357 7.77925 4.39086 8.13663 4.41841 8.54993L4.88186 15.5017C4.96736 16.7844 5.03642 17.8205 5.19839 18.6336C5.36679 19.4789 5.65321 20.185 6.2448 20.7385C6.8364 21.2919 7.55995 21.5308 8.4146 21.6425C9.23662 21.7501 10.275 21.7501 11.5606 21.75H12.4395C13.7251 21.7501 14.7635 21.7501 15.5856 21.6425C16.4402 21.5308 17.1638 21.2919 17.7554 20.7385C18.347 20.185 18.6334 19.4789 18.8018 18.6336C18.9638 17.8206 19.0328 16.7844 19.1183 15.5017L19.5818 8.54993C19.6093 8.13663 19.2966 7.77925 18.8833 7.7517C18.47 7.72415 18.1126 8.03685 18.0851 8.45015L17.6251 15.3493C17.5353 16.6971 17.4713 17.6349 17.3307 18.3406C17.1943 19.025 17.004 19.3873 16.7306 19.6431C16.4572 19.8989 16.083 20.0647 15.391 20.1552C14.6776 20.2485 13.7376 20.25 12.3868 20.25H11.6134C10.2626 20.25 9.32255 20.2485 8.60915 20.1552C7.91715 20.0647 7.54299 19.8989 7.26958 19.6431C6.99617 19.3873 6.80583 19.025 6.66948 18.3406C6.52892 17.6349 6.46489 16.6971 6.37503 15.3493L5.91509 8.45015Z" fill="#FFF" />
                                <path d="M9.42546 10.2538C9.83762 10.2125 10.2052 10.5133 10.2464 10.9254L10.7464 15.9254C10.7876 16.3376 10.4869 16.7051 10.0747 16.7463C9.66256 16.7875 9.29503 16.4868 9.25381 16.0747L8.75381 11.0747C8.7126 10.6625 9.01331 10.295 9.42546 10.2538Z" fill="#FFF" />
                                <path d="M14.5747 10.2538C14.9869 10.295 15.2876 10.6625 15.2464 11.0747L14.7464 16.0747C14.7052 16.4868 14.3376 16.7875 13.9255 16.7463C13.5133 16.7051 13.2126 16.3376 13.2538 15.9254L13.7538 10.9254C13.795 10.5133 14.1626 10.2125 14.5747 10.2538Z" fill="#FFF" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="inline-flex justify-between">
                            <div class="flex sm:flex-col lg:flex-row gap-2">
                                <a href="{{ route('suppliers.tbo.booking-detail', ['confirmationNumber' => $booking['ConfirmationNo']]) }}" class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md items-center font-bold group-hover:animate-pulse cursor-pointer">
                                    {{ $booking['ConfirmationNo'] }}
                                </a>
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

            let url = "{!! route('suppliers.tbo.booking-details-by-date', ['startDate' => '__startDate__', 'endDate' => '__endDate__']) !!}";
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
                                    <div class="inline-flex">
                                        <div class="bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 p-2 text-white rounded-md">${booking.AgencyName}</div>
                                        <a href="{{ route('suppliers.tbo.cancel-booking', confirmationNumber => ${booking.ConfirmationNo}" class="bg-back text-white p-4">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.3094 2.25002H13.6908C13.9072 2.24988 14.0957 2.24976 14.2737 2.27819C14.977 2.39049 15.5856 2.82915 15.9146 3.46084C15.9978 3.62073 16.0573 3.79961 16.1256 4.00494L16.2373 4.33984C16.2562 4.39653 16.2616 4.41258 16.2661 4.42522C16.4413 4.90933 16.8953 5.23659 17.4099 5.24964C17.4235 5.24998 17.44 5.25004 17.5001 5.25004H20.5001C20.9143 5.25004 21.2501 5.58582 21.2501 6.00004C21.2501 6.41425 20.9143 6.75004 20.5001 6.75004H3.5C3.08579 6.75004 2.75 6.41425 2.75 6.00004C2.75 5.58582 3.08579 5.25004 3.5 5.25004H6.50008C6.56013 5.25004 6.5767 5.24998 6.59023 5.24964C7.10488 5.23659 7.55891 4.90936 7.73402 4.42524C7.73863 4.41251 7.74392 4.39681 7.76291 4.33984L7.87452 4.00496C7.94281 3.79964 8.00233 3.62073 8.08559 3.46084C8.41453 2.82915 9.02313 2.39049 9.72643 2.27819C9.90445 2.24976 10.093 2.24988 10.3094 2.25002ZM9.00815 5.25004C9.05966 5.14902 9.10531 5.04404 9.14458 4.93548C9.1565 4.90251 9.1682 4.86742 9.18322 4.82234L9.28302 4.52292C9.37419 4.24941 9.39519 4.19363 9.41601 4.15364C9.52566 3.94307 9.72853 3.79686 9.96296 3.75942C10.0075 3.75231 10.067 3.75004 10.3553 3.75004H13.6448C13.9331 3.75004 13.9927 3.75231 14.0372 3.75942C14.2716 3.79686 14.4745 3.94307 14.5842 4.15364C14.605 4.19363 14.626 4.2494 14.7171 4.52292L14.8169 4.82216L14.8556 4.9355C14.8949 5.04405 14.9405 5.14902 14.992 5.25004H9.00815Z" fill="#1C274C"/>
                                            <path d="M5.91509 8.45015C5.88754 8.03685 5.53016 7.72415 5.11686 7.7517C4.70357 7.77925 4.39086 8.13663 4.41841 8.54993L4.88186 15.5017C4.96736 16.7844 5.03642 17.8205 5.19839 18.6336C5.36679 19.4789 5.65321 20.185 6.2448 20.7385C6.8364 21.2919 7.55995 21.5308 8.4146 21.6425C9.23662 21.7501 10.275 21.7501 11.5606 21.75H12.4395C13.7251 21.7501 14.7635 21.7501 15.5856 21.6425C16.4402 21.5308 17.1638 21.2919 17.7554 20.7385C18.347 20.185 18.6334 19.4789 18.8018 18.6336C18.9638 17.8206 19.0328 16.7844 19.1183 15.5017L19.5818 8.54993C19.6093 8.13663 19.2966 7.77925 18.8833 7.7517C18.47 7.72415 18.1126 8.03685 18.0851 8.45015L17.6251 15.3493C17.5353 16.6971 17.4713 17.6349 17.3307 18.3406C17.1943 19.025 17.004 19.3873 16.7306 19.6431C16.4572 19.8989 16.083 20.0647 15.391 20.1552C14.6776 20.2485 13.7376 20.25 12.3868 20.25H11.6134C10.2626 20.25 9.32255 20.2485 8.60915 20.1552C7.91715 20.0647 7.54299 19.8989 7.26958 19.6431C6.99617 19.3873 6.80583 19.025 6.66948 18.3406C6.52892 17.6349 6.46489 16.6971 6.37503 15.3493L5.91509 8.45015Z" fill="#1C274C"/>
                                            <path d="M9.42546 10.2538C9.83762 10.2125 10.2052 10.5133 10.2464 10.9254L10.7464 15.9254C10.7876 16.3376 10.4869 16.7051 10.0747 16.7463C9.66256 16.7875 9.29503 16.4868 9.25381 16.0747L8.75381 11.0747C8.7126 10.6625 9.01331 10.295 9.42546 10.2538Z" fill="#1C274C"/>
                                            <path d="M14.5747 10.2538C14.9869 10.295 15.2876 10.6625 15.2464 11.0747L14.7464 16.0747C14.7052 16.4868 14.3376 16.7875 13.9255 16.7463C13.5133 16.7051 13.2126 16.3376 13.2538 15.9254L13.7538 10.9254C13.795 10.5133 14.1626 10.2125 14.5747 10.2538Z" fill="#1C274C"/>
                                            </svg>
                                        </a> 
                                    </div>
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