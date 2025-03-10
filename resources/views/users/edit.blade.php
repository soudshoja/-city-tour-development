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
    <!-- ./Breadcrumbs -->
    <div class="bg-white rounded-md shadow-md max-h-160 p-6">
        <p>Assign Role</p>
        <div class="px-2">
            <form action="{{ route('users.role', $user) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <div class="flex gap-2 my-2">
                    @foreach ($roles as $role)
                        @if ($role->id != 1)
                            <div
                                class="form-check form-check-inline flex justify-center items-center gap-2 border p-2 rounded-md">
                                <input class="form-check-input" type="radio" name="role_id"
                                    value="{{ $role->id }}" @if ($user->roles->contains($role)) checked @endif>
                                <label class="form-check-label"
                                    for="inlineRadio{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                        @endif
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
</x-app-layout>
