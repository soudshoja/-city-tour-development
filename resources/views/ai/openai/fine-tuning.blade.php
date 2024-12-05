<x-app-layout>
    <div class="header">
        <div class="bg-white rounded-md shadow-md mb-2">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-700">Fine-tuning</h2>
            </div>
        </div>
    </div>
    <div class="body p-2 bg-white rounded-md shadow-md flex flex-col m-auto">
        <div class="sample">
            <label for="sample" class="text-lg font-semibold text-gray-700">Sample</label>
            <textarea name="sample" id="sample">
            </textarea>
        </div>
        <div class="completion">
            <label for="completion" class="text-lg font-semibold text-gray-700">Completion</label>
            <textarea name="completion" id="completion">
            </textarea>
        </div>
    </div>
</x-app-layout>