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
        
        <div class="form-group w-full">
            <label for="base-currency">Base Currency</label>
            <select class="p-2 border border-gray-400 rounded-md w-full dark:bg-gray-700 dark:border-gray-600" id="base-currency" name="base_currency" required>
                <option selected disabled value="">Select Currency</option>
                @foreach($currenciesAvailable as $currency)
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group w-full">
            <label for="exchange-currency">Exchange Currency</label>
            <select class="p-2 border border-gray-400 rounded-md w-full dark:bg-gray-700 dark:border-gray-600" id="exchange-currency" name="exchange_currency" required>
                <option selected disabled value="">Select Currency</option>
                @foreach($currenciesAvailable as $currency)
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }}</option>
                @endforeach
            </select>
        </div>
    </form>
    <div class="p-2 flex justify-end">
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition-colors" form="createRateForm">
            Create Currency Exchange
        </button>
    </div>
</div>