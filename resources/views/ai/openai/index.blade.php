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

        var d = $('.chat-box');
        console.log(d.prop("scrollHeight"));
        d.scrollTop(d.prop("scrollHeight"));
    </script>
</x-app-layout>