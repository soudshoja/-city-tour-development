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

            const url = "{!! route('suppliers.tbo.search') !!}";
            console.log(url);

            const data = {
                checkInDate,
                checkOutDate,
                hotel,
                guestNationality,
            };

            console.log(data);

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
                    console.log(data);
                    const searchResult = document.getElementById('search-result');
                    searchResult.innerHTML = '';
                    data.forEach((hotel) => {
                    });
                })
        });
    </script>
</x-app-layout>