<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
            {{ __('Change Password') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto">
            <div class="bg-white dark:bg-gray-900 p-6 shadow sm:rounded-lg">

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('To change your password, please confirm your current password. A verification code will be sent to your email.') }}
                </p>

                @if (session('status') === 'code-sent')
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">
                        {{ __('Verification code sent successfully.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.password.request-update') }}">
                    @csrf

                    <div>
                        <x-input-label for="current_password" :value="__('Current Password')" />
                        <x-text-input id="current_password" name="current_password" type="password"
                            class="mt-1 block w-full" required autocomplete="current-password" autofocus />
                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-4">
                        <x-primary-button>
                            {{ __('Send Verification Code') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
