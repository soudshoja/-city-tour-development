<style>
    .center-loading {
        position: fixed;
        inset: 0;
        width: fit-content;
        height: fit-content;
        margin: auto;
    }

    .loader {
        border: 4px solid #f3f3f3;
        border-radius: 50%;
        border-top: 4px solid #3498db;
        width: 40px;
        height: 40px;
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

<div id="loadingScreen" class="fixed inset-0 bg-gray-400 bg-opacity-55 flex z-50" style="display: none;">
    <div class="center-loading">
        <div
            class="container bg-white p-4 rounded shadow-md border-solid border-gray-300 border-2 flex items-center justify-center">
            <div class="loader"></div>
        </div>
    </div>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/loading.blade.php ENDPATH**/ ?>