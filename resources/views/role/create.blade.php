<x-app-layout>
    <div class="permission">
        <div class="header flex flex-col justify-start sm:flex-row sm:justify-between bg-white rounded-md p-2 shadow-md">
            <h1 class="inline-block align-baseline mb-2 sm:mb-0">Add Permission For New Role</h1>
            <div class=" m-l-auto grid grid-cols-2 gap-2" x-data="{ open: false }">
                <button class="btn btn-primary min-w-28" @click="open = true">Create</button>
                <a href="{{ route('role.index') }}" class="btn btn-primary min-28">Back</a>

                <div class="fixed z-10 inset-0 overflow-y-auto" x-show="open" x-cloak>
                    <div class="flex items end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0" @click.away="open = false">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>

                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                            <dialog class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" aria-modal="true" aria-labelledby="modal-headline">
                                <form action="{{ route('role.store') }}" method="POST">
                                    @csrf
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-headline">
                                                    Create New Role
                                                </h3>
                                                <div class="mt-2">
                                                    <input type="text" name="name" id="name" class="form-input" placeholder="Role Name">
                                                    <textarea name="description" id="description" class="form-textarea mt-2" placeholder="Role Description"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit" class="btn btn-primary mx-2">Create</button>
                                        <button type="button" @click="open = false" class="btn btn-secondary mx">Cancel</button>
                                    </div>
                            </dialog>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('role.partials.permission', ['permissions' => $permissions])
        </form>
    </div>
</x-app-layout>