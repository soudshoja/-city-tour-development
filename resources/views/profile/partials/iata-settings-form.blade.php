<section>
    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">IATA EasyPay Settings</h2>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage your IATA credentials for EasyPay wallet integration.
    </p>
    <form method="POST" action="{{ route('profile.iata.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="iata_code" :value="__('IATA Code')" />
            <x-text-input id="iata_code" name="iata_code" type="text"
                class="mt-1 block w-full" :value="old('iata_code', optional($user->company)->iata_code)"
                placeholder="8-digit IATA Code" maxlength="8" />
            <x-input-error class="mt-2" :messages="$errors->get('iata_code')" />
        </div>
        <div>
            <x-input-label for="iata_client_id" :value="__('Client ID')" />
            <x-text-input id="iata_client_id" name="iata_client_id" type="text" placeholder="IATA Client ID"
                class="mt-1 block w-full" :value="old('iata_client_id', optional($user->company)->iata_client_id)" />
            <x-input-error class="mt-2" :messages="$errors->get('iata_client_id')" />
        </div>
        <div>
            <x-input-label for="iata_client_secret" :value="__('Client Secret')" />
            <x-text-input id="iata_client_secret" name="iata_client_secret" type="text" placeholder="IATA Client Secret"
                class="mt-1 block w-full" :value="old('iata_client_secret', optional($user->company)->iata_client_secret)" />
            <x-input-error class="mt-2" :messages="$errors->get('iata_client_secret')" />
        </div>
        <div class="flex justify-end">
            <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
        </div>
    </form>
</section>
