<x-modal id="tbo-credentials" name="tbo-credentials" maxWidth="md" :show="true">
    <div class="header py-4 px-2 flex gap-2 justify-between">
        <p class="text-sm">
            Please confirm which TBO credentials you would like to use
        </p>
        <button x-on:click="show = false">&times</button>
    </div>
    <hr>
    <form id="tbo-credentials-form" action="{{ route('suppliers.tbo.credentials') }}" method="POST">
        <div class="p-2 grid grid-cols-1 gap-2 w-full">
            @csrf
            @if(env('TBO_URL') !== null)
            <p>
                Live Credentials
            </p> 
            <div id="live" class="grid grid-cols-1 gap-2 p-2 border border-gray-500 rounded-lg cursor-pointer hover:bg-gray-100">
                <div class="flex justify-between">
                    <p class="overflow-hidden mr-4" data-url="{{ env('TBO_URL') }}">
                        {{ env('TBO_URL') }}
                    </p>
                    <div id="uncheck">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.0303 10.0303C16.3232 9.73744 16.3232 9.26256 16.0303 8.96967C15.7374 8.67678 15.2626 8.67678 14.9697 8.96967L10.5 13.4393L9.03033 11.9697C8.73744 11.6768 8.26256 11.6768 7.96967 11.9697C7.67678 12.2626 7.67678 12.7374 7.96967 13.0303L9.96967 15.0303C10.2626 15.3232 10.7374 15.3232 11.0303 15.0303L16.0303 10.0303Z" fill="#1C274C" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.25C6.06294 1.25 1.25 6.06294 1.25 12C1.25 17.9371 6.06294 22.75 12 22.75C17.9371 22.75 22.75 17.9371 22.75 12C22.75 6.06294 17.9371 1.25 12 1.25ZM2.75 12C2.75 6.89137 6.89137 2.75 12 2.75C17.1086 2.75 21.25 6.89137 21.25 12C21.25 17.1086 17.1086 21.25 12 21.25C6.89137 21.25 2.75 17.1086 2.75 12Z" fill="#1C274C" />
                        </svg>
                    </div>
                    <div id="checked" class="hidden">
                        <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="fill-green-500">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM16.0303 8.96967C16.3232 9.26256 16.3232 9.73744 16.0303 10.0303L11.0303 15.0303C10.7374 15.3232 10.2626 15.3232 9.96967 15.0303L7.96967 13.0303C7.67678 12.7374 7.67678 12.2626 7.96967 11.9697C8.26256 11.6768 8.73744 11.6768 9.03033 11.9697L10.5 13.4393L12.7348 11.2045L14.9697 8.96967C15.2626 8.67678 15.7374 8.67678 16.0303 8.96967Z" />
                        </svg>
                    </div>
                </div>
                <input type="text" value="{{ env('TBO_USERNAME') }}" class="w-full border border-gray-300 p-2 rounded-lg" disabled>
                <input type="password" value="{{ env('TBO_PASSWORD') }}" class="w-full border border-gray-300 p-2 rounded-lg" disabled>
            </div>
            @endif
            @if(env('TBO_URL') !== null)
            <p>
                Sandbox Credentials
            </p>
            <div id="sandbox" class="grid grid-cols-1 gap-2 p-2 border border-gray-500 rounded-lg cursor-pointer hover:bg-gray-100">
                <div class="flex justify-between">
                    <p class="overflow-hidden mr-4" data-url="{{ env('TBO_SANDBOX_URL') }}">
                        {{ env('TBO_SANDBOX_URL') }}
                    </p>
                    <div id="uncheck">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.0303 10.0303C16.3232 9.73744 16.3232 9.26256 16.0303 8.96967C15.7374 8.67678 15.2626 8.67678 14.9697 8.96967L10.5 13.4393L9.03033 11.9697C8.73744 11.6768 8.26256 11.6768 7.96967 11.9697C7.67678 12.2626 7.67678 12.7374 7.96967 13.0303L9.96967 15.0303C10.2626 15.3232 10.7374 15.3232 11.0303 15.0303L16.0303 10.0303Z" fill="#1C274C" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.25C6.06294 1.25 1.25 6.06294 1.25 12C1.25 17.9371 6.06294 22.75 12 22.75C17.9371 22.75 22.75 17.9371 22.75 12C22.75 6.06294 17.9371 1.25 12 1.25ZM2.75 12C2.75 6.89137 6.89137 2.75 12 2.75C17.1086 2.75 21.25 6.89137 21.25 12C21.25 17.1086 17.1086 21.25 12 21.25C6.89137 21.25 2.75 17.1086 2.75 12Z" fill="#1C274C" />
                        </svg>
                    </div>
                    <div id="checked" class="hidden">
                        <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="fill-green-500">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM16.0303 8.96967C16.3232 9.26256 16.3232 9.73744 16.0303 10.0303L11.0303 15.0303C10.7374 15.3232 10.2626 15.3232 9.96967 15.0303L7.96967 13.0303C7.67678 12.7374 7.67678 12.2626 7.96967 11.9697C8.26256 11.6768 8.73744 11.6768 9.03033 11.9697L10.5 13.4393L12.7348 11.2045L14.9697 8.96967C15.2626 8.67678 15.7374 8.67678 16.0303 8.96967Z" />
                        </svg>
                    </div>
                </div>
                <input type="text" value="{{ env('TBO_SANDBOX_USERNAME') }}" class="w-full border border-gray-300 p-2 rounded-lg" disabled>
                <input type="password" value="{{ env('TBO_SANDBOX_PASSWORD') }}" class="w-full border border-gray-300 p-2 rounded-lg" disabled>
            </div>
            @endif
            @if(env('TBO_URL') === null && env('TBO_SANDBOX_URL') === null)
            <div class="flex justify-center">
                <p class="text-sm">No TBO credentials found</p>
            </div>
            @endif
        </div>
    </form>
    <hr>
    <div class="flex justify-center py-4">
        <x-primary-button id="tbo-credentials-button">
            {{ __('Save') }}
        </x-primary-button>
    </div>
    <script>
        let tboModal = document.getElementById('tbo-credentials');
        let liveDiv = document.getElementById('live');
        let sandboxDiv = document.getElementById('sandbox');
        let supplierIndexUrl = "{!! route('suppliers.index') !!}";

        console.log(tboModal.style);
        console.log(tboModal.style.display);

        liveDiv.addEventListener('click', () => {
            liveDiv.querySelector('#uncheck').classList.add('hidden');
            liveDiv.querySelector('#checked').classList.remove('hidden');
            sandboxDiv.querySelector('#uncheck').classList.remove('hidden');
            sandboxDiv.querySelector('#checked').classList.add('hidden');
        });

        sandboxDiv.addEventListener('click', () => {
            sandboxDiv.querySelector('#uncheck').classList.add('hidden');
            sandboxDiv.querySelector('#checked').classList.remove('hidden');
            liveDiv.querySelector('#uncheck').classList.remove('hidden');
            liveDiv.querySelector('#checked').classList.add('hidden');
        });

        let tboCredentialsButton = document.getElementById('tbo-credentials-button');

        tboCredentialsButton.addEventListener('click', (e) => {
            e.preventDefault();
            let liveChecked = liveDiv.querySelector('#checked').classList.contains('hidden') ? false : true;
            let sandboxChecked = sandboxDiv.querySelector('#checked').classList.contains('hidden') ? false : true;

            let url = '';
            let username = '';
            let password = '';

            if (liveChecked) {

                liveDiv.querySelectorAll('input').forEach((input) => {
                    url = liveDiv.querySelector('p').getAttribute('data-url');
                    if (input.type === 'text') {
                        username = input.value;
                    } else if (input.type === 'password') {
                        password = input.value;
                    }
                });

            } else if (sandboxChecked) {

                sandboxDiv.querySelectorAll('input').forEach((input) => {
                    url = sandboxDiv.querySelector('p').getAttribute('data-url');
                    if (input.type === 'text') {
                        username = input.value;
                    } else if (input.type === 'password') {
                        password = input.value;
                    }
                });

            } else {
                alert('Please select a TBO credentials');
                return;
            }

            if(liveChecked || sandboxChecked) {
                console.log('submit');
                let form = document.getElementById('tbo-credentials-form');
                
                form.innerHTML += `<input type="hidden" name="url" value="${url}">`;
                form.innerHTML += `<input type="hidden" name="username" value="${username}">`;
                form.innerHTML += `<input type="hidden" name="password" value="${password}">`;

                form.submit();
            }
        });

        document.addEventListener('click', (e) => {
            let isModalClosed = $('#tbo-credentials').is(':hidden');
            if (isModalClosed) {
                console.log('modal is closed');
                window.location.href = supplierIndexUrl;
                alert('You have to select a TBO credentials to proceed');
            }

        });
    </script>
</x-modal>