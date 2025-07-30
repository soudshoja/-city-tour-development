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

    <div
        x-data='{createRateModal : false}'
        class="header p-2 bg-white rounded-md shadow font-bold dark:bg-gray-800">
        <div class="flex items-center justify-between">
            Currency Exchange List
            <button
                @click="createRateModal = true"
                class="btn btn-primary">
                Create New Rate
            </button>
            <div
                x-show="createRateModal"
                x-cloak
                class="absolute inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center "
                x-transition:enter="transition ease-out duration-50"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-50"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
                <div
                    @click.away="createRateModal = false"
                    class="bg-white rounded-md shadow-lg w-1/2">
                    @include('currency-exchange.partials.create')
                </div>
            </div>
        </div>
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
                    <th>Updating Method</th>
                    <th>Last Updated</th>
                </tr>
            </thead>

            <tbody>
                @foreach($currencyExchanges as $currencyExchange)
                @role('admin')

                <td>
                    <p class="uppercase">
                        {{ $currencyExchange->company->name }}
                    </p>
                </td>

                @endrole
                <td>{{ $currencyExchange->base_currency }}</td>
                <td>{{ $currencyExchange->exchange_currency }}</td>
                @if(auth()->user()->can('update currency exchange') && $currencyExchange->is_manual)
                <td>
                    <div class="inline-flex justify-between gap-2 items-center" id="exchange-input-container-{{ $currencyExchange->id }}">
                        <input
                            type="number"
                            class="rounded-md border-gray-400 dark:bg-gray-600 exchange-input py-0"
                            value="{{ $currencyExchange->exchange_rate }}"
                            id="{{ $currencyExchange->id }}"
                            onkeydown="setInitialRate(this)"
                            onkeyup="exchangeRateDiffer(this)">
                     
                    </div>
                </td>
                @else
                <td>
                    <div id="exchange-input-container-{{ $currencyExchange->id }}">
                        {{ $currencyExchange->exchange_rate }}
                    </div>
                </td>
                @endif
                <td>
                    <div class="w-full flex items-center justify-between">
                        <div class="method-text" data-id="{{ $currencyExchange->id }}">
                            @if($currencyExchange->is_manual)
                            <p class="text-blue-500">Manual</p>
                            @else
                            <p class="text-green-500">Auto</p>
                            @endif
                        </div>
                        <label class="w-12 h-6 relative">
                            <input
                                type="checkbox"
                                class="toggle-method absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                data-id="{{ $currencyExchange->id }}"
                                {{ $currencyExchange->is_manual ? '' : 'checked' }} />
                            <span class="bg-blue-500 block h-full rounded-full before:absolute before:left-1 before:bg-white  dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-green-500 before:transition-all before:duration-300">
                            </span>
                        </label>
                    </div>
                </td>
                <td class="group relative">
                    <p class="group-hover:invisible absolute top-1/2 transform -translate-y-1/2 transition-opacity duration-300 ease-in-out">
                        {{ $currencyExchange->updated_at->diffForHumans() }}
                    </p>
                    <p class="invisible group-hover:visible absolute top-1/2 transform -translate-y-1/2 transition-opacity duration-300 ease-in-out">
                        {{ $currencyExchange->updated_at}}
                    </p>
                </td>
                </tr>

                @endforeach
                <tr>

                </tr>
            </tbody>
        </table>

    </div>

    <div id="update-exchange-rate" class="opacity-0 bg-gradient-to-t from-gray-500 pt-4 pb-6 absolute bottom-0 left-0 w-full m-auto flex justify-center transition-opacity duration-150 ease-in-out">
        <div class="bg-white p-3 px-8 shadow-lg rounded-md">
            <button class="btn btn-primary" onclick="updateRateManual()">Update Exchange Rate</button>
        </div>
    </div>
    <script>
        let updateExchangeContainer = document.getElementById('update-exchange-rate');
        let updateManualUrl = "{!! route('exchange.update.manual') !!}";
        let updateAutoUrl = "{!! route('exchange.update.auto') !!}";
        let toggleMethod = document.querySelectorAll('.toggle-method');

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

        updateRateManual = () => {
            if (differentCount > 0 && exchangeRate) {


                let data = [];
                for (const key in exchangeRate) {
                    if (exchangeRate[key].current < 0 || !/^\d+(\.\d{1,4})?$/.test(exchangeRate[key].current)) {
                        alert('Exchange rate must be a positive number and maximum 4 decimal places');
                        return;
                    }

                    data.push({
                        id: key,
                        exchange_rate: exchangeRate[key].current,
                        is_manual: true
                    });

                }

                fetch(updateManualUrl, {
                        method: 'PUT',
                        body: JSON.stringify(data),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }

                    })
                    .then(response => {
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

        updateRateFromApi = (element) => {

            if (!confirm('Are you sure you want to update all exchange rate automatically?')) return;
            console.log('element:   ', element);
            // element.classList.add('animate-spin');

            let data = {
                id: element.getAttribute('data-id'),
                is_manual: false
            }
            fetch(updateAutoUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
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
                .finally(() => {
                    // element.classList.remove('animate-spin');
                })
        }

        toggleMethod.forEach(element => {
            element.addEventListener('change', (e) => {
                let id = e.target.getAttribute('data-id');
                toggleUpdateMethod(element, id);
            });
        });

        toggleUpdateMethod = (element, id) => {
            let updateMethodUrl = "{!! route('exchange.update.method', '__id__') !!}".replace('__id__', id);
            fetch(updateMethodUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                })
                .then(response => {
                    if (!response.ok) {
                        alert('Something went wrong');
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const alert = document.createElement('div');
                    alert.className = 'alert flex items-center justify-between rounded bg-success-light p-3.5 text-success ';
                    alert.id = 'alert';
                    alert.innerHTML = `
                <span>${data.message}</span>
                <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
                `;
                    document.body.append(alert);

                    setTimeout(() => {
                        alert.remove();
                    }, 3000);

                    exchangeRateContainer = document.getElementById(`exchange-input-container-${id}`);

                    if (data.currencyExchange.is_manual) {
                        exchangeRateContainer.classList.add('inline-flex', 'justify-between', 'gap-2', 'items-center');
                        exchangeRateContainer.innerHTML = `
                    <input
                    type="number"
                    class="rounded-md border-gray-400 dark:bg-gray-600 exchange-input py-0"
                    value="${data.currencyExchange.exchange_rate}"
                    id="${data.currencyExchange.id}"
                    onkeydown="setInitialRate(this)"
                    onkeyup="exchangeRateDiffer(this)">
                `;

                    } else {
                        exchangeRateContainer.className = '';
                        exchangeRateContainer.innerHTML = data.currencyExchange.exchange_rate;
                    }

                    let methodText = element.parentElement.parentElement.querySelector('.method-text');

                    if (data.currencyExchange.is_manual) {
                        methodText.innerHTML = '<p class="text-blue-500">Manual</p>';
                    } else {
                        methodText.innerHTML = '<p class="text-green-500">Auto</p>';
                    }
                })
        }

        updateExchangeRateContainer = (exchange_rate) => {

            return rate;
        }
    </script>

</x-app-layout>