<x-app-layout>
    <div class="header p-2 bg-gray-300 rounded-md my-2">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('OpenAI - Tools') }}
        </h2>
    </div>
    <div class="body bg-white p-2 rounded-md shadow-md">
        <form action="">
            <label for="function-name">Function Name</label>
            <input type="text" name="function-name" id="function-name" class="w-full p-2 rounded-md my-2">
            <label for="function-description">Function Description</label>
            <textarea name="function-description" id="function-description" class="w-full p-2 rounded-md my-2"></textarea>
            <select name="strict" id="strict" class="w-full p-2 rounded-md my-2">
                <option value="true">True</option>
                <option value="false">False</option>
            </select>
            <button class="bg-blue-500 text-white p-2 rounded-md my-2">Submit</button>
            <label for="parameters">Parameters</label>
            <input type="checkbox" name="parameters" id="parameters">
            <div id="parameters-input" class="hidden">
                <label for="parameter-name">Parameter Name</label>
                <input type="text" name="parameter-name" id="parameter-name" class="w-full p-2 rounded-md my-2">
                <label for="parameter-type">Parameter Type</label>
                <input type="text" name="parameter-type" id="parameter-type" class="w-full p-2 rounded-md my-2">
            </div>

            <script>
                document.getElementById('parameters').addEventListener('change', function() {
                    var parametersInput = document.getElementById('parameters-input');
                    if (this.checked) {
                        parametersInput.classList.remove('hidden');
                    } else {
                        parametersInput.classList.add('hidden');
                    }
                });
            </script>
        </form> 
    </div>
</x-app-layout>