<x-app-layout>
    <div class="justify-middle text-center align-center m-2 rounded-md bg-white">
        <div class="flex justify-between p-2">
            <h1 class="text-2xl font-bold">Roles</h1>
            <a href="{{ route('roles.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Create Role</a>
        </div>
    </div>

    @foreach($roles as $role)
    <ul>
        <li>
            {{ $role['name'] }}
        </li>
    </ul>
    @endforeach
</x-app-layout>