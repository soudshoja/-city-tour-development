<section>
    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">IATA EasyPay Settings</h2>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage your IATA credentials for EasyPay wallet integration
    </p>
    <form method="POST" action="{{ route('profile.iata.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('PATCH')
        <div>
            <x-input-label for="iata_code" :value="__('IATA Code')" />
            <x-text-input id="iata_code" name="iata_code" type="text"
                class="mt-1 block w-full" :value="old('iata_code', optional($company ?? $user->company)->iata_code)"
                placeholder="8-digit IATA Code" maxlength="8" />
            <x-input-error class="mt-2" :messages="$errors->get('iata_code')" />
        </div>
        <div>
            <x-input-label for="iata_client_id" :value="__('Client ID')" />
            <x-text-input id="iata_client_id" name="iata_client_id" type="text" placeholder="IATA Client ID"
                class="mt-1 block w-full" :value="old('iata_client_id', optional($company ?? $user->company)->iata_client_id)" />
            <x-input-error class="mt-2" :messages="$errors->get('iata_client_id')" />
        </div>
        <div>
            <x-input-label for="iata_client_secret" :value="__('Client Secret')" />
            <div class="relative mt-1">
                <x-text-input id="iata_client_secret" name="iata_client_secret" type="password" placeholder="IATA Client Secret"
                class="mt-1 block w-full" :value="old('iata_client_secret', optional($user->company)->iata_client_secret)" />
                <button type="button"
                    onclick="togglePassword('iata_client_secret', this)"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
                    </svg>
                </button>
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('iata_client_secret')" />
        </div>
        <div class="flex justify-end">
            <button class="btn-primary">Save Settings</button>
        </div>
    </form>
</section>

