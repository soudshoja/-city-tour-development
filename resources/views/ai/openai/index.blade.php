<x-app-layout>
    <style>
        /* Add some basic styling for the loading spinner */
        .spinner {
            display: none;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #000;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin: 0 auto;
            margin-top: 1em;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        #response-container {
            display: block;
            /* Change from inline-flex to block for proper word wrapping */
            text-align: left;
            justify-items: center;
            white-space: normal;
            /* Allow line breaks */
            word-wrap: break-word;
            /* Enable word wrapping */
            overflow-wrap: break-word;
            /* Allow long words to wrap */
            max-width: 100%;
            /* Prevent container from exceeding screen width */
            overflow-y: auto;
            /* Enable scrolling for overflow content */
            padding: 10px;
            font-weight: 700;
            max-height: 300px;
            /* Set a maximum height for scrolling */
        }
    </style>
    <div class="h-160 p-2">
        <livewire:chat />
    </div>
    <script>
        async function sendPrompt(prompt) {
            let loading = document.getElementById("loading-spinner");
            loading.style.display = "flex";

            loading.classList.add("justify-items-center");



            // Send POST request to the server with the prompt
            const response = await fetch("{{ route('open-ai.store') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    prompt: prompt
                })
            });

            const data = await response.json();
            const responseText = data.choices[0].message.content;

            document.getElementById("loading-spinner").style.display = "none";
            // Simulate streaming by chunking the response
            simulateStreaming(responseText);
        }

        function simulateStreaming(text) {
            const responseContainer = document.getElementById("response-container");
            responseContainer.innerHTML = ""; // Clear previous response

            // Split text into words instead of characters
            const words = text.split(" ");
            let currentIndex = 0;

            function displayNextChunk() {
                if (currentIndex < words.length) {
                    // Append the next word with a space
                    const word = words[currentIndex];
                    const messageDiv = document.createElement("span");
                    messageDiv.textContent = word + " ";
                    responseContainer.appendChild(messageDiv);

                    // Move to the next word
                    currentIndex++;

                    // Set delay for the next word
                    setTimeout(displayNextChunk, 30); // Adjust delay as needed
                }
            }

            displayNextChunk(); // Start streaming simulation
        }

        document.getElementById("send").addEventListener("click", function() {
            const prompt = document.getElementById("prompt").value;
            if (prompt) {
                sendPrompt(prompt);
            }
        });
    </script>
</x-app-layout>