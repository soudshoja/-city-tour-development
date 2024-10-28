<!-- singleTask.blade.php -->
<div id="taskModal" onclick="closeModalbgC(event)"
    class="fixed inset-0 hidden  bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg w-full max-w-2xl p-6 shadow-lg relative">
        <button class="absolute top-4 right-4 text-gray-500 dark:text-gray-300" onclick="closeTaskModal()">
            &times;
        </button>
        <h2 class="text-lg font-semibold mb-4" id="taskTitle">Task Details</h2>

        <!-- Task details content -->
        <div id="taskDetails" class="space-y-2 text-gray-700 dark:text-gray-300">
            <p><strong>Status:</strong> <span id="taskStatus"></span></p>
            <p><strong>Client Name:</strong> <span id="taskClientName"></span></p>
            <p><strong>Agent Name:</strong> <span id="taskAgentName"></span></p>
            <p><strong>Type:</strong> <span id="taskType"></span></p>
            <p><strong>Net Price:</strong> <span id="taskPrice"></span></p>
            <p><strong>Surcharge:</strong> <span id="taskSurcharge"></span></p>
            <p><strong>Tax:</strong> <span id="taskTax"></span></p>
            <p><strong>Total:</strong> <span id="taskTotal"></span></p>
            <p><strong>Reference:</strong> <span id="taskReference"></span></p>
        </div>
    </div>
</div>

<script>
// JavaScript to handle showing the task modal
function ShowTask(taskId) {
    console.log('Opening task modal for task ID:', taskId); // Debugging line to ensure function is triggered

    // Fetch task details using AJAX
    fetch(`/task/${taskId}`)
        .then(response => response.json())
        .then(data => {
            if (data.task) {
                console.log('Task data loaded:', data.task); // Debugging line to see the task data in console

                // Populate modal with task details
                document.getElementById('taskStatus').textContent = data.task.status;
                document.getElementById('taskClientName').textContent = data.task.client_name;
                document.getElementById('taskAgentName').textContent = data.task.agent.name;
                document.getElementById('taskType').textContent = data.task.type;
                document.getElementById('taskPrice').textContent = data.task.price;
                document.getElementById('taskSurcharge').textContent = data.task.surcharge;
                document.getElementById('taskTax').textContent = data.task.tax;
                document.getElementById('taskTotal').textContent = data.task.total;
                document.getElementById('taskReference').textContent = data.task.reference;

                // Show the modal
                document.getElementById('taskModal').classList.remove('hidden');
                console.log('Modal is now visible'); // Debugging line to confirm the modal is shown
            } else {
                alert('Task details could not be loaded.');
            }
        })
        .catch(error => {
            console.error('Error fetching task details:', error);
        });
}

// Close the modal
function closeTaskModal() {
    document.getElementById('taskModal').classList.add('hidden');
    console.log('Modal has been closed'); // Debugging line to confirm the modal is closed
}

// Close the modal when clicking outside the modal content (on the background)
function closeModalOnBgClick(event) {
    const modal = document.getElementById('taskModal');
    const modalContent = document.querySelector('#taskModal > div');

    // If the clicked target is the modal itself (background), close the modal
    if (event.target === modal) {
        closeTaskModal();
    }
}

// Add event listener to close modal when clicking on the background
document.getElementById('taskModal').addEventListener('click', closeModalOnBgClick);

// Optional: Close the modal by pressing the Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        closeTaskModal();
    }
});
</script>

<style>
/* CSS for the modal */
#taskModal .dark\:bg-gray-800 {
    background-color: #F3F4F6;
}
</style>