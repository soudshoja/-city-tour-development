<x-app-layout>
    <div class="p-2">
        <div class="flex flex-col md:flex-row">
            <div class="w-full md:w-1/3">
                <div class="bg-white shadow-md rounded px-8 py-4 mb-4">
                    <h1 class="text-2xl font-bold text-gray-700">OpenAI</h1>
                </div>
                <div class="flex flex-col items-center bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                    <input type="text" name="prompt" id="prompt" placeholder="Enter your prompt here" class="border border-gray-400 p-2 mb-2 rounded-lg w-6/12">
                    <div id="response-container inline-flex justify-start">
                        <div class="font-bold text-sm mt-2">
                            Start by entering a prompt and click the enter or send button.
                        </div>
                    </div>
                    <button id="send" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-2">
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        // // Initialize a new EventSource connection to the streaming route
        // const eventSource = new EventSource("{{ route('open-ai.store') }}");

        // // Handle incoming messages
        // eventSource.onmessage = function(event) {
        //     // Parse the event data as JSON (assuming the API returns JSON data)
        //     const data = event.data ? JSON.parse(event.data) : null;

        //     // Update the HTML by appending each new message
        //     if (data) {
        //         const responseContainer = document.getElementById("response-api");
        //         const messageDiv = document.createElement("div");
        //         messageDiv.textContent = data.content; // Display the message content
        //         responseContainer.appendChild(messageDiv);
        //     }
        // };

        // // Handle any errors that may occur
        // eventSource.onerror = function(error) {
        //     console.error("Error with SSE connection:", error);
        //     eventSource.close(); // Close the connection if there’s an error
        // };

        async function sendPrompt(prompt) {
            // Send POST request to the server with the prompt
            const response = await fetch("{{ route('open-ai.store') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}" // Include CSRF token for security
                },
                body: JSON.stringify({
                    prompt: prompt
                })
            });

            console.log(response);

            if (!response.body) {
                console.error("Stream response is empty.");
                return;
            }

            // Clear previous response
            const responseContainer = document.getElementById("response-container");
            responseContainer.innerHTML = "";

            // Read the streamed response as it arrives
            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");

            while (true) {
                const {
                    done,
                    value
                } = await reader.read();
                if (done) break; // End of stream

                // Decode and parse the streamed chunk
                const chunk = decoder.decode(value);
                const data = JSON.parse(chunk.trim());

                // Display the response content
                const messageDiv = document.createElement("div");
                messageDiv.textContent = data.content;
                responseContainer.appendChild(messageDiv);
            }
        }

        // Event listener for the button click
        document.getElementById("send").addEventListener("click", function() {
            const prompt = document.getElementById("prompt").value;
            if (prompt) {
                sendPrompt(prompt);
            }
        });
    </script>
</x-app-layout>