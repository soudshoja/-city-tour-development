<form method="POST" action="{{ route('suppliers.exchange-rates.update', $supplier->id) }}">
    @csrf
    <h2>Exchange Rates for {{ $supplier->name }}</h2>
 
    <hr>
    @foreach($currencies as $currency)
        <div>
            <label>{{ $currency }}</label>
            <input type="number" step="0.000001" name="{{ strtolower($currency) }}"
                value="{{ optional($supplier->exchangeRates->where('currency', $currency)->first())->rate }}">
        </div>
    @endforeach
    <button type="submit">Save Rates</button>
</form>