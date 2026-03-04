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
                <div class="mb-5 text-sm text-green-600 dark:text-green-400 text-center">
                    A verification code has been sent to your email address
                </div>
                @endif

                <div class="mb-5">
                    <h2 class="font-semibold">Verify Code</h2>
                    <p class="text-sm text-gray-500 mb-3">Please enter the verification code sent to your registered email address.</p>
                    <div class="flex flex-col items-center mt-3 mb-3">
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            Code expires in
                        </div>
                        <span id="countdown" class="text-2xl font-bold tabular-nums text-blue-600 mt-1">10:00</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.password.verify-code') }}">
                    @csrf

                    <div>
                        <x-input-label for="code" :value="__('Enter the verification code')" />
                        <x-text-input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*"
                            maxlength="6" autocomplete="one-time-code" required autofocus class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                   <div class="flex items-center justify-between mt-10">
                    <a href="{{ route('profile.edit', ['tab' => 'Security']) }}" class="btn-cancel">
                        Cancel
                    </a>
                    <button class="btn-primary">
                        Verify
                    </button>
                </div>
                </form>

                <div class="mt-6 text-sm text-gray-600 text-center dark:text-gray-400">
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
    const countdownEl = document.getElementById('countdown');
    if (!countdownEl) return;

    let totalSeconds = 10 * 60; // 10 minutes

    const timer = setInterval(() => {
        totalSeconds--;

        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        countdownEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

        // Turn red when under 2 minutes
        if (totalSeconds <= 120) {
            countdownEl.classList.add('text-red-600');
        }

        if (totalSeconds <= 0) {
            clearInterval(timer);
            countdownEl.textContent = 'Expired';
            countdownEl.classList.remove('text-red-500');
            countdownEl.classList.add('text-red-700', 'font-bold');

            // Disable the verify button and input
            document.getElementById('code').disabled = true;
            document.querySelector('button.btn-primary').disabled = true;
            document.querySelector('button.btn-primary').classList.add('opacity-50', 'cursor-not-allowed');
        }
    }, 1000);
});
    </script>
</x-app-layout>