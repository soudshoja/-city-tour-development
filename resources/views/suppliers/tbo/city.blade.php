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
                        if (typeof data.error !== 'undefined') throw new Error(data.error);
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

                        data.forEach(hotel => {
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

                                        breadcrumbs.innerHTML = `
                                            <p class="">
                                                <p class="" id="hotel-list">Hotels</p>
                                            </p>
                                            <p class="before:content-['/'] before:mr-1">
                                                <p class=""> ${data[0].HotelName} </p>
                                            </p>
                                        `;

                                        hotelListContainer.innerHTML = '';
                                        hotelListContainer.classList.remove('grid', 'grid-cols-2', 'gap-2', 'w-full');
                                        hotelListContainer.classList.add('flex', 'flex-col', 'w-full', 'p-2');

                                        data.forEach(hotelDetails => {
                                            const hotelDetailsDiv = document.createElement('div');
                                            Object.entries(hotelDetails).forEach(([key, value]) => {
                                                hotelDetailsDiv.innerHTML += `
                                                    <div class="grid grid-cols-1 text-start">
                                                        <div class="inline-flex gap-2">
                                                            <strong>${key}:</strong> ${value}
                                                        </div>
                                                    </div>
                                                `;
                                            });
                                            hotelListContainer.appendChild(hotelDetailsDiv);
                                        })


                                    })
                                    .catch(error => {
                                        alert(error);
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