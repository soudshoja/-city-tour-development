<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 px-5 text-base md:text-lg sm:text-sm">
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
            <a href="{{ route('suppliers.tbo.prebook.index') }}" class="customBlueColor hover:underline">Prebook</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>Prebook for {{ $tboPreBook->booking_code }}</span>
        </li>
    </ul>
    <div class="bg-white rounded-md p-4 mb-4">
        Fill out the necessary information to proceed with the booking
    </div>
    <form method="POST" action="{{route('suppliers.tbo.book') }}" class="bg-white rounded-md p-4">
        @csrf
        <input type="hidden" name="tbo_id" value="{{ $tboPreBook->id }}">
        <input type="hidden" name="booking_code" value="{{ $tboPreBook->booking_code }}">
    
        @foreach($tboPreBook->rooms as $roomKey => $room)
        <div class="text-lg font-bold p-2 my-2" > Rooms {{ $loop->iteration }} </div>
        <hr>
        @if($room->adult_quantity >0)
        <div class="p-2 my-2">
            <h2 class="text-lg font-bold">Adults</h2>
            <hr>
            @for($i = 0; $i < $room->adult_quantity; $i++)
                <div class="border border-gray-600 rounded-md p-2 my-2">
                    <h3 class="text-base font-bold">Adult {{ $i + 1 }}</h3>
                    <div class="mb-4">
                        <label for="title" class="block">Title</label>
                        <select name="rooms[{{ $roomKey }}][adults][{{ $i }}][title]" class="w-full border rounded-md p-2">
                            <option value="Mr" {{ old('rooms.'.$roomKey.'.adults.'.$i.'.title') == 'Mr' ? 'selected' : '' }}>Mr</option>
                            <option value="Mrs" {{ old('rooms.'.$roomKey.'.adults.'.$i.'.title') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                            <option value="Ms" {{ old('rooms.'.$roomKey.'.adults.'.$i.'.title') == 'Ms' ? 'selected' : '' }}>Ms</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="firstName" class="block">First Name</label>
                        <input type="text" id="firstName" name="rooms[{{ $roomKey }}][adults][{{ $i }}][first_name]" value="{{ old('rooms.'.$roomKey.'.adults.'.$i.'.first_name') }}" class="w-full border rounded-md p-2">
                    </div>
                    <div class="mb-4">
                        <label for="lastName" class="block">Last Name</label>
                        <input type="text" id="lastName" name="rooms[{{ $roomKey }}][adults][{{ $i }}][last_name]" value="{{ old('rooms.'.$roomKey.'.adults.'.$i.'.last_name') }}" class="w-full border rounded-md p-2">
                    </div>
                </div>
            @endfor
        </div>
        @endif
        @if($room->child_quantity > 0)
        <div class="p-2 my-2">
            <h2 class="text-lg font-bold">Children</h2>
            @for($i = 0; $i < $room->child_quantity; $i++)
                <div class="border border-gray-600 rounded-md p-2 my-2">
                    <h3 class="text-base font-bold">Child {{ $i + 1 }}</h3>
                    <div class="mb-4">
                        <label for="title" class="block">Title</label>
                        <select id="title" name="rooms[{{ $roomKey }}][children][{{ $i }}][title]" class="w-full border rounded-md p-2">
                            <option value="Mr" {{ old('rooms.'.$roomKey.'.children.'.$i.'.title') == 'Mr' ? 'selected' : '' }}>Mr</option>
                            <option value="Mrs" {{ old('rooms.'.$roomKey.'.children.'.$i.'.title') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                            <option value="Ms" {{ old('rooms.'.$roomKey.'.children.'.$i.'.title') == 'Ms' ? 'selected' : '' }}>Ms</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="firstName" class="block">First Name</label>
                        <input type="text" id="firstName" name="rooms[{{ $roomKey }}][children][{{ $i }}][first_name]" value="{{ old('rooms.'.$roomKey.'.children.'.$i.'.first_name') }}" class="w-full border rounded-md p-2">
                    </div>
                    <div class="mb-4">
                        <label for="lastName" class="block">Last Name</label>
                        <input type="text" id="lastName" name="rooms[{{ $roomKey }}][children][{{ $i }}][last_name]" value="{{ old('rooms.'.$roomKey.'.children.'.$i.'.last_name') }}" class="w-full border rounded-md p-2">
                    </div>
                </div>
            @endfor
        @endif

        @endforeach

            <div class="mb-4">
                <label for="clientReferenceId" class="block">Client Reference ID</label>
                <input type="text" id="clientReferenceId" name="client_reference_id" value="{{ old('client_reference_id') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="bookingReferenceId" class="block">Booking Reference ID</label>
                <input type="text" id="bookingReferenceId" name="booking_reference_id" value="{{ old('booking_reference_id') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="totalFare" class="block">Total Fare</label>
                <input type="number" step="0.01" id="totalFare" name="total_fare" value="{{ $tboPreBook->total_fare }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="emailId" class="block">Email ID</label>
                <input type="email" id="emailId" name="email_id" value="{{ old('email_id') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="phoneNumber" class="block">Phone Number</label>
                <input type="tel" id="phoneNumber" name="phone_number" value="{{ old('phone_number') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="bookingType" class="block">Booking Type</label>
                <input type="text" id="bookingType" name="booking_type" value="Voucher" readonly class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="paymentMode" class="block">Payment Mode</label>
                <select id="paymentMode" name="payment_mode" class="w-full border rounded-md p-2">
                    <option value="Limit" {{ old('payment_mode') == 'Limit' ? 'selected' : '' }}>Limit</option>
                    <option value="SavedCard" {{ old('payment_mode') == 'SavedCard' ? 'selected' : '' }}>SavedCard</option>
                    <option value="NewCard" {{ old('payment_mode') == 'NewCard' ? 'selected' : '' }}>NewCard</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="cvvNumber" class="block">CVV Number</label>
                <input type="text" id="cvvNumber" name="cvv" value="{{ old('cvv') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardNumber" class="block">Card Number</label>
                <input type="text" id="cardNumber" name="card_number" value="{{ old('card_number') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardExpirationMonth" class="block">Card Expiration Month</label>
                <input type="text" id="cardExpirationMonth" name="expired_month" value="{{ old('expired_month') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardExpirationYear" class="block">Card Expiration Year</label>
                <input type="text" id="cardExpirationYear" name="expired_year" value="{{ old('expired_year') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardHolderFirstName" class="block">Card Holder First Name</label>
                <input type="text" id="cardHolderFirstName" name="card_first_name" value="{{ old('card_first_name') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardHolderLastName" class="block">Card Holder Last Name</label>
                <input type="text" id="cardHolderLastName" name="card_last_name" value="{{ old('card_last_name') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="billingAmount" class="block">Billing Amount</label>
                <input type="number" step="0.01" id="billingAmount" name="billing_amount" value="{{ $tboPreBook->total_fare }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="billingCurrency" class="block">Billing Currency</label>
                <input type="text" id="billingCurrency" name="billing_currency" value="{{ old('billing_currency') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="addressLine1" class="block">Address Line 1</label>
                <input type="text" id="addressLine1" name="address_line_1" value="{{ old('address_line_1') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="addressLine2" class="block">Address Line 2</label>
                <input type="text" id="addressLine2" name="address_line_2" value="{{ old('address_line_2') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="city" class="block">City</label>
                <input type="text" id="city" name="card_city" value="{{ old('card_city') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="postalCode" class="block">Postal Code</label>
                <input type="text" id="postalCode" name="card_postal_code" value="{{ old('card_postal_code') }}" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="countryCode" class="block">Country Code</label>
                <input type="text" id="countryCode" name="card_country_code" value="{{ old('card_country_code') }}" class="w-full border rounded-md p-2">
            </div>
            <div>
                <button type="submit" class="bg-blue-500 text-white rounded-md p-2">Submit</button>
            </div>
            <div class="w-full fixed left-0 bottom-0 bg-white p-4 text-center shadow-lg border-t border-gray-200">
                <button type="submit" class="bg-black text-white rounded-md p-2 w-80">Book</button>
            </div>
    </form>
</x-app-layout>