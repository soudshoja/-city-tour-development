<x-app-layout>
    <div class="my-3">
        <div class="flex items-center space-x-4 mb-6">
            <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                <a href="{{ route('accounting.fixed-assets.show', $asset) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                        <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                    </svg>
                </a>
            </div>
            <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Edit {{ $asset->name }}</h2>
        </div>

        <form method="POST" action="{{ route('accounting.fixed-assets.update', $asset) }}">
            @csrf
            @method('PUT')
            @include('accounting.fixed-assets._form')
        </form>
    </div>
</x-app-layout>
