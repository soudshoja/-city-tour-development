@php
    $allowedCurrencies = ['USD', 'SAR', 'QAR', 'GBP', 'AED', 'EUR', 'EGP', 'BHD'];
@endphp
<div class="grid">
    <div
        @click="createRateModal = false"
        class="flex justify-between p-4">
        <p>Create Currency Exchange</p>
        <p class="text-gray-400 hover:text-black cursor-pointer">Close</p>
    </div>
    <hr>
    <form id="createRateForm" action="{{ route('exchange.store') }}" class="p-4 w-full flex gap-2 justify-around" method="POST">
        @csrf
        <input type="hidden" name="is_manual" value="0">
        @if(auth()->user()->hasRole('admin'))
        <div class="form-group w-full">
            <label for="company">Company</label>
            <select class="p-2 border border-gray-400 rounded-md" id="company_rate" name="company_id">
                <option selected disabled>Select Company</option>
                @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group w-full">
            <label for="base-currency">Base Currency</label>
            <select class="p-2 border border-gray-400 rounded-md w-full" id="base-currency" name="base_currency">
                <option selected disabled>Select Currency</option>
                @foreach($currenciesAvailable as $currency)
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group w-full">
            <label for="exchange-currency">Exchange Currency</label>
            <select class="p-2 border border-gray-400 rounded-md w-full" id="exchange-currency" name="exchange_currency">
                <option selected disabled>Select Currency</option>
                @foreach($currenciesAvailable as $currency)
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }}</option>
                @endforeach
            </select>
        </div>
        @elseif( auth()->user()->hasRole('company') && auth()->user()->company !== null)
        <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">
        <div class="form-group w-full">
            <label for="base-currency">Base Currency</label>
            <select class="p-2 border border-gray-400 rounded-md w-full" id="base-currency" name="base_currency">
                <option selected disabled>Select Currency</option>
                @foreach($currenciesAvailable as $currency)
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group w-full">
            <label for="exchange-currency">Exchange Currency</label>
            <select class="p-2 border border-gray-400 rounded-md w-full" id="exchange-currency" name="exchange_currency">
                <option selected disabled>Select Currency</option>
                @foreach($currenciesAvailable as $currency)
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }}</option>
                @endforeach
            </select>
        </div>
        @else
        <div class="alert alert-danger" role="alert">
            You are not authorized to create a currency exchange.
        </div>
        @endif
    </form>
    <div class="p-2 flex justify-end">
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-md" form="createRateForm">
            Create Currency Exchange
        </button>
    </div>
</div>