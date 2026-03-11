<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
            {{ __('Verify Code') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto">
            <div class="bg-white dark:bg-gray-900 p-6 shadow sm:rounded-lg">

                @if (session('status') === 'code-sent')
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">
                        {{ __('A verification code has been sent to your email address.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.password.verify-code') }}">
                    @csrf

                    <div>
                        <x-input-label for="code" :value="__('Verification Code')" />
                        <x-text-input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*"
                            maxlength="6" autocomplete="one-time-code" required autofocus class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-4">
                        <a href="{{ route('profile.edit', ['tab' => 'Security']) }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400">
                            {{ __('Cancel') }}
                        </a>

                        <x-primary-button>
                            {{ __('Verify & Continue') }}
                        </x-primary-button>
                    </div>
                </form>

                <div class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                    {{ __("Didn't receive the code?") }}
                    <form method="POST" action="{{ route('profile.password.request-update') }}" class="inline">
                        @csrf
                        <input type="hidden" name="current_password" value="__retry__" />
                        <button type="submit" class="text-blue-600 hover:underline dark:text-blue-400">
                            {{ __('Resend Code') }}
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
