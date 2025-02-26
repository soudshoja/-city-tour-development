<x-app-layout>
    <div class="permission">
        <div class="header flex flex-col justify-start sm:flex-row sm:justify-between bg-white rounded-md p-2 shadow-md dark:bg-gray-800">
            <h1 class="inline-block align-baseline mb-2 sm:mb-0">Permission For {{ ucfirst($role->name) }}</h1>
            <form action="{{ route('role.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class=" m-l-auto grid grid-cols-2 gap-2">
                    <input type="hidden" name="role_id" value="{{ $role->id }}">
                    <button class="btn btn-primary min-w-28" type="submit">Update</button>
                    <a href="{{ route('role.index') }}" class="btn btn-primary min-28">Back</a>
                </div>
        </div>

        @include('role.partials.permission', ['permissions' => $permissions])
        </form>
    </div>
</x-app-layout>