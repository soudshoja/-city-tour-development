<!-- resources/views/auth/forgot-password.blade.php -->
<x-guest-layout>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
        <div>
            <a href="{{ url('/') }}">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            <h2 class="text-center text-xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
                {{ __('Forgot your password?') }}
            </h2>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400 text-center">
                {{ __('No problem. Just let us know your email address and we will email you a password reset link.') }}
            </p>

            <!-- Session Status -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                        :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button class="ml-3">
                        {{ __('Email Password Reset Link') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
