<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Change Password
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Ensure your account is using a long, random password to stay secure
        </p>
    </header>
    
    @if (session('status') === 'password-updated')
        <div class="mt-4 rounded-md bg-green-100 p-4 text-green-700 dark:bg-green-900 dark:text-green-300">
            Password updated successfully
        </div>
    @endif

    @if (!session('password_update_verified'))
    <form method="post" action="{{ route('profile.password.request-update') }}" class="mt-6 space-y-6">
        @csrf

        <div>
            <x-input-label for="update_password_current_password" :value="__('Please enter your current password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_current_password" name="current_password" type="password"
                    class="block w-full pr-10" autocomplete="current-password" />
                <button type="button"
                    onclick="togglePassword('update_password_current_password', this)"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600">
                    <!-- Eye Open -->
                    <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <!-- Eye Closed -->
                    <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="flex justify-end gap-4">
            <button type="submit" class="btn-primary">Request Verification Code</button>
        </div>
    </form>

    <!-- After code verification, create new password -->
    @else
    <form method="post" action="{{ route('profile.password.update') }}" class="mt-6 space-y-6">
        @method('PUT')
        @csrf

        <div class="flex gap-4 mt-4">
            <div class="flex-1">
                <x-input-label for="update_password_password" :value="__('New Password')" />
                <div class="relative mt-1">
                    <x-text-input id="update_password_password" name="password" type="password"
                        class="block w-full pr-10" autocomplete="new-password" />
                    <button type="button"
                        onclick="togglePassword('update_password_password', this)"
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
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex-1">
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm New Password')" />
                <div class="relative mt-1">
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                        class="block w-full pr-10" autocomplete="new-password" />
                    <button type="button"
                        onclick="togglePassword('update_password_password_confirmation', this)"
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
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-end gap-4">
            <button class="btn-primary">Save</button>
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