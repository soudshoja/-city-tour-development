
    <div class="bg-white rounded-lg h-auto overflow-y-auto chat-container">
        <div class="max-w-md mx-auto bg-white shadow-md rounded-lg">
            <div class="p-4">
                <div id="chat-window" class="mb-3 h-64 overflow-y-scroll border border-gray-300 p-4 rounded-lg bg-gray-50">
                    <!-- Chat messages will be appended here -->
                </div>
                <textarea id="user-input" 
                          class="w-full p-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400" 
                          rows="2" 
                          placeholder="Type your message"></textarea>
                <button id="send-btn" 
                        class="w-full bg-blue-500 text-white py-2 px-4 mt-3 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    Send
                </button>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            const chatWindow = $('#chat-window');

            function appendMessage(role, content) {
                const messageClass = role === 'user' ? 'text-end text-primary' : 'text-start text-success';
                chatWindow.append(`<div class="${messageClass}"><strong>${role}:</strong> ${content}</div>`);
                chatWindow.scrollTop(chatWindow[0].scrollHeight);
            }

            $('#send-btn').on('click', function () {
                const userInput = $('#user-input').val().trim();

                if (!userInput) return;

                // Append user message
                appendMessage('user', userInput);

                // Clear input
                $('#user-input').val('');

                // Send AJAX request
                $.ajax({
                    url: "{{ route('chat.send') }}",
                    method: "POST",
                    data: {
                        messages: [{ role: 'user', content: userInput }],
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (response) {
                        const botMessage = response.choices[0].message.content;
                        appendMessage('cityTour', botMessage);
                    },
                    error: function (error) {
                        console.error(error);
                        appendMessage('cityTour', 'Error: Could not connect to the chatbot.');
                    }
                });
            });
        });
    </script>