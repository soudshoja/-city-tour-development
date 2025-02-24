<x-app-layout>
    <style>
        .dt-length {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        #dt-search-0 {
            width: 100%;
        }

        .dark #dt-search-0 {
            background-color: #374151;
        }

        /* Chrome, Safari, Edge, Opera */
        .exchange-input::-webkit-outer-spin-button,
        .exchange-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Firefox */
        .exchange-input {
            -moz-appearance: textfield;
        }
    </style>
    <div class="header p-2 bg-white rounded-md shadow font-bold dark:bg-gray-800">
        Currency Exchange List
    </div>
    <div class="mt-3 p-2 bg-white rounded-md shadow dark:bg-gray-800 overflow-auto">
        @role('admin')
        Exchange Rate by All Company
        @else
        Exchange Rate by Your Company
        @endrole
        <hr class="mt-3">
        <table id="currency-exchange">
            <thead>
                <tr>
                    @role('admin')
                    <th>Company</th>
                    @endrole
                    <th>From Currency</th>
                    <th>To Currency</th>
                    <th>Exchange Rate</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            @if($currencyExchanges->isEmpty())
            <tbody>
                <tr>
                    <td colspan="6" class="text-center">No data found</td>
                </tr>
            </tbody>
            @else
            <tbody>
                @foreach($currencyExchanges as $currencyExchange)
                <tr>
                    @role('admin')
                    <td>{{ $currencyExchange->company->name }}</td>
                    @endrole
                    <td>{{ $currencyExchange->base_currency }}</td>
                    <td>{{ $currencyExchange->exchange_currency }}</td>
                    @cannot('update currency exchange')
                    <td>
                        <input
                            type="number"
                            class="rounded-md border-gray-400 dark:bg-gray-600 exchange-input"
                            value="{{ $currencyExchange->exchange_rate }}"
                            id="{{ $currencyExchange->id }}"
                            onkeydown="setInitialRate(this)"
                            onkeyup="exchangeRateDiffer(this)">
                    </td>
                    @else
                    <td>{{ $currencyExchange->exchange_rate }}</td>
                    @endcan
                    <td>{{ $currencyExchange->created_at }}</td>
                    <td>{{ $currencyExchange->updated_at }}</td>
                </tr>
                @endforeach
            </tbody>
            @endif
        </table>
    </div>
    <div id="update-exchange-rate" class="opacity-0 bg-gradient-to-t from-gray-500 to-transparent pt-4 pb-6 absolute bottom-0 left-0 w-full m-auto flex justify-center transition-opacity duration-150 ease-in-out">
        <div class="bg-white p-3 px-8 shadow-lg rounded-md">
            <button class="btn btn-primary" onclick="updateExchangeRate()">Update Exchange Rate</button>
        </div>
    </div>
    <script>
        let updateExchangeContainer = document.getElementById('update-exchange-rate');
        let updateUrl = "{!! route('exchange.update') !!}";

        new DataTable('#currency-exchange', {});

        exchangeRate = {};
        let differentCount;
        setInitialRate = (element) => {


            if (!exchangeRate[element.id]) {
                exchangeRate[element.id] = {
                    'initial': element.value,
                }
            }
        }

        exchangeRateDiffer = (element) => {

            if (exchangeRate[element.id]) {
                exchangeRate[element.id].current = element.value;
            } else {
                alert('something went wrong');
            }

            differentCount = 0;

            for (const key in exchangeRate) {
                let element = document.getElementById(key);



                if (exchangeRate[key].initial != exchangeRate[key].current) {
                    differentCount++;

                    if (element.value < 0 || !/^\d+(\.\d{1,4})?$/.test(element.value)) {
                        element.classList.add('border-red-600', 'border-2');
                        element.classList.remove('border-gray-400');
                    } else {
                        element.classList.remove('border-red-600', 'border-2');
                    }

                    if (!element.classList.contains('border-gree-600')) {
                        element.classList.add('border-blue-600', 'border-2');
                        element.classList.remove('border-gray-400');
                    }

                } else {
                    if (element.classList.contains('border-blue-600')) {
                        element.classList.remove('border-blue-600', 'border-2');
                        element.classList.add('border-gray-400');
                    }
                }

                if (differentCount > 0) {
                    updateExchangeContainer.classList.remove('opacity-0');
                } else {
                    updateExchangeContainer.classList.add('opacity-0');
                }
            }
        }

        updateExchangeRate = () => {
            if (differentCount > 0 && exchangeRate) {
                

                let data = [];
                for (const key in exchangeRate) {
                    if(exchangeRate[key].current < 0 || !/^\d+(\.\d{1,4})?$/.test(exchangeRate[key].current)){
                        alert('Exchange rate must be a positive number and maximum 4 decimal places');
                        return;
                    }

                    data.push({
                        id: key,
                        exchange_rate: exchangeRate[key].current
                    });

                }

                console.log(data);

                fetch(updateUrl, {
                        method: 'PUT',
                        body: JSON.stringify(data),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }

                    })
                    .then(response => {
                        console.log(response);
                        if (!response.ok) {
                            alert('Something went wrong');
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        alert(data.message);
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });


            }
        }
    </script>
</x-app-layout>