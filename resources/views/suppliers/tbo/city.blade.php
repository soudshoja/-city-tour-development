<x-app-layout>
    <style>
    </style>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <ul class="flex space-x-2 rtl:space-x-reverse text-base md:text-lg sm:text-sm py-3">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="{{ route('suppliers.index') }}" class="customBlueColor hover:underline">Suppliers List</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <a href="{{ route('suppliers.tbo.index') }}" class="customBlueColor hover:underline">TBO Holidays</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <span>City List</span>
            </li>
        </ul>
        <div class="">
            <div class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-t-lg font-semibold">
                CITY LIST
            </div>
            <hr class="dark:border-gray-200">
            <div class="p-4 rounded-b-lg bg-white dark:bg-gray-600 overflow-auto shadow-sm max-h-160 flex">
                <div class="px-2 grid grid-cols-2 xl:grid-cols-3 gap-2 w-1/2">
                    @foreach($cities as $city)
                    <button data-item-id="{{ $city['Code'] }}" class="btn p-2 bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 dark:border-gray-500 rounded-md text-center text-white w-full hover:from-gray-200 hover:to-gray-400 hover:text-black">
                        {{ $city['Name'] }}
                    </button>
                    @endforeach
                </div>
                <div class="w-1/2 text-center">
                    <div id="hotels" class="">
                        <p>Select a city to view hotels</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const cityButtons = document.querySelectorAll('.btn');
        let hotels = document.getElementById('hotels');

        cityButtons.forEach(button => {
            button.addEventListener('click', function() {
                console.log('City button clicked');
                hotels.innerHTML = '';

                const itemId = this.getAttribute('data-item-id');

                let url = "{!! route('suppliers.tbo.hotel-list', ['cityCode' => '__cityCode__']) !!}";
                url = url.replace('__cityCode__', itemId);

                const loading = document.createElement('div');
                loading.classList.add('w-full', 'flex', 'items-center', 'justify-center');
                loading.innerHTML = `
                    <svg class="animate-spin h-12 w-12 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                        </path>
                    </svg>
                `;
                hotels.appendChild(loading);
                // hotels.classList.add('flex', 'items-center', 'justify-center');
                // hotels.classList.remove('grid')
                // hotels.innerHTML = `
                //     <svg class="animate-spin h-12 w-12 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                //         viewBox="0 0 24 24">
                //         <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                //         </circle>
                //         <path class="opacity-75" fill="currentColor"
                //             d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                //         </path>
                //     </svg>
                // `;


                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        // console.log(data);
                        if (typeof data.error !== 'undefined') throw new Error(data.error);
                        hotelsData = data.Hotels;
                        hotels.innerHTML = '';


                        //create breadcrumbs
                        const breadcrumbs = document.createElement('div');
                        breadcrumbs.classList.add('flex', 'space-x-2', 'rtl:space-x-reverse', 'text-base', 'md:text-lg', 'sm:text-sm', 'py-3');
                        breadcrumbs.innerHTML = `
                            <p class="">
                                Hotels
                            </p>
                        `;
                        hotels.appendChild(breadcrumbs);

                        var hotelsList = [];

                        const hotelListContainer = document.createElement('div');
                        hotelListContainer.classList.add('grid', 'grid-cols-2', 'gap-2', 'w-full');

                        hotels.appendChild(hotelListContainer);

                        hotelsData.forEach(hotel => {
                            hotelsList.push(hotel);

                            breadcrumbs.innerHTML = `
                                <p class="">
                                    <p class=""> Hotels </p>
                                </p>
                            `;

                            let rating;

                            if (hotel.HotelRating === 'OneStar') {
                                rating = 1;
                            } else if (hotel.HotelRating === 'TwoStar') {
                                rating = 2;
                            } else if (hotel.HotelRating === 'ThreeStar') {
                                rating = 3;
                            } else if (hotel.HotelRating === 'FourStar') {
                                rating = 4;
                            } else if (hotel.HotelRating === 'All') {
                                rating = 5;
                            } else {
                                rating = 0;
                            }

                            const hotelDiv = document.createElement('div');
                            // hotelDiv.href = "{!! route('suppliers.tbo.hotel-details', ['hotelCode' => '__hotelCode__']) !!}".replace('__hotelCode__', hotel.HotelCode);
                            hotelDiv.id = 'hotel-' + hotel.HotelCode;
                            hotelDiv.className = 'rounded-md border-black border dark:border-gray-600 shadow-lg p-2 dark:bg-gray-500 cursor-pointer hover:animate-pulse';
                            hotelDiv.innerHTML = `
                                <div class="flex justify-between mb-2">
                                    <div>
                                        Hotel Id: <strong> ${hotel.HotelCode} </strong>
                                    </div>
                                    <div class="bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 p-2 text-white rounded-md">${hotel.HotelName}</div>
                                </div>
                                <div class="grid grid-cols-1 text-start">
                                    <div class="inline-flex gap-2">
                                        <strong>Hotel Name:</strong> ${hotel.HotelName}
                                    </div>
                                    <div class="inline-flex gap-2">
                                        <strong>Hotel Rating:</strong>
                                        <div class="rating-container">
                                        </div>
                                    </div>
                                </div>
                            `;
                            hotelListContainer.appendChild(hotelDiv);

                            const ratingContainer = hotelDiv.querySelector('.rating-container');

                            for (let i = 0; i < 5; i++) {
                                const star = document.createElement('span');
                                if (i < rating) {
                                    star.innerHTML = '&#9733;'; // Filled star
                                } else {
                                    star.innerHTML = '&#9734;'; // Empty star
                                }
                                ratingContainer.appendChild(star);
                            }

                            hotelDiv.addEventListener('click', function() {
                                let url = "{!! route('suppliers.tbo.hotel-details', ['hotelCode' => '__hotelCode__']) !!}".replace('__hotelCode__', hotel.HotelCode);
                                fetch(url)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (typeof data.error !== 'undefined') throw new Error(data.error);
                                        if (data.Status.Code !== 200) throw new Error(data.Status.Description);

                                        hotelData = data.HotelDetails[0];
                                        console.log(data);

                                        breadcrumbs.innerHTML = `
                                            <p class="">
                                                <span class="cursor-pointer hover:underline customBlueColor" id="hotel-list">Hotels</span>
                                            </p>
                                            <p class="before:content-['/'] before:mr-1">
                                                <span>${hotelData.HotelName}</span>
                                            </p>
                                        `;

                                        // Add click event to go back to hotels list
                                        document.getElementById('hotel-list').addEventListener('click', function() {
                                            location.reload(); // Simple way to go back, or rebuild the hotels list
                                        });

                                        hotelListContainer.innerHTML = '';
                                        hotelListContainer.classList.remove('grid', 'grid-cols-2', 'gap-2');
                                        hotelListContainer.classList.add('flex', 'flex-col', 'w-full', 'space-y-4');

                                        // Display hotel details
                                        const hotelDetailsDiv = document.createElement('div');
                                        hotelDetailsDiv.className = 'bg-white dark:bg-gray-700 rounded-lg shadow p-4';

                                        // Hotel Name as header
                                        hotelDetailsDiv.innerHTML = `
                                            <h2 class="text-2xl font-bold mb-4">${hotelData.HotelName}</h2>
                                        `;

                                        // Single Image
                                        if (hotelData.Image && typeof hotelData.Image === 'string') {
                                            hotelDetailsDiv.innerHTML += `
                                                <div class="mt-4">
                                                    <div class="rounded overflow-hidden shadow w-full md:w-1/2">
                                                        <img src="${hotelData.Image}" alt="${hotelData.HotelName}" class="w-full h-64 object-cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23ddd\' width=\'100\' height=\'100\'/%3E%3Ctext fill=\'%23999\' x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\'%3ENo Image%3C/text%3E%3C/svg%3E'">
                                                    </div>
                                                </div>
                                            `;
                                        }



                                        // Description
                                        if (hotelData.Description) {
                                            hotelDetailsDiv.innerHTML += `
                                                <div class="mb-4">
                                                    <h3 class="font-semibold text-lg mb-2">Description:</h3>
                                                    <p class="text-gray-700 dark:text-gray-300">${hotelData.Description}</p>
                                                </div>
                                            `;
                                        }

                                        // Other details
                                        const excludeKeys = ['HotelName', 'Description', 'HotelFacilities', 'Attractions', 'Images'];
                                        Object.entries(hotelData).forEach(([key, value]) => {
                                            if (!excludeKeys.includes(key) && value !== null && value !== '') {
                                                hotelDetailsDiv.innerHTML += `
                                                    <div class="mb-2">
                                                        <span class="font-semibold">${key}:</span>
                                                        <span class="ml-2">${typeof value === 'object' ? JSON.stringify(value) : value}</span>
                                                    </div>
                                                `;
                                            }
                                        });
                                        // Hotel Fees
                                        if (hotelData.HotelFees) {
                                            const fees = typeof hotelData.HotelFees === 'string' ? JSON.parse(hotelData.HotelFees) : hotelData.HotelFees;

                                            hotelDetailsDiv.innerHTML += `
                                                <div class="mt-4">
                                                    <h3 class="font-semibold text-lg mb-2">Hotel Fees:</h3>
                                                    <div class="bg-gray-50 dark:bg-gray-600 rounded p-3">
                                                        ${fees.Optional && fees.Optional.length > 0 ? `
                                                            <div class="mb-3">
                                                                <h4 class="font-semibold mb-2">Optional Fees:</h4>
                                                                <div class="grid grid-cols-1 gap-2">
                                                                    ${fees.Optional.map(fee => `
                                                                        <div class="text-sm bg-white dark:bg-gray-700 p-2 rounded">
                                                                            ${typeof fee === 'object' ? JSON.stringify(fee) : fee}
                                                                        </div>
                                                                    `).join('')}
                                                                </div>
                                                            </div>
                                                        ` : '<div class="text-sm text-gray-600 dark:text-gray-400 mb-2">No Optional Fees</div>'}
                                                        
                                                        ${fees.Mandatory && fees.Mandatory.length > 0 ? `
                                                            <div>
                                                                <h4 class="font-semibold mb-2">Mandatory Fees:</h4>
                                                                <div class="grid grid-cols-1 gap-2">
                                                                    ${fees.Mandatory.map(fee => `
                                                                        <div class="text-sm bg-white dark:bg-gray-700 p-2 rounded">
                                                                            ${typeof fee === 'object' ? JSON.stringify(fee) : fee}
                                                                        </div>
                                                                    `).join('')}
                                                                </div>
                                                            </div>
                                                        ` : '<div class="text-sm text-gray-600 dark:text-gray-400">No Mandatory Fees</div>'}
                                                    </div>
                                                </div>
                                            `;
                                        }
                                        // Hotel Facilities
                                        if (hotelData.HotelFacilities && hotelData.HotelFacilities.length > 0) {
                                            hotelDetailsDiv.innerHTML += `
                                                <div class="mt-4">
                                                    <h3 class="font-semibold text-lg mb-2">Facilities:</h3>
                                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                        ${hotelData.HotelFacilities.map(facility => `<div class="text-sm">• ${facility}</div>`).join('')}
                                                    </div>
                                                </div>
                                            `;
                                        }

                                        hotelListContainer.appendChild(hotelDetailsDiv);


                                    })
                                    .catch(error => {
                                        hotelListContainer.innerHTML = `
                                            <div class="text-center w-full font-bold text-red-600 dark:text-red-400 p-4 bg-red-50 dark:bg-red-900/20 rounded">
                                                Error: ${error.message}
                                            </div>
                                        `;

                                        hotelDiv.classList.remove('animate-pulse');
                                    });


                            });



                        });

                    })
                    .catch(error => {
                        // alert(error);
                        console.log(error);
                        hotels.innerHTML = `
                            <div class="text-center w-full font-bold">
                                No Hotels Found For This City
                            </div>
                        `;

                        //clear hotel list; don't need too, but just to be sure
                        hotelsList = [];
                    })
                    .finally(() => {
                        // Hide loading indicator
                    });

            });
        });
    </script>
</x-app-layout>