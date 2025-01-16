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
        <div class="mb-4">
            <label for="title" class="block">Title</label>
            <select id="title" name="title" class="w-full border rounded-md p-2">
                <option value="Mr" {{ old('title') == 'Mr' ? 'selected' : '' }}>Mr</option>
                <option value="Mrs" {{ old('title') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                <option value="Ms" {{ old('title') == 'Ms' ? 'selected' : '' }}>Ms</option>
            </select>
        </div>
        <div class="mb-4">
            <label for="firstName" class="block">First Name</label>
            <input type="text" id="firstName" name="first_name" value="{{ old('first_name') }}" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="lastName" class="block">Last Name</label>
            <input type="text" id="lastName" name="last_name" value="{{ old('last_name') }}" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="type" class="block">Type</label>
            <select id="type" name="type" class="w-full border rounded-md p-2">
                <option value="Adult" {{ old('type') == 'Adult' ? 'selected' : '' }}>Adult</option>
                <option value="Child" {{ old('type') == 'Child' ? 'selected' : '' }}>Child</option>
            </select>
        </div>
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
            <input type="number" step="0.01" id="totalFare" name="total_fare" value="{{ old('total_fare') }}" class="w-full border rounded-md p-2">
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
            <input type="number" step="0.01" id="billingAmount" name="billing_amount" value="{{ old('billing_amount') }}" class="w-full border rounded-md p-2">
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