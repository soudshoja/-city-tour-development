<div
    x-cloak
    x-show="credentialModal_{{ $supplier->id }}"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
    <div
        @click.away="credentialModal_{{ $supplier->id }} = false"
        class="bg-white dark:bg-gray-800 rounded-md shadow-md">
        <div class="p-2">
            <h1 class="font-bold">
                Credentials for {{$supplier->name}} supplier
            </h1>
            @if($supplier->credentials->isEmpty())
            <p class="text-red-500">You don't have any credentials for supplier yet</p>
            @endif
        </div>
        <hr>
        <form id="store-credential_{{ $supplier->id }}" class="p-2 flex flex-col gap-2" action="{{ route('credentials.store') }}" method="POST">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
            <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">
            <input type="hidden" name="type" value="{{ $supplier->auth_method }}">
            <div class="p-2 bg-gray-300 text-gray-600 font-semibold rounded border border-gray-500 text-start">
                {{ $supplier->auth_method }}
            </div>
            <div class="basic {{ $supplier->auth_method == 'oauth' ? 'hidden' : '' }}">
                <input type="text" name="username" id="username_{{ $supplier->id }}" placeholder="Username" class="border border-gray-300 rounded-lg p-2 mb-2 w-full" value="{{ old('username') ?? $supplier->credentials->first()?->username }}">
                <input type="password" name="password" id="password_{{ $supplier->id }}" placeholder="Password" class="border border-gray-300 rounded-lg p-2 mb-2 w-full" value="{{ old('password') ?? $supplier->credentials->first()?->password }}">
            </div>
            <div class="oauth {{ $supplier->auth_method == 'basic' ? 'hidden' : '' }}">
                <input type="text" name="client_id" id="client_id_{{ $supplier->id }}" placeholder="Client ID" class="border border-gray-300 rounded-lg p-2 mb-2 w-full">
                <input type="password" name="client_secret" id="client_secret_{{ $supplier->id }}" placeholder="Client Secret" class="border border-gray-300 rounded-lg p-2 mb-2 w-full">
            </div>
        </form>
        <div class="p-2 flex justify-center gap-2">
            <button class="bg-green-700 text-white px-2 py-1 rounded" type="submit" form="store-credential_{{ $supplier->id }}">Save</button>
            <button @click="credentialModal_{{ $supplier->id }}=false" class="bg-red-700 text-white px-2 py-1 rounded">Cancel</button>
        </div>
    </div>
</div>