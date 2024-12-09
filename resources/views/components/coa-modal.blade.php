@props(['type', 'color'])

<div id="{{ strtolower($type) }}-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md mx-auto">
        <h2 class="text-xl font-bold mb-4">Create {{ $type }} Account</h2>
        <form id="{{ strtolower($type) }}-form" class="flex flex-col">
            <label for="accountName" class="mr-2 text-sm font-medium text-gray-700">Account Name</label>
            <input type="text" name="accountName" required class="block border border-gray-300 rounded-md p-2 w-full mb-4">

            <div class="flex items-center space-x-2">
                <button type="submit" style="background-color: #{{ $color }}" class="text-white px-4 py-2 rounded">Create</button>
                <button type="button" class="close-modal bg-gray-300 text-gray-700 px-4 py-2 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div>
