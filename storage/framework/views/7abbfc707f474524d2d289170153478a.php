<div id="toast" class="toast fixed bottom-2 right-1 border text-black m-2 rounded-lg shadow-lg bg-white p-4 font-semibold z-50">
    <div class="flex p-2">
        <div class="toast-body px-2 flex items-center">
        </div>
        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" onclick="this.parentElement.parentElement.classList.remove('show')">
            <span class="text-3xl font-bold flex items-center" aria-hidden="true">&times;</span>
        </button>
    </div>
</div>

<style>
    .toast {
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
        font-family: 'Poppins', sans-serif;
        align-items: start;
    }

    .toast.show {
        opacity: 1;
    }
</style>

<script>
    function showToast(text) {
        const toast = document.getElementById('toast');
        toast.querySelector('.toast-body').textContent = text;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/toast.blade.php ENDPATH**/ ?>