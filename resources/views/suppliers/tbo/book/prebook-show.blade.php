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
        <input type="hidden" name="tboId" value="{{ $tboPreBook->id }}">
        <input type="hidden" name="BookingCode" value="{{ $tboPreBook->booking_code }}">
        <div class="mb-4">
            <label for="title" class="block">Title</label>
            <select id="title" name="CustomerDetails[CustomerNames][Title]" class="w-full border rounded-md p-2">
                <option value="Mr">Mr</option>
                <option value="Mrs">Mrs</option>
                <option value="Ms">Ms</option>
            </select>
        </div>
        <div class="mb-4">
            <label for="firstName" class="block">First Name</label>
            <input type="text" id="firstName" name="CustomerDetails[CustomerNames][FirstName]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="lastName" class="block">Last Name</label>
            <input type="text" id="lastName" name="CustomerDetails[CustomerNames][LastName]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="type" class="block">Type</label>
            <select id="type" name="CustomerDetails[CustomerNames][Type]" class="w-full border rounded-md p-2">
                <option value="Adult">Adult</option>
                <option value="Child">Child</option>
            </select>
        </div>
        <div class="mb-4">
            <label for="clientReferenceId" class="block">Client Reference ID</label>
            <input type="text" id="clientReferenceId" name="ClientReferenceId" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="bookingReferenceId" class="block">Booking Reference ID</label>
            <input type="text" id="bookingReferenceId" name="BookingReferenceId" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="totalFare" class="block">Total Fare</label>
            <input type="number" step="0.01" id="totalFare" name="TotalFare" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="emailId" class="block">Email ID</label>
            <input type="email" id="emailId" name="EmailId" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="phoneNumber" class="block">Phone Number</label>
            <input type="tel" id="phoneNumber" name="PhoneNumber" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="bookingType" class="block">Booking Type</label>
            <input type="text" id="bookingType" name="BookingType" value="Voucher" readonly class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="paymentMode" class="block">Payment Mode</label>
            <select id="paymentMode" name="PaymentMode" class="w-full border rounded-md p-2">
                <option value="Limit">Limit</option>
                <option value="SavedCard">SavedCard</option>
                <option value="NewCard">NewCard</option>
            </select>
        </div>
        <div class="mb-4">
            <label for="cvvNumber" class="block">CVV Number</label>
            <input type="text" id="cvvNumber" name="PaymentInfo[CvvNumber]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="cardNumber" class="block">Card Number</label>
            <input type="text" id="cardNumber" name="PaymentInfo[CardNumber]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="cardExpirationMonth" class="block">Card Expiration Month</label>
            <input type="text" id="cardExpirationMonth" name="PaymentInfo[CardExpirationMonth]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="cardExpirationYear" class="block">Card Expiration Year</label>
            <input type="text" id="cardExpirationYear" name="PaymentInfo[CardExpirationYear]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="cardHolderFirstName" class="block">Card Holder First Name</label>
            <input type="text" id="cardHolderFirstName" name="PaymentInfo[CardHolderFirstName]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="cardHolderLastName" class="block">Card Holder Last Name</label>
            <input type="text" id="cardHolderLastName" name="PaymentInfo[CardHolderLastName]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="billingAmount" class="block">Billing Amount</label>
            <input type="number" step="0.01" id="billingAmount" name="PaymentInfo[BillingAmount]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="billingCurrency" class="block">Billing Currency</label>
            <input type="text" id="billingCurrency" name="PaymentInfo[BillingCurrency]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="addressLine1" class="block">Address Line 1</label>
            <input type="text" id="addressLine1" name="PaymentInfo[CardHolderAddress][AddressLine1]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="addressLine2" class="block">Address Line 2</label>
            <input type="text" id="addressLine2" name="PaymentInfo[CardHolderAddress][AddressLine2]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="city" class="block">City</label>
            <input type="text" id="city" name="PaymentInfo[CardHolderAddress][City]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="postalCode" class="block">Postal Code</label>
            <input type="text" id="postalCode" name="PaymentInfo[CardHolderAddress][PostalCode]" class="w-full border rounded-md p-2">
        </div>
        <div class="mb-4">
            <label for="countryCode" class="block">Country Code</label>
            <input type="text" id="countryCode" name="PaymentInfo[CardHolderAddress][CountryCode]" class="w-full border rounded-md p-2">
        </div>
        <div>
            <button type="submit" class="bg-blue-500 text-white rounded-md p-2">Submit</button>
        </div>
        <div class="w-full fixed left-0 bottom-0 bg-white p-4 text-center shadow-lg border-t border-gray-200">
            <button type="submit" class="bg-black text-white rounded-md p-2 w-80">Book</button>
        </div>
    </form>
</x-app-layout>