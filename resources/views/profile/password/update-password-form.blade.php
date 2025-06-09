<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Change Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    @if (session('status') === 'password-updated')
        <div class="mt-4 rounded-md bg-green-100 p-4 text-green-700 dark:bg-green-900 dark:text-green-300">
            {{ __('Password updated successfully.') }}
        </div>
    @endif

    @if (!session('password_update_verified'))
        <form method="post" action="{{ route('profile.password.request-update') }}" class="mt-6 space-y-6">
            @csrf

            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-text-input id="update_password_current_password" name="current_password" type="password"
                    class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Request Verification Code') }}</x-primary-button>
            </div>
        </form>

        {{-- @if (session('status') === 'code-sent')
            <div id="verificationModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                <div class="bg-white dark:bg-gray-900 rounded-lg p-6 shadow-lg w-full max-w-md">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Enter Verification Code</h2>

                    <p class="text-green-600 mt-2">
                        {{ __('Verification code sent to your email. Please check your inbox.') }}
                    </p>

                    <form method="POST" action="{{ route('profile.password.verify-code') }}" class="mt-4 space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="code" :value="__('Verification Code')" />
                            <x-text-input id="code" name="code" type="text" required
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="hideModal()"
                                class="px-4 py-2 bg-gray-300 rounded-md hover:bg-gray-400 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                                Cancel
                            </button>

                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Verify & Continue
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function hideModal() {
                    document.getElementById('verificationModal').classList.add('hidden');
                }
            </script>
        @endif --}}

        {{-- Step 2: After code verified, show password fields --}}
    @else
        <form method="post" action="{{ route('profile.password.update') }}" class="mt-6 space-y-6">
            @method('PUT')
            @csrf

            <div>
                <x-input-label for="update_password_password" :value="__('New Password')" />
                <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full"
                    autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                    class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save New Password') }}</x-primary-button>

                @if (session('status') === 'password-updated')
                    <p class="text-sm text-green-600 dark:text-green-400">
                        {{ __('Password updated successfully.') }}
                    </p>
                @endif
            </div>
        </form>
    @endif
</section>


@if (session('status') === 'code-sent')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showModal();
        });

        function showModal() {
            document.getElementById('verificationModal').classList.remove('hidden');
            document.getElementById('verificationModal').classList.add('flex');
        }

        function hideModal() {
            document.getElementById('verificationModal').classList.remove('flex');
            document.getElementById('verificationModal').classList.add('hidden');
        }
    </script>
@endif
