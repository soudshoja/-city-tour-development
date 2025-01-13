<x-app-layout>
    <style>
        #search-body>div,
        #search-body>div>div {
            width: 100%;
        }
    </style>
    <div id="search-header" class="bg-white font-semibold p-2 my-2 rounded-md text-center">
        Search Hotels
    </div>
    <div id="search-body" class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-lg font-semibold">
        <div class="flex justify-evenly gap-4">
            <div class="flex flex-col gap-2">
                <label for="checkInDate">Check In</label>
                <input type="date" id="checkInDate">
                <label for="checkOutDate">Check Out</label>
                <input type="date" id="checkOutDate">
            </div>
            <div class="flex flex-col gap-2 w-full">
                <div class="flex flex-col gap-2">
                    <label for="country">Country</label>
                    <select name="country" id="country" class="h-12 p-2">
                        <option value="">Select Country</option>
                        @foreach($countryList as $country)
                        <option value="{{ $country['Code'] }}">{{ $country['Name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="city">
                        City
                    </label>
                    @if(count($cityList) === 0)
                    <div>
                        Please select a country
                    </div>
                    @else
                    <select name="city" id="city" class="h-12 p-2">
                        @foreach($cityList as $city)
                        <option value="{{ $city['Code'] }}">{{ $city['Name'] }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
                <div class="flex flex-col gap-2">
                    <label for="hotel">Hotel</label>
                    @if(count($hotelList) === 0)
                    <div>
                        No hotels found
                    </div>
                    @else
                    <select name="hotel" id="hotel" class="h-12 p-2">
                        @foreach($hotelList as $hotel)
                        <option value="{{ $hotel['HotelCode'] }}">{{ $hotel['HotelName'] }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
            </div>
            <div id="pax-of-rooms" class="flex flex-col gap-2">
                <label for="guestNationality">Guest Nationality</label>
                <select name="guestNationality" id="guestNationality" class="h-12 p-2">
                    @foreach($countryList as $country)
                    <option value="{{ $country['Code'] }}">{{ $country['Name'] }}</option>
                    @endforeach
                </select>
                <label for="rooms">Rooms</label>
                <input type="number" name="rooms" id="rooms">
                <label for="adults">Adults</label>
                <input type="number" class="" name="adults" id="adults">
                <label for="children">Children</label>
                <input type="number" class="" name="children" id="children" placeholder="Enter Children Amount And Press Enter">
                <div id="children-list-age" class="grid gap-2">
                </div>
            </div>
        </div>
        <div class="bg-blue-500 text-white font-semibold p-2 text-center rounded-md cursor-pointer shadow-md" id="search-button">
            Submit Search
        </div>
        <div id="search-result">
        </div>
    </div>
    <script>
        const country = document.getElementById('country');

        country.addEventListener('change', async (e) => {
            const country = e.target.value;
            const url = new URL(window.location.href);
            url.searchParams.set('countryCode', country);
            window.location.href = url.toString();
        });

        const city = document.getElementById('city');

        city.addEventListener('change', async (e) => {
            const city = e.target.value;
            const url = new URL(window.location.href);
            url.searchParams.set('cityCode', city);
            window.location.href = url.toString();
        });

        const children = document.getElementById('children');

        children.addEventListener('change', async (e) => {
            const children = e.target.value;
            const childrenListAge = document.getElementById('children-list-age');
            childrenListAge.innerHTML = `
            <div> Age of Children </div>
            `;
            for (let i = 0; i < children; i++) {
                const input = document.createElement('input');
                input.type = 'number';
                input.name = `childAge${i + 1}`;
                input.placeholder = `Child ${i + 1} Age`;
                childrenListAge.appendChild(input);
            }
        });

        const searchButton = document.getElementById('search-button');
        console.log('search button: ' + searchButton);

        searchButton.addEventListener('click', async () => {
            const checkInDate = document.getElementById('checkInDate').value;
            const checkOutDate = document.getElementById('checkOutDate').value;
            const hotel = document.getElementById('hotel').value;
            const guestNationality = document.getElementById('guestNationality').value;
            // const rooms = document.getElementById('rooms').value;
            // const adults = document.getElementById('adults').value;
            // const children = document.getElementById('children').value;
            // const childrenListAge = document.getElementById('children-list-age');
            // const childrenAge = [];
            // for (let i = 0; i < children; i++) {
            //     const childAge = document.querySelector(`input[name=childAge${i + 1}]`).value;
            //     childrenAge.push(childAge);
            // }

            if (!checkInDate || !checkOutDate) {
                alert('Please select check in and check out date');
                return;
            }

            const url = "{!! route('suppliers.tbo.search') !!}";

            console.log('url:' + url);

            const data = {
                checkInDate,
                checkOutDate,
                hotel,
                guestNationality,
            };
            console.log('request data: ' + JSON.stringify(data));
            const searchResult = document.getElementById('search-result');

            searchResult.innerHTML = 'Loading...';

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data)
                    if (data.Status.Code !== 200) {
                        searchResult.innerHTML = '';
                        alert(data.Status.Description);
                        return;
                    }

                    searchResult.innerHTML = '';

                    const hotels = data.HotelResult;
                    hotels.forEach(hotel => {
                        hotel.Rooms.forEach(room => {
                            const roomDiv = document.createElement('div');
                            roomDiv.classList.add('p-4', 'border', 'rounded', 'mb-4', 'cursor-pointer');
                            roomDiv.innerHTML = `
                                <form action="{{ route('suppliers.tbo.prebook.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="bookingCode" value="${room.BookingCode}">
                                    <input type="hidden" name="totalFare" value="${room.TotalFare}">
                                    <input type="hidden" name="totalTax" value="${room.TotalTax}">
                                    <input type="hidden" name="mealType" value="${room.MealType}">
                                    <input type="hidden" name="isRefundable" value="${room.IsRefundable}">
                                    <input type="hidden" name="roomPromotion" value="${room.RoomPromotion}">
                                    <input type="hidden" name="inclusion" value="${room.Inclusion}">
                                    <input type="hidden" name="name" value="${room.Name}">
                                    <input type="hidden" name="currency" value="${hotel.Currency}">
                                    <div class="font-bold">${room.Name.join(', ')}</div>
                                    <div>Inclusion: ${room.Inclusion}</div>
                                    <div>Total Fare: ${room.TotalFare} ${hotel.Currency}</div>
                                    <div>Total Tax: ${room.TotalTax} ${hotel.Currency}</div>
                                    <div>Meal Type: ${room.MealType}</div>
                                    <div>Refundable: ${room.IsRefundable ? 'Yes' : 'No'}</div>
                                    <div>Room Promotion: ${room.RoomPromotion.join(', ')}</div>
                                    <button type="submit" class="bg-black text-white font-semibold p-2 text-center rounded-md cursor-pointer shadow-md">
                                        Book Now
                                    </button>
                                </form>
                            `;
                            searchResult.appendChild(roomDiv);
                        });
                    });

                })
                .catch((error) => {
                    searchResult.innerHTML = '';
                    alert('Error: ' + error);
                });
        });
    </script>
</x-app-layout>