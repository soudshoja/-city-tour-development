<x-app-layout>
    <!-- Breadcrumbs -->
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <a href="{{ route('users.index') }}" class="customBlueColor hover:underline">Users</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span class="text-gray-500">{{ $user->name }}</span>
        </li>
    </ul>

    <div class="bg-white rounded-md shadow-md max-h-160 p-6">
        <p class="text-xl font-semibold mb-4">Assign Role</p>

        <!-- Role Update Form -->
        <form action="{{ route('users.role', $user) }}" method="POST" class="mb-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="user_id" value="{{ $user->id }}">

            <div class="flex gap-2 my-2 flex-wrap">
                @foreach ($roles as $role)
                    @if ($role->id != 1)
                        <div
                            class="form-check form-check-inline flex justify-center items-center gap-2 border p-2 rounded-md">
                            <input class="form-check-input role-radio" type="radio" name="role_id"
                                value="{{ $role->id }}" data-role-name="{{ strtolower($role->name) }}"
                                @if ($user->roles->contains($role)) checked @endif>
                            <label class="form-check-label">{{ $role->name }}</label>
                        </div>
                    @endif
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary mt-2">Update Role</button>
        </form>

        <!-- Info Update Form -->
        <form action="{{ route('users.updateInfo', $user) }}" method="POST" id="info-form"
            class="bg-gray-50 p-4 rounded border hidden">
            @csrf
            @method('PUT')

            <input type="hidden" name="user_id" value="{{ $user->id }}">
            <input type="hidden" name="source_role" id="source_role">

            <div class="mb-2">
                <label class="block font-medium">Name:</label>
                <input type="text" name="name" id="info-name" class="form-input w-full">
            </div>

            <div class="mb-2">
                <label class="block font-medium">Email:</label>
                <input type="text" name="email" id="info-email" class="form-input w-full">
            </div>

            <div class="mb-2">
                <label class="block font-medium">Phone:</label>
                <input type="text" name="phone" id="info-phone" class="form-input w-full">
            </div>

            <div class="mb-2">
                <label class="block font-medium">New Password: <small class="text-gray-500">(leave blank to keep current
                        password)</small></label>
                <input type="password" name="info-new-password" id="info-new-password" class="form-input w-full">
            </div>

            <div class="mb-2">
                <label class="block font-medium">Confirm New Password:</label>
                <input type="password" name="info-new-password_confirmation" class="form-input w-full">
            </div>

            <button type="submit" class="btn btn-secondary mt-2">Update Info</button>
        </form>

    </div>

    <!-- Inject role data -->
    <script>
        const company = @json($user->company ?? null);
        const branch = @json($user->branch ?? null);
        const agent = @json($user->agent ?? null);

        const infoForm = document.getElementById('info-form');
        const nameField = document.getElementById('info-name');
        const emailField = document.getElementById('info-email');
        const phoneField = document.getElementById('info-phone');
        const sourceRoleField = document.getElementById('source_role');

        const roleRadios = document.querySelectorAll('.role-radio');

        roleRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                const role = radio.dataset.roleName;
                sourceRoleField.value = role;

                let data = null;
                if (role === 'company') data = company;
                if (role === 'branch') data = branch;
                if (role === 'agent') data = agent;

                if (data) {
                    nameField.value = data.name ?? '';
                    emailField.value = data.email ?? '';
                    phoneField.value = data.phone ?? data.phone_number ?? '';
                    infoForm.classList.remove('hidden');
                } else {
                    nameField.value = '';
                    emailField.value = '';
                    phoneField.value = '';
                    infoForm.classList.add('hidden');
                }
            });

            if (radio.checked) radio.dispatchEvent(new Event('change'));
        });
    </script>
</x-app-layout>
