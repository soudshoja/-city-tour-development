<x-app-layout>
    <div class="bg-white rounded-md p-2">
        <div class="charge-header flex justify-between">
            <h2 class="text-xl font-semibold">Charges</h2>
            <a href="{{ route('charges.create') }}" class="btn btn-primary">Add Charge</a>
        </div>
        <div class="charge-body md:block">
            <table class="hidden md:block w-full mt-4">
                <thead>
                    <tr class="">
                        <th class="">Charge ID</th>
                        <th class="">Charge Name</th>
                        <th class="">Charge Type</th>
                        <th class="">Description</th>
                        <th class="">Amount</th>
                        <th class="">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charges as $charge)
                    <tr class="hover:bg-gray-100 md:hover:bg-transparent">
                        <td class="">{{ $charge->id }}</td>
                        <td class="">{{ $charge->name }}</td>
                        <td class="">{{ $charge->type }}</td>
                        <td class="">{{ $charge->description }}</td>
                        <td class="">{{ $charge->amount }}</td>
                        <td class="flex flex-col gap-2">
                            <a href="{{ route('charges.edit', $charge->id) }}" class="btn btn-primary">Edit</a>
                            <form action="{{ route('charges.destroy', $charge->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="md:hidden">
                <thead>
                    <tr class="">
                        <th class="">Charge Id</th>
                        <th class="">Charge Name</th>
                        <th class="">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charges as $charge)
                    <tr class="border-b-2 border-gray-100">
                        <td class="">{{ $charge->id }}</td>
                        <td class="">{{ $charge->name }}</td>
                        <td class="">{{ $charge->amount }}</td>
                    </tr>
                    <tr class="md:hidden">
                        <td class="flex justify-between">
                            <div class="flex gap-2 h-10" x-data="{chargeDetails: false}">
                                <x-primary-button @click="chargeDetails = !chargeDetails">
                                    Details
                                </x-primary-button>
                                <div x-show="chargeDetails" x-cloak
                                    class="modal fixed inset-0 px-4 py-6 sm:px-0 z-50 bg-gray-400 bg-opacity-50 transition-all transform" role="dialog" style="display: block;">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                        <div class="bg-white rounded-md p-4 overflow-hidden w-full" @click.away="chargeDetails = false">
                                            <div class="header flex justify-between rounded-md bg-gray-200 p-2 align-middle">
                                                <h2 class="text-xl font-semibold">Charge Details</h2>
                                                <button @click="chargeDetails = false">
                                                    <svg width="27px" height="27px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                                        <g id="SVGRepo_iconCarrier">
                                                            <path d="M14.5 9.50002L9.5 14.5M9.49998 9.5L14.5 14.5" stroke="#000000" stroke-width="1.5" stroke-linecap="round"></path>
                                                            <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="#000000" stroke-width="1.5" stroke-linecap="round"></path>
                                                        </g>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="pt-4 pb-4 grid">
                                                <div class="flex justify-between my-2">
                                                    <div class="">
                                                        <p class="font-semibold">Charge ID</p>
                                                        <p>{{ $charge->id }}</p>
                                                    </div>
                                                    <div class="">
                                                        <p class="font-semibold">Charge Name</p>
                                                        <p>{{ $charge->name }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between gap-2">
                                                    <div class="">
                                                        <p class="font-semibold">Charge Type</p>
                                                        <p>{{ $charge->type }}</p>
                                                    </div>
                                                    <div class="">
                                                        <p class="font-semibold">Description</p>
                                                        <p>{{ $charge->description }}</p>
                                                    </div>
                                                </div>
                                                <div class="">
                                                    <label for="amount">Amount</label>
                                                    <div class="flex">
                                                        <span class="rounded-l-md bg-gray-200 p-2 border-black border border-r-0">$</span>
                                                        <input type="text" class="rounded-r-md w-full" name="amount" value="{{ $charge->amount }}">
                                                    </div>
                                                </div>
                                                <div class="flex justify-end mt-2">
                                                    <a href="{{ route('charges.edit', $charge->id) }}" class="btn btn-primary">Edit</a>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <form action="{{ route('charges.destroy', $charge->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger h-10">
                                        <svg width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                            <g id="SVGRepo_iconCarrier">
                                                <path d="M18 6L17.1991 18.0129C17.129 19.065 17.0939 19.5911 16.8667 19.99C16.6666 20.3412 16.3648 20.6235 16.0011 20.7998C15.588 21 15.0607 21 14.0062 21H9.99377C8.93927 21 8.41202 21 7.99889 20.7998C7.63517 20.6235 7.33339 20.3412 7.13332 19.99C6.90607 19.5911 6.871 19.065 6.80086 18.0129L6 6M4 6H20M16 6L15.7294 5.18807C15.4671 4.40125 15.3359 4.00784 15.0927 3.71698C14.8779 3.46013 14.6021 3.26132 14.2905 3.13878C13.9376 3 13.523 3 12.6936 3H11.3064C10.477 3 10.0624 3 9.70951 3.13878C9.39792 3.26132 9.12208 3.46013 8.90729 3.71698C8.66405 4.00784 8.53292 4.40125 8.27064 5.18807L8 6M14 10V17M10 10V17" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </g>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>