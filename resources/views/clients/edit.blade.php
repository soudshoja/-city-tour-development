<x-app-layout>
    <div>
        <x-breadcrumbs :breadcrumbs="[
            ['url' => route('dashboard'), 'label' => 'Dashboard'],
            ['url' => route('clients.index'), 'label' => 'Clients List'],
            ['label' => 'Update Client']
        ]" />
        <div class="bg-white rounded-md shadow-md p-4 m-2">
            <form action=" {{ route('clients.update', $client->id) }} " method="POST" class="w-full flex flex-col gap-2 justify-start items-center" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="text" name="name" id="name" value="{{ $client->first_name }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Name">
                <input type="email" name="email" id="email" value="{{ $client->email }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Email">
                <input type="text" name="phone_number" id="phone_number" value="{{ $client->phone }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Phone Number">
                @can('clientAgent', App\Models\Client::class)
                <select name="agent_id" id="agent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}" {{ $client->agent_id == $agent->id ? 'selected' : '' }}>
                        {{ $agent->name }}
                    </option>
                    @endforeach
                </select>
                @endcan
                <textarea name="address" id="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Address">{{ $client->address }}</textarea>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" name="passport_no" id="passport_no" value="{{ $client->passport_no ?? 'N\A'}}" disabled>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" name="civil_no" id="civil_no" value="{{ $client->civil_no ?? 'N\A'}}" disabled>
                <div
                    id="file-container"
                    class="border-2 border-dashed border-gray-400 rounded-md w-full w-full flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48"
                    ondrop="dropHandler(event);"
                    ondragover="dragOverHandler(event);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5" stroke="#1C274C" stroke-width="1.5" />
                        <path d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z" stroke="#1C274C" stroke-width="1.5" />
                    </svg>
                    <input type="file" name="file" id="file" class="hidden">
                    <p id="file-name">
                        You can drag and drop a file here
                    </p>
                    <label for="file" class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500">
                        Upload File
                    </label>
                </div>
                <div class="w-full flex justify-end">
                    <x-primary-button class="font-semibold">
                        Update Client
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const file = document.getElementById('file');
        const fileName = document.getElementById('file-name');
        file.addEventListener('change', (e) => {
            fileName.textContent = e.target.files[0].name;
            file.innerHTML = '';
            let img = document.createElement('img');
            img.src = URL.createObjectURL(e.target.files[0]);
            console.log(img.src);  
            img.width = 100;
            img.height = 100;
            file.appendChild(img);
        });

        dropHandler = (e) => {
            e.preventDefault();
            file.files = e.dataTransfer.files;
            fileName.textContent = e.dataTransfer.files[0].name;
        }

        dragOverHandler = (e) => {
            console.log('File in drop area');
            e.preventDefault();
        }
    </script>
</x-app-layout>