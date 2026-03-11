<section>


    <div class="bg-white dark:bg-gray-800 rounded-lg  grid grid-cols-1 md:grid-cols-2  items-center">

        {{-- LEFT COLUMN (Profile Info) --}}
        <div class="flex flex-col justify-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Profile Information') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Update your account's profile information and email address.") }}
            </p>
        </div>



    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
    @csrf
    @method('patch')

    {{-- RIGHT COLUMN (Logo Upload) --}}
    @if ($user->role_id == \App\Models\Role::COMPANY)
    <div class="flex justify-end justify-end pr-6">
        <div class="flex flex-col items-center space-y-4">
            <!-- Logo Preview -->
            <div id="logo-preview-container"
                class="h-24 w-24 border-2 border-gray-300 flex items-center justify-center
                        rounded-md overflow-hidden bg-gray-50 dark:bg-gray-700">
                @if ($user->company && $user->company->logo)
                <img id="logo-preview" src="{{ asset('storage/' . $user->company->logo) }}"
                    alt="Company Logo"
                    class="h-full w-full object-cover">
                @else
                <span id="logo-placeholder" class="text-gray-400 text-3xl">+</span>
                @endif
            </div>

            <!-- File Upload Button -->
            <div>
                <input id="logo" name="logo" type="file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" accept="image/*" onchange="previewLogo(event)" />
                <x-input-error class="mt-2" :messages="$errors->get('logo')" />
            </div>
        </div>
        <input type="hidden" id="logo_processed" name="logo_processed" />
    </div>
    @endif
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)"
                required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        @if ($userEmail)
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email"
                class="mt-1 block w-full" :value="old('email', $userEmail)" required autocomplete="username" />
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
        <div class="mt-4">
            <x-input-label for="phone" :value="__('Phone Number')" />
            <x-text-input id="phone" name="phone" type="text"
                class="mt-1 block w-full" :value="old('phone', $userPhone)" required autofocus autocomplete="phone" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>
        @endif

        @if ($user->role_id == \App\Models\Role::COMPANY)
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="facebook" :value="__('Facebook Page Link')" />
                <x-text-input id="facebook" name="facebook" type="url" placeholder="https://www.facebook.com/"
                    class="mt-1 block w-full"
                    :value="old('facebook', optional($user->company)->facebook)" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('facebook')" />
            </div>
            <div>
                <x-input-label for="instagram" :value="__('Instagram Profile Link')" />
                <x-text-input id="instagram" name="instagram" type="url" placeholder="https://www.instagram.com/"
                    class="mt-1 block w-full"
                    :value="old('instagram', optional($user->company)->instagram)" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('instagram')" />
            </div>
            <div>
                <x-input-label for="snapchat" :value="__('Snapchat Profile Link')" />
                <x-text-input id="snapchat" name="snapchat" type="url" placeholder="https://www.snapchat.com/add/"
                    class="mt-1 block w-full"
                    :value="old('snapchat', optional($user->company)->snapchat)" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('snapchat')" />
            </div>
            <div>
                <x-input-label for="tiktok" :value="__('TikTok Profile Link')" />
                <x-text-input id="tiktok" name="tiktok" type="url" placeholder="https://www.tiktok.com/"
                    class="mt-1 block w-full"
                    :value="old('tiktok', optional($user->company)->tiktok)" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('tiktok')" />
            </div>
            <div>
                <x-input-label for="whatsapp" :value="__('WhatsApp Link')" />
                <x-text-input id="whatsapp" name="whatsapp" type="url" placeholder="https://wa.me/"
                    class="mt-1 block w-full"
                    :value="old('whatsapp', optional($user->company)->whatsapp)" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('whatsapp')" />
            </div>
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

        <div class="flex justify-end p-6">
            <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
        </div>
    </form>

  <script>

    let processedLogoBase64 = null;

    async function previewLogo(event) {
        const input = event.target;
        const container = document.getElementById('logo-preview-container');
        const file = input.files[0];

        if (file) {
            const reader = new FileReader();
            reader.onload = async function(e) {
                const img = new Image();
                img.onload = async function() {
                    if (img.width > 700 || img.height > 400) {
                        alert('Image must be at most 700x400 pixels.');
                        input.value = '';
                        return;
                    }
                    container.innerHTML = '<span class="text-gray-400 text-sm">Removing background...</span>';

                    const formData = new FormData();
                    formData.append('image_file', file);
                    formData.append('size', 'auto');

                    try {
                        const response = await fetch('https://api.remove.bg/v1.0/removebg', {
                            method: 'POST',
                            headers: {
                                'X-Api-Key': '5YxnoUsbBA9kJfNnSbJVgVjW', // Replace with your API key
                            },
                            body: formData
                        });

                        if (!response.ok) throw new Error('Background removal failed');

                        const blob = await response.blob();
                        const url = URL.createObjectURL(blob);

                        const resultImg = new Image();
                        resultImg.onload = function() {
                            container.innerHTML = '';
                            resultImg.classList.add('h-full', 'w-full', 'object-cover');
                            container.appendChild(resultImg);
                        };
                        resultImg.src = url;

                        // Convert blob → Base64 and store in hidden field
                        const reader2 = new FileReader();
                        reader2.onloadend = function() {
                            processedLogoBase64 = reader2.result;
                            document.getElementById('logo_processed').value = processedLogoBase64;
                        };
                        reader2.readAsDataURL(blob);

                    } catch (error) {
                        alert('Could not remove background: ' + error.message);
                        container.innerHTML = '<span class="text-gray-400 text-3xl">+</span>';
                        input.value = '';
                        processedLogoBase64 = null;
                        document.getElementById('logo_processed').value = '';
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    // Ensure processed image is submitted instead of original

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[action="{{ route('profile.update') }}"]');
        form.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('logo');
            if (fileInput.value && !processedLogoBase64) {
                e.preventDefault();
                alert('Please wait for the background to be removed from your logo before saving.');
            }

            // ⚡ Remove original file from submission if processed exists
            if (processedLogoBase64) {
                fileInput.removeAttribute('name'); // prevent raw file upload
            }
        });
    });
</script>

</section>