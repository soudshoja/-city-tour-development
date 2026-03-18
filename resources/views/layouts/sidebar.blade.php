<div class="sidebar">
    <div class="flex flex-col justify-between items-center space-y-4 mt-5">
        <div class="sidebar-logo">
            <a href="{{ route('dashboard') }}">
                <x-application-logo 
                    width="60" 
                    height="60" 
                    class="rounded-full shadow-md bg-white dark:bg-gray-700 p-2 hover:shadow-lg transition-all duration-200"/>
            </a>
        </div>
        <div class="flex flex-col items-center ">
            <a
                href="{{ route('dashboard') }}">
                <div class="relative">
                    <div data-tooltip="{{ __('menu.dashboard') }}"
                        class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <g fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="19" cy="5" r="2.5" />
                                <path stroke-linecap="round" d="M21.25 10v5.25a6 6 0 0 1-6 6h-6.5a6 6 0 0 1-6-6v-6.5a6 6 0 0 1 6-6H14" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="m7.27 15.045l2.205-2.934a.9.9 0 0 1 1.197-.225l2.151 1.359a.9.9 0 0 0 1.233-.261l2.214-3.34" />
                            </g>
                        </svg>
                    </div>
                </div>

            </a>
        </div>

        @can('create', App\Models\User::class)
        <div class="flex flex-col items-center ">
            <a href="{{ route('users.create') }}">
                <div class="relative ">
                    <div data-tooltip="{{ __('menu.add_new_user') }}"
                        class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <g fill="none" fill-rule="evenodd">
                                <path d="m12.594 23.258l-.012.002l-.071.035l-.02.004l-.014-.004l-.071-.036q-.016-.004-.024.006l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.016-.018m.264-.113l-.014.002l-.184.093l-.01.01l-.003.011l.018.43l.005.012l.008.008l.201.092q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.003-.011l.018-.43l-.003-.012l-.01-.01z" />
                                <path fill="currentColor" d="M11 2a5 5 0 1 0 0 10a5 5 0 0 0 0-10M8 7a3 3 0 1 1 6 0a3 3 0 0 1-6 0M4 18.5c0-.18.09-.489.413-.899c.316-.4.804-.828 1.451-1.222C7.157 15.589 8.977 15 11 15q.563 0 1.105.059a1 1 0 1 0 .211-1.99A13 13 0 0 0 11 13c-2.395 0-4.575.694-6.178 1.672c-.8.488-1.484 1.064-1.978 1.69C2.358 16.976 2 17.713 2 18.5c0 .845.411 1.511 1.003 1.986c.56.45 1.299.748 2.084.956C6.665 21.859 8.771 22 11 22l.685-.005a1 1 0 1 0-.027-2L11 20c-2.19 0-4.083-.143-5.4-.492c-.663-.175-1.096-.382-1.345-.582C4.037 18.751 4 18.622 4 18.5M18 14a1 1 0 0 1 1 1v2h2a1 1 0 1 1 0 2h-2v2a1 1 0 1 1-2 0v-2h-2a1 1 0 1 1 0-2h2v-2a1 1 0 0 1 1-1" />
                            </g>
                        </svg>

                    </div>
                </div>
            </a>
        </div>
        @endcan

        @can('create', App\Models\Invoice::class)
        <div class="flex flex-col items-center ">
            <a
                href="{{ route('invoices.create') }}">

                <div class="relative">

                    <div data-tooltip="{{ __('menu.create_invoice') }}"
                        class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">

                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h9.5A2.25 2.25 0 0 1 18 5.25V14h4v3.75A3.25 3.25 0 0 1 18.75 21h-6.772c.297-.463.536-.966.709-1.5H16.5V5.25a.75.75 0 0 0-.75-.75h-9.5a.75.75 0 0 0-.75.75v5.826a6.5 6.5 0 0 0-1.5.422zm9.75 7.25h-3.096a6.5 6.5 0 0 0-2.833-1.366A.75.75 0 0 1 8.25 11h5.5a.75.75 0 0 1 0 1.5m4.25 7h.75a1.75 1.75 0 0 0 1.75-1.75V15.5H18zM8.25 7a.75.75 0 0 0 0 1.5h5.5a.75.75 0 0 0 0-1.5zM12 17.5a5.5 5.5 0 1 0-11 0a5.5 5.5 0 0 0 11 0M7 18l.001 2.503a.5.5 0 1 1-1 0V18H3.496a.5.5 0 0 1 0-1H6v-2.5a.5.5 0 1 1 1 0V17h2.497a.5.5 0 0 1 0 1z" />
                        </svg>

                    </div>
                </div>
            </a>
        </div>
        @endcan

        @can('create', App\Models\Invoice::class)
        <div class="flex flex-col items-center ">
            <a href="{{ route('bulk-invoices.index') }}">
                <div class="relative">
                    <div data-tooltip="{{ __('menu.bulk_invoice_upload') }}"
                        class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm4 18H6V4h7v5h5v11M8 12h2v2H8v-2m0 4h8v-2H8v2m8-10h-4v-2h4v2m-4 4h4v-2h-4v2Z"/>
                        </svg>
                    </div>
                </div>
            </a>
        </div>
        @endcan

        <div class="flex flex-col items-center"
            x-data="currencyConverter({ companyId: window.APP_COMPANY_ID, convertUrl: '{{ route('exchange.convert') }}'})">

            <!-- Trigger Button -->
            <button @click="showModal = true">
                <div class="relative">
                    <div data-tooltip="{{ __('menu.currency_exchange') }}"
                        class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M8 15c0 2 2 3 4 3s4-1 4-3-2-3-4-3-4-1-4-3 2-3 4-3 4 1 4 3" />
                            <path d="M12 6v12" />
                        </svg>
                    </div>
                </div>
            </button>

            <!-- Modal -->
            <div x-cloak x-show="showModal" x-trap="showModal" @click.self="showModal = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-50">

                <div class="bg-white rounded-lg p-6 w-full max-w-[900px] md:max-w-[1100px] lg:max-w-[1280px] shadow-xl overflow-y-auto max-h-[90vh]">

                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">{{ __('menu.currency_exchange') }}</h2>
                            <p class="text-gray-600 italic text-xs mt-1">{{ __('menu.currency_exchange_description') }}</p>
                        </div>
                        <button @click="showModal = false" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                    </div>

                    <div class="space-y-6">
                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">{{ __('menu.amount') }}</label>
                            <input type="text" step="0.01" x-model.number="amount"
                                @input.debounce.400ms="convertIfReady"
                                class="w-full border border-gray-300 rounded-md px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>



                        <div class="flex items-center justify-between space-x-4">
                            <!-- From -->
                            <div class="w-full border rounded-lg p-4 flex items-center justify-between">
                                <div class="relative w-full">
                                    <p class="text-sm text-gray-500">{{ __('menu.from') }}</p>
                                    <select id="fromSelect"
                                        x-model="from"
                                        @change="convertIfReady"
                                        class="w-full font-semibold border-none focus:ring-0 p-0 bg-transparent appearance-none pr-8">
                                        @foreach($allIso as $code)
                                        @php
                                        $c = $currencies[$code] ?? null;
                                        $label = trim(($c->symbol ?? '') . ' ' . $code . ' ' . ($c->name ?? ''));
                                        @endphp
                                        <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>


                            <!-- Swap -->
                            <button type="button" @click="swap(); convertIfReady()" class="rounded-full border p-2 bg-white shadow" title="Swap">
                                <img src="{{ asset('images/swap-currency.png') }}" alt="Swap" class="w-full h-full">
                            </button>

                            <!-- To -->
                            <div class="w-full border rounded-lg p-4 flex items-center justify-between">
                                <div class="relative w-full">
                                    <p class="text-sm text-gray-500">{{ __('menu.to') }}</p>
                                    <select id="toSelect"
                                        x-model="to"
                                        @change="convertIfReady"
                                        class="w-full font-semibold border-none focus:ring-0 p-0 bg-transparent appearance-none pr-8">
                                        @foreach($allIso as $code)
                                        @php
                                        $c = $currencies[$code] ?? null;
                                        $label = trim(($c->symbol ?? '') . ' ' . $code . ' ' . ($c->name ?? ''));
                                        @endphp
                                        <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>

                        <!-- Result -->
                        <template x-if="ready">
                            <div class="text-center mt-10">
                                <p class="text-sm text-gray-600" x-text="`${format(amount)} ${from} =`"></p>
                                <p class="text-4xl font-bold text-blue-800">
                                    <span x-text="format(converted)"></span>
                                    <span class="font-medium text-blue-800" x-text="to"></span>
                                </p>
                                <div class="text-sm text-gray-500 mt-2">
                                    <p x-text="`1 ${from} = ${parseFloat(rate).toFixed(4)} ${to}`"></p>
                                    <p x-text="`1 ${to} = ${parseFloat(inverse).toFixed(4)} ${from}`"></p>
                                </div>
                                <p class="text-xs text-gray-400 mt-5" x-text="lastUpdated"></p>
                            </div>
                        </template>

                        <!-- (Button kept but hidden; remove if you prefer) -->
                        <div class="flex items-center justify-end gap-2" x-show="false">
                            <button type="button" @click="convert()" class="px-4 py-2 bg-blue-600 text-white rounded-full shadow-md hover:bg-blue-700">
                                {{ __('menu.convert') }}
                            </button>
                        </div>

                        <!-- Success/Info notice -->
                        <template x-if="notice">
                            <div class="flex justify-center mt-2">
                                <div
                                    class="border border-green-400 bg-green-50 text-green-700 text-sm px-3 py-2 rounded max-w-lg text-center"
                                    x-text="notice">
                                </div>
                            </div>
                        </template>

                        <!-- Existing error block stays as-is -->
                        <template x-if="error">
                            <div class="flex justify-center mt-2">
                                <div class="border border-red-500 bg-red-50 text-red-700 text-sm px-3 py-2 rounded max-w-lg text-center"
                                    x-text="error"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        @if(auth()->user()->role_id == \App\Models\Role::ADMIN)
        <x-sidebar-company
            :companies="$sidebarCompanies ?? collect()"
            :currentCompanyId="$currentCompanyId ?? 1" />
        @endif

        <div class="flex flex-col items-center" x-data="{ showLangModal: false }">
            <!-- Language Switcher Button -->
            <button @click="showLangModal = true">
                <div class="relative">
                    <div data-tooltip="{{ __('general.language') }}"
                        class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M2 12h20"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                    </div>
                </div>
            </button>

            <!-- Language Modal -->
            <div x-cloak x-show="showLangModal" x-trap="showLangModal" @click.self="showLangModal = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-50">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-sm shadow-xl">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ __('general.select_language') }}</h2>
                            <p class="text-gray-600 dark:text-gray-400 italic text-xs mt-1">{{ __('general.language_description') }}</p>
                        </div>
                        <button @click="showLangModal = false" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                    </div>
                    <div class="space-y-3">
                        <a href="{{ route('locale.switch', 'en') }}"
                            class="flex items-center p-4 border rounded-lg cursor-pointer transition-all duration-200
                                {{ app()->getLocale() === 'en' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-600 hover:border-blue-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xl mr-4">EN</div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800 dark:text-gray-100">English</p>
                            </div>
                            @if(app()->getLocale() === 'en')
                                <svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </a>
                        <a href="{{ route('locale.switch', 'ar') }}"
                            class="flex items-center p-4 border rounded-lg cursor-pointer transition-all duration-200
                                {{ app()->getLocale() === 'ar' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-600 hover:border-blue-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xl mr-4">AR</div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800 dark:text-gray-100">العربية</p>
                            </div>
                            @if(app()->getLocale() === 'ar')
                                <svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>


    <div x-data="{ 
        toggle: false,
        open: false,
        iataWallet: false
        }"
        class="sidebar-profile">
        @include('layouts.profile')
    </div>

    <div class="flex flex-col justify-between items-center space-y-4">

        <div id="themeToggle" data-tooltip="{{ __('menu.switch_theme') }}">

            <button id="themeButton" class="p-3 rounded-full shadow-md flex items-center justify-center bg-black hover:bg-gray-700 dark:bg-gray-600  dark:hover:bg-gray-900/50 transition-all duration-200">

                <svg id="lightSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#fff" d="M12 15q1.25 0 2.125-.875T15 12t-.875-2.125T12 9t-2.125.875T9 12t.875 2.125T12 15m0 2q-2.075 0-3.537-1.463T7 12t1.463-3.537T12 7t3.538 1.463T17 12t-1.463 3.538T12 17m-7-4H1v-2h4zm18 0h-4v-2h4zM11 5V1h2v4zm0 18v-4h2v4zM6.4 7.75L3.875 5.325L5.3 3.85l2.4 2.5zm12.3 12.4l-2.425-2.525L17.6 16.25l2.525 2.425zM16.25 6.4l2.425-2.525L20.15 5.3l-2.5 2.4zM3.85 18.7l2.525-2.425L7.75 17.6l-2.425 2.525zM12 12" />
                </svg>

                <svg id="darkSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                    style="display: none;">
                    <path fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9 9 0 0 0 8.997-9.252a7 7 0 0 1-10.371-8.643A9 9 0 0 0 12 21" />

                </svg>
            </button>
        </div>
    </div>
</div>
<script>
    const __sidebarTranslations = {
        lastUpdated: "{{ __('menu.last_updated') }}",
        failedToConvert: "{{ __('menu.failed_to_convert') }}",
        rateCreatedRefreshing: "{{ __('menu.rate_created_refreshing') }}"
    };

    function currencyConverter({
        companyId,
        convertUrl
    }) {
        return {
            showModal: false,

            companyId,
            convertUrl,

            amount: null,
            from: null,
            to: null,
            rate: null,
            inverse: null,
            converted: null,
            ready: false,
            error: null,
            lastUpdated: '',
            notice: null,
            loading: false,

            init() {
                this.$nextTick(() => {
                    if (!this.from) this.from = this._selectValue('#fromSelect');
                    if (!this.to) this.to = this._selectValue('#toSelect');
                    this.convertIfReady();
                });
            },

            _selectValue(sel) {
                const el = this.$root.querySelector(sel);
                return el ? el.value : null;
            },

            format(n) {
                return Number(n ?? 0).toLocaleString(undefined, {
                    maximumFractionDigits: 6
                });
            },

            swap() {
                const tmp = this.from;
                this.from = this.to;
                this.to = tmp;
                this.ready = false;
            },

            convertIfReady() {
                const validAmt = typeof this.amount === 'number' ? this.amount : Number(this.amount);
                if (validAmt > 0 && this.from && this.to) {
                    this.convert();
                } else {
                    this.ready = false;
                }
            },

            async convert() {
                this.error = null;
                this.notice = null;
                this.ready = false;
                this.loading = true;

                try {
                    const res = await fetch(this.convertUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            company_id: this.companyId,
                            from_currency: this.from,
                            to_currency: this.to,
                            amount: Number(this.amount),
                        })
                    });

                    let payload = null,
                        fallbackText = '';
                    try {
                        payload = await res.json();
                    } catch {
                        fallbackText = await res.text();
                    }

                    if (!res.ok || (payload && payload.status !== 'success')) {
                        const msg = (payload && (payload.message || payload.error)) || fallbackText || `Server error ${res.status}`;
                        throw new Error(msg);
                    }


                    // If backend says the rate was just created, show message then retry
                    if (payload && payload.created === true) {
                        this.notice = payload.message || __sidebarTranslations.rateCreatedRefreshing;
                        // small delay so DB write is visible; 1500 ms as you asked
                        setTimeout(() => {
                            this.convert(); // re-run to fetch fresh numbers
                        }, 3000);
                        this.loading = false;
                        return; // stop here; don't render numbers yet
                    }

                    // Normal success path
                    const data = payload ?? {};
                    this.rate = this.format(data.exchange_rate);
                    this.inverse = (data.inverse_rate == null || data.inverse_rate === 'N/A') ?
                        null :
                        this.format(data.inverse_rate);
                    this.converted = data.converted_amount;
                    this.ready = true;

                    const now = new Date();
                    this.lastUpdated = `${__sidebarTranslations.lastUpdated} ${now.toLocaleString()}`;
                } catch (e) {
                    this.error = e?.message || __sidebarTranslations.failedToConvert;
                } finally {
                    this.loading = false;
                }
            },

            format(n) {
                if (n === 'N/A' || n === null || Number.isNaN(Number(n))) return 'N/A';
                return Number(n ?? 0).toLocaleString(undefined, {
                    maximumFractionDigits: 6
                });
            },
        };
    }
</script>