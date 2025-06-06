<section>
    <header class="bg-white dark:bg-gray-800 rounded-lg flex justify-between items-center">
        <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Profile Information') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Update your account's profile information and email address.") }}
            </p>
        </div>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)"
                required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        @if ($userEmail)
            <div class="mt-4">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input readonly id="email" name="email" type="email"
                    class="mt-1 block w-full bg-gray-100" :value="old('email', $userEmail)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                    {{-- <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification"
                            class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div> --}}
                @endif
            </div>
        @endif
        @if ($userPhone)
            <div>
                <x-input-label for="phone" :value="__('Name')" />
                <x-text-input readonly id="phone" name="phone" type="text"
                    class="mt-1 block w-full  bg-gray-100" :value="old('phone', $userPhone)" required autofocus autocomplete="phone" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>
        @endif

        {{-- @php
            $selectedBank = $bankAccounts->firstWhere('id', (int) old('acc_bank_id', $user->acc_bank_id));
        @endphp


        <div class="mt-4">
            <x-input-label for="acc_bank_name" :value="__('Default Bank Account')" />

            <input id="acc_bank_name" name="acc_bank_name" list="bank_accounts"
                class="mt-1 block w-full h-11 rounded-md border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50 px-3"
                value="{{ old('acc_bank_name', $selectedBank?->name) }}" required autocomplete="off" />

            <datalist id="bank_accounts">
                @foreach ($bankAccounts as $account)
                    <option data-id="{{ $account->id }}" value="{{ $account->name }}">{{ $account->name }}</option>
                @endforeach
            </datalist>

            <x-text-input id="acc_bank_id_hidden" name="acc_bank_id" type="hidden"
                class="mt-1 block w-full bg-gray-100" value="{{ old('acc_bank_id', auth()->user()->acc_bank_id) }}" />

            <x-input-error class="mt-2" :messages="$errors->get('acc_bank_id')" />

            <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                Please define the bank account via
                <a href='/coa'>
                    <span
                        class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                        {{ __('here.') }}
                    </span>
                </a>
            </p>
        </div> --}}

        <div class="mt-4">
            <x-input-label for="role" :value="__('Role')" />
            <x-text-input readonly id="role" name="role" type="text" class="mt-1 block w-full bg-gray-100"
                :value="old('role', ucfirst($user->role->name) ?? 'No role assigned')" required autofocus autocomplete="role" />
            <x-input-error class="mt-2" :messages="$errors->get('role')" />
        </div>

        <div class="mt-4">
            <x-input-label for="created_at" :value="__('Registered Date')" />
            <x-text-input readonly id="created_at" name="created_at" type="text"
                class="mt-1 block w-full bg-gray-100" :value="$user->created_at ? $user->created_at->format('l, F j, Y g:i A') : ''" autofocus />
            <x-input-error class="mt-2" :messages="$errors->get('created_at')" />
        </div>


        @if (session('status') === 'profile-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                class="text-sm text-gray-600 dark:text-gray-400">{{ __('Saved.') }}</p>
        @endif

        <div class="flex justify-end">
            <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
        </div>
    </form>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('acc_bank_name');
            const hiddenInput = document.getElementById('acc_bank_id_hidden');
            const options = document.querySelectorAll('#bank_accounts option');

            const updateHidden = () => {
                const match = Array.from(options).find(opt => opt.value === nameInput.value);
                hiddenInput.value = match ? match.getAttribute('data-id') : ''; // Update hidden field value
            };

            nameInput.addEventListener('input', updateHidden); // Update on user input
            nameInput.addEventListener('change', updateHidden); // Update on selection change

            // Update hidden field before form submit
            const form = nameInput.closest('form');
            form.addEventListener('submit', updateHidden);
        });
    </script>





</section>
