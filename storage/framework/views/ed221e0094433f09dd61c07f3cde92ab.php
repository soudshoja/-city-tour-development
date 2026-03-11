<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 px-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="<?php echo e(route('dashboard')); ?>" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <a href="<?php echo e(route('suppliers.index')); ?>" class="customBlueColor hover:underline">Suppliers List</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <a href="<?php echo e(route('suppliers.tbo.index')); ?>" class="customBlueColor hover:underline">TBO Holidays</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>Search Rooms</span>
        </li>
    </ul>
    <?php if(session('tbo.url') == env('TBO_URL')): ?>
    <div class="w-full bg-red-200 text-red-500 p-2 rounded-md mb-2">
        Careful!!! You Are You Using Live Credentials !
    </div>
    <?php endif; ?>
    <div id="search-header" class="bg-white dark:bg-gradient-to-r dark:from-gray-800 dark:to-gray-500 font-semibold p-2 my-2 rounded-md text-center">
        Search Hotels
    </div>
    <div id="search-body" class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-lg font-semibold">
        <div class="flex justify-evenly gap-4">
            <div class="flex flex-col gap-2">
                <label for="checkInDate">Check In</label>
                <input type="date" id="checkInDate" class="dark:bg-gray-800 dark:border-gray-800" value="<?php echo e(old('checkInDate')); ?>">
                <label for="checkOutDate">Check Out</label>
                <input type="date" id="checkOutDate" class="dark:bg-gray-800 dark:border-gray-900" value="<?php echo e(old('checkOutDate')); ?>">
            </div>
            <div class="flex flex-col gap-2 max-w-120">
                <div class="flex flex-col gap-2">
                    <label for="country">Country</label>
                    <select name="country" id="country" class="h-12 p-2 dark:bg-gray-800 dark:border-gray-900">
                        <option value="">Select Country</option>
                        <?php $__currentLoopData = $countryList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($country['Code']); ?>" <?php echo e($country['Code'] === $countryCode ? 'selected' : ''); ?>>
                            <?php echo e($country['Name']); ?>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="city">
                        City
                    </label>
                    <?php if(count($cityList) === 0): ?>
                    <div>
                        Please select a country
                    </div>
                    <?php else: ?>
                    <select name="city" id="city" class="h-12 p-2 dark:bg-gray-800 dark:border-gray-900">
                        <option value="">Select City</option>
                        <?php $__currentLoopData = $cityList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $city): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($city['Code']); ?>" <?php echo e($city['Code'] === $cityCode ? 'selected' : ''); ?>><?php echo e($city['Name']); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="hotel">Hotel</label>
                    <?php if(count($hotelList) === 0): ?>
                    <div>
                        No hotels found
                    </div>
                    <?php else: ?>
                    <select name="hotel" id="hotel" class="h-12 p-2 dark:bg-gray-800 dark:border-gray-900">
                        <option value="">Select Hotel</option>
                        <?php $__currentLoopData = $hotelList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hotel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($hotel['HotelCode']); ?>" <?php echo e($hotel['HotelCode'] === old('hotelCode') ? 'selected' : ''); ?>

                        ><?php echo e($hotel['HotelName']); ?> - <?php echo e($hotel['HotelCode']); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php endif; ?>
                </div>
            </div>
            <div id="pax-of-rooms" class="flex flex-col gap-2">
                <label for="guestNationality">Guest Nationality</label>
                <select name="guestNationality" id="guestNationality" class="h-12 p-2 dark:bg-gray-800 dark:border-gray-900">
                    <?php $__currentLoopData = $countryList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($country['Code']); ?>"><?php echo e($country['Name']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </div>
        <div id="room-container" class="grid gap-2 p-2">
            <button class="bg-blue-500 dark:bg-blue-700 text-white font-semibold p-2 text-center rounded-md cursor-pointer shadow-md" onclick="addRoom()">
                Add Room
            </button>
            <div id="room-list" class="grid grid-cols-2"></div>
        </div>
        <div class="bg-blue-500 dark:bg-blue-700 text-white font-semibold p-2 text-center rounded-md cursor-pointer shadow-md" id="search-button">
            Submit Search
        </div>
        <div id="search-result" class="mt-2">
        </div>
    </div>
    <script>
        const country = document.getElementById('country');

        const roomListDiv = document.getElementById('room-list');
        const roomContainerDiv = document.getElementById('room-container');

        var roomCount = 1;

        roomContainerDiv.append(roomContainer(roomCount));

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

        const searchButton = document.getElementById('search-button');

        searchButton.addEventListener('click', async () => {
            const checkInDate = document.getElementById('checkInDate').value;
            const checkOutDate = document.getElementById('checkOutDate').value;
            const hotel = document.getElementById('hotel').value;
            const hotelName = document.getElementById('hotel').options[document.getElementById('hotel').selectedIndex].text;

            const guestNationality = document.getElementById('guestNationality').value;

            if (!hotel) {
                alert('Please select a hotel');
                return;
            }

            if (!checkInDate || !checkOutDate) {
                alert('Please fill the correct date');
                return;
            }

            const rooms = [];

            let adultQuantity = 0;
            let childrenQuantity = 0;

            for (let i = 1; i <= roomCount; i++) {
                const adults = document.getElementById('room' + i + '-adults').value;
                const children = document.getElementsByClassName('children-for-room' + i);
                const childrenArray = [];

                for (let j = 0; j < children.length; j++) {
                    childrenArray.push(children[j].value);
                }
                rooms.push({
                    adults,
                    children: childrenArray.length,
                    childrenAges: childrenArray
                });


            }

            const url = "<?php echo route('suppliers.tbo.search'); ?>";

            const data = {
                checkInDate,
                checkOutDate,
                hotel,
                guestNationality,
                rooms
            };

            console.log(data);

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

                    if (data.Status.Code !== 200) {
                        searchResult.innerHTML = '';
                        alert(data.Status.Description);
                        return;
                    }

                    searchResult.innerHTML = '';

                    const hotels = data.HotelResult;
                    hotels.forEach(hotel => {
                        console.log('hotel: ', hotel);
                        hotel.Rooms.forEach(room => {
                            console.log(room);
                            const roomResultDiv = document.createElement('div');
                            roomResultDiv.classList.add('p-4', 'border', 'rounded', 'mb-4', 'cursor-pointer');

                            let form = document.createElement('form');
                            form.action = "<?php echo e(route('suppliers.tbo.prebook.store')); ?>";
                            form.method = "POST";
                            form.classList.add('flex', 'justify-between');

                            form.innerHTML += `<?php echo csrf_field(); ?>`;

                            for (let i = 0; i < rooms.length; i++) {
                                let adultInput = document.createElement('input');
                                adultInput.type = 'hidden';
                                adultInput.name = 'rooms[' + i + '][adults]';
                                adultInput.value = rooms[i].adults;

                                form.appendChild(adultInput);

                                let childrenInput = document.createElement('input');
                                childrenInput.type = 'hidden';
                                childrenInput.name = 'rooms[' + i + '][children]';
                                childrenInput.value = rooms[i].children;

                                form.appendChild(childrenInput);
                            }

                            form.innerHTML += `
                                <input type="hidden" name="checkInDate" value="${checkInDate}">
                                <input type="hidden" name="checkOutDate" value="${checkOutDate}">
                                <input type="hidden" name="hotelCode" value="${hotel.HotelCode}">
                                <input type="hidden" name="hotelName" value="${hotelName}">
                                <input type="hidden" name="bookingCode" value="${room.BookingCode}">
                                <input type="hidden" name="totalFare" value="${room.TotalFare}">
                                <input type="hidden" name="totalTax" value="${room.TotalTax}">
                                <input type="hidden" name="mealType" value="${room.MealType}">
                                <input type="hidden" name="isRefundable" value="${room.IsRefundable}">
                                <input type="hidden" name="roomPromotion" value="${room.RoomPromotion}">
                                <input type="hidden" name="inclusion" value="${room.Inclusion}">
                                <input type="hidden" name="name" value="${room.Name}">
                                <input type="hidden" name="currency" value="${hotel.Currency}">
                                <div>
                                <div class="font-bold">${room.Name.join(', ')}</div>
                                <div>Inclusion: ${room.Inclusion}</div>
                                <div>Total Fare: ${room.TotalFare} ${hotel.Currency}</div>
                                <div>Total Tax: ${room.TotalTax} ${hotel.Currency}</div>
                                <div>Meal Type: ${room.MealType}</div>
                                <div>Refundable: ${room.IsRefundable ? 'Yes' : 'No'}</div>
                                </div>
                            `;

                            if (room.RoomPromotion && room.RoomPromotion.length > 0) {
                                form.innerHTML += `
                                  <div>Room Promotion: ${room.RoomPromotion.join(', ')}</div>
                                `;
                            }

                            if (room.Supplements && room.Supplements.length > 0) {
                                form.innerHTML += `
                                  <div>Supplements:</div>
                                `;

                                room.Supplements.forEach(supplement => {
                                    supplement.forEach(sup => {
                                        form.innerHTML += `
                                            <div>${sup.Description}</div>
                                        `;
                                    });
                                });
                            }

                            form.innerHTML += `
                                <button type="submit" class="bg-black text-white font-semibold p-2 text-center rounded-md cursor-pointer shadow-md">
                                    Book Now
                                </button>
                            `;
                            roomResultDiv.appendChild(form);
                            searchResult.appendChild(roomResultDiv);
                        });
                    });

                })
                .catch((error) => {
                    searchResult.innerHTML = '';
                    alert('Error: ' + error);
                });
        });

        function roomContainer(roomCount) {

            let tempDiv = document.createElement('div');
            tempDiv.id = 'room' + roomCount;

            tempDiv.innerHTML = `
            <div class="p-4 border rounded mb-4">
                <div class="flex justify-between">
                    <div class="font-bold">Room ${roomCount}</div>
                    <button class="font-bold p-2 bg-red-500 rounded-md text-center text-white dark:bg-red-700" onclick="removeRoom(room${roomCount})">Remove Room</button>
                </div>
                <div class="flex justify-evenly">
                    <div>
                        <label for="adults">Adult Quantity</label>
                        <input type="number" name="rooms[${roomCount}][adults]" id="room${roomCount}-adults" class="dark:bg-gray-800 dark:border-gray-900">
                    </div>
                    <div class="grid">
                        <div class="flex justify-between mb-2 min-w-56">
                            <label for="children" class="mt-2">Children</label> 
                            <button class="font-bold p-2 bg-gray-300 dark:bg-gradient-to-r dark:from-black dark:to-gray-700 rounded-md text-center" onclick="addChildren(${roomCount})">Add Child</button>
                        </div>
                        <div class="grid gap-2 min-w-40" id="children-container-room${roomCount}">
                        </div>
                    </div>
                </div>
            </div>
            `;

            return tempDiv;

        }

        function addRoom() {
            roomCount++;
            roomContainerDiv.append(roomContainer(roomCount));
        }

        function addChildren(roomCount) {
            const childrenDiv = document.getElementById('children-container-room' + roomCount);

            let childrenList = document.createElement('div');
            childrenList.innerHTML = `
            <div class="flex gap-2">
                <input type="number" name="children[]" class="children-for-room${roomCount} dark:bg-gray-800 dark:border-gray-900" placeholder="Age">
                <button class="font-bold p-2 bg-red-500 dark:bg-red-700 rounded-md text-center text-white" onclick="deleteChildDiv(this)">Remove</button>
            </div>
            `;

            childrenDiv.appendChild(childrenList);
        }

        function deleteChildDiv(element) {
            element.parentElement.remove();
        }

        function removeRoom(roomId) {
            roomId.remove();
            roomCount--;
        }
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/tbo/book/index.blade.php ENDPATH**/ ?>