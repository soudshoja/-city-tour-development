<div class="container mx-auto mt-5">
        <!-- Chat Section -->
        <div class="bg-white shadow-md rounded-lg mb-6">
            <div class="bg-blue-500 text-white px-4 py-2 rounded-t-lg font-semibold">
                Chat
            </div>
            <div class="p-4">
                <div id="chat-log" class="mb-4 overflow-y-auto border border-gray-300 rounded-lg p-3" style="max-height: 300px;">
                    <!-- Chat messages will appear here -->
                </div>
                <div class="flex">
                    <input id="user-message" type="text" class="flex-grow border border-gray-300 rounded-l-lg p-2" placeholder="Type your message...">
                    <button id="send-message" class="bg-blue-500 text-white px-4 py-2 rounded-r-lg hover:bg-blue-600">Send</button>
                </div>
            </div>
        </div>

        <!-- Task Selection Section -->
        <div id="task-selection" class="bg-white shadow-md rounded-lg mb-6 hidden">
             <div class="bg-green-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
                <span>Task Selection</span>
                <button id="close-task-selection" class="text-white hover:text-gray-200">&times;</button>
            </div>
            <div class="p-4">
                <p class="mb-4">Select tasks to include in the invoice:</p>
                <ul id="task-list" class="space-y-2">
                    <!-- Tasks will be dynamically loaded here -->
                </ul>
                <button id="confirm-tasks" class="mt-4 bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">Confirm Tasks</button>
            </div>
        </div>

        <!-- Task Pricing Section -->
        <div id="task-pricing" class="bg-white shadow-md rounded-lg mb-6 hidden">
            <div class="bg-yellow-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
                <span>Task Pricing</span>
                <button id="close-task-pricing" class="text-white hover:text-gray-200">&times;</button>
            </div>
            <div class="p-4">
                <p class="mb-4">Enter invoice prices for selected tasks:</p>
                <form id="pricing-form" class="space-y-4">
                    <div id="pricing-fields" class="space-y-2">
                        <!-- Pricing fields will be dynamically loaded here -->
                    </div>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Generate Invoice</button>
                </form>
            </div>
        </div>
    </div>


    <script>
        const chatLog = $("#chat-log");
        const userMessageInput = $("#user-message");
        const sendMessageButton = $("#send-message");
        const taskSelection = $("#task-selection");
        const taskList = $("#task-list");
        const confirmTasksButton = $("#confirm-tasks");
        const taskPricing = $("#task-pricing");
        const pricingFields = $("#pricing-fields");

        let selectedTasks = [];

        function appendMessage(role, content) {
            const messageClass = role === "user" ? "text-end" : "text-start";
            const message = `<div class="${messageClass}"><strong>${role}:</strong> ${content}</div>`;
            chatLog.append(message);
            chatLog.scrollTop(chatLog.prop("scrollHeight"));
        }

        sendMessageButton.on("click", function () {
        const userMessage = userMessageInput.val().trim();
        if (!userMessage) return;

            appendMessage("user", userMessage);

            $.ajax({
                url: "{{ route('chat.process') }}",
                method: "POST",
                data: {
                    messages: [{ role: "user", content: userMessage }],
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),  // Ensure CSRF token is included
                },
                success: function (response) {
                    if (response.tasks) {
                        loadTaskSelection(response.tasks);
                    } else if (response.taskPricing) {
                        loadTaskPricing(response.taskPricing);
                    } else {
                        const botMessage = response.choices[0].message.content;
                        appendMessage('cityTour', botMessage);
                    }
                },
                error: function (xhr) {
                    appendMessage("chatbot", "Error: " + (xhr.responseJSON?.error || xhr.statusText));
                },
            });

            userMessageInput.val("");
        });

        function loadTaskSelection(tasks) {
            taskList.empty();
            taskSelection.show();
            tasks.forEach(task => {
                const listItem = `
                    <li class="list-group-item">
                        <input type="checkbox" class="form-check-input me-2" data-task-id="${task.id}">
                        ${task.description} (Client: ${task.client})
                    </li>`;
                taskList.append(listItem);
            });
        }

        confirmTasksButton.on("click", function () {
            selectedTasks = taskList.find("input[type='checkbox']:checked").map(function () {
                return parseInt($(this).data("task-id"));
            }).get();

            if (selectedTasks.length === 0) {
                alert("Please select at least one task.");
                return;
            }

            $.ajax({
                url: "{{ route('chat.select') }}",
                method: "POST",
                data: {
                    tasks: selectedTasks  // Send selected task IDs
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),  // Ensure CSRF token is included
                },
                success: function (response) {
                    loadTaskPricing(response.taskPricing);
                },
                error: function (xhr) {
                    alert("Error: " + (xhr.responseJSON?.error || xhr.statusText));
                },
            });

            taskSelection.hide();
        });

        function loadTaskPricing(tasks) {
            pricingFields.empty();
            taskPricing.show();
            tasks.forEach(task => {
                const field = `
                    <div class="mb-3">
                        <label class="form-label">${task.description} (Client: ${task.client} Price: ${task.taskprice})</label>
                        <input type="number" class="form-control" name="task-${task.id}" placeholder="Enter price">
                    </div>`;
                pricingFields.append(field);
            });
        }

        $("#pricing-form").on("submit", function (event) {
            event.preventDefault();
            const tasks = selectedTasks.map(id => {
                return {
                    id,
                    invprice: parseFloat($(`input[name='task-${id}']`).val()),
                };
            });

            $.ajax({
                url: "{{ route('chat.create') }}",
                method: "POST",
                contentType: 'application/json',
                data: JSON.stringify({ tasks }),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),  // Ensure CSRF token is included
                },
                success: function (response) {
                     if (response.success) {
                             const generatedLink = response.invoiceLink;
                             appendMessage("chatbot", "Invoice generated! View it here: " + generatedLink);
                    }
                },
                error: function (xhr) {
                    alert("Error: " + (xhr.responseJSON?.error || xhr.statusText));
                },
            });
            taskPricing.hide();
        });


        document.getElementById('close-task-selection').addEventListener('click', function() {
            taskSelection.hide();
        });

        document.getElementById('close-task-pricing').addEventListener('click', function() {
            taskPricing.hide();
        });

    </script>