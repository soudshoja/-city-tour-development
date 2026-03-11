<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <!-- Breadcrumbs -->
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="<?php echo e(route('tasks.index')); ?>" class="customBlueColor hover:underline"> Tasks</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Queue</span>
        </li>
    </ul>
    <!-- ./Breadcrumbs -->

    <?php if($queueTasks->isEmpty()): ?>
    <p class="text-center text-gray-500 dark:text-gray-300">No tasks in the queue</p>
    <?php else: ?>
    <?php $__currentLoopData = $queueTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="p-2 bg-white dark:bg-gray-700 rounded-md shadow-md mb-2">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?php echo e($task->reference); ?></p>
                <p class="text-sm text-gray-500 dark:text-gray-300"><?php echo e($task->agent->name ?? 'Not Agent Set'); ?></p>
            </div>
            <div>
                <a href="<?php echo e(route('tasks.show', $task->id)); ?>" class="view-task text-blue-600 dark:text-blue-500">View</a>
            </div>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>

    <!-- Modal -->
    <div id="taskModal" class="fixed inset-0 flex items-center justify-center bg-opacity-50 hidden bg-gray-900 w">
    <div class="bg-white dark:bg-gray-700 rounded-md shadow-md p-6 max-w-3xl w-full mx-4">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Task Details</h2>
            <button id="closeModal" class="text-gray-500 dark:text-gray-300 text-2xl">&times;</button>
        </div>
        <div id="modalContent" class="mt-4">
            <!-- Task details will be loaded here -->
        </div>
    </div>
</div>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('taskModal');
        const closeModal = document.getElementById('closeModal');
        const modalContent = document.getElementById('modalContent');

        document.querySelectorAll('.view-task').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const url = this.getAttribute('href');
                console.log('Fetching URL:', url); // Debugging log

                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Fetched Data:', data); // Debugging log
                        const html = `
<div class="flex flex-col items-center">
    <div class="bg-blue-200 dark:bg-blue-800 text-blue-900 dark:text-blue-100 font-semibold text-center p-2 rounded-xl shadow-m text-2xl mb-6 w-full max-w-3xl">
        <h2>🏢 ${data.supplier?.name || "Unknown Supplier"}</h2>
    </div>

    <!-- Status Section -->
    <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl shadow-m mb-4 grid grid-cols-3 gap-2 text-center w-full max-w-3xl">
        <div class="col-span-3">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">📌 Status</h2>
            <p class="text-l text-gray-800 dark:text-gray-200 font-semibold">${data.status}</p>
        </div>
    </div>

    <!-- General Information -->
    <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl shadow-m mb-4 grid grid-cols-3 gap-2 text-center w-full max-w-3xl">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 col-span-3">ℹ️ General Information</h2>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">🔖 Reference</label>
            <p class="text-l font-semibold text-gray-900 dark:text-gray-100">${data.reference || "N/A"}</p>
        </div>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">📂 Type</label>
            <p class="text-l font-semibold text-gray-900 dark:text-gray-100">${data.type}</p>
        </div>
    </div>

    <!-- Dynamic Details -->
    ${data.type === "flight" ? `
    <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl shadow-m mb-4 grid grid-cols-3 gap-2 text-center w-full max-w-3xl">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 col-span-3">✈️ Flight Details</h2>
    <p class="text-l text-gray-800 dark:text-gray-200 col-span-3">${data.country_from} ➡ ${data.country_to}</p>
    
    <div class="col-span-3 md:col-span-1 flex flex-col items-center">
        <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">🛫 Departure</label>
        <p class="text-l text-gray-900 dark:text-gray-100">${data.flight_details?.airport_from || "N/A"} - ${data.flight_details?.departure_time || "N/A"}</p>
    </div>
    
    <div class="col-span-3 md:col-span-1 flex flex-col items-center">
        <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">🛬 Arrival</label>
        <p class="text-l text-gray-900 dark:text-gray-100">${data.flight_details?.airport_to || "N/A"} - ${data.flight_details?.arrival_time || "N/A"}</p>
    </div>
</div>
` : `
    <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl shadow-m mb-4 grid grid-cols-3 gap-2 text-center w-full max-w-3xl">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 col-span-3">🏨 Hotel Details</h2>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">🏠 Hotel</label>
            <p class="text-l text-gray-900 dark:text-gray-100">${data.hotel_name || "N/A"}</p>
        </div>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">📍 Location</label>
            <p class="text-l text-gray-900 dark:text-gray-100">${data.hotel_details?.hotel?.address || "N/A"}, ${data.hotel_details?.hotel?.city || "N/A"}</p>
        </div>
    </div>`}

    <!-- Pricing Section -->
    <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl shadow-m grid grid-cols-3 gap-2 text-center w-full max-w-3xl">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 col-span-3">💰 Pricing Details</h2>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">💵 Price</label>
            <p class="text-l font-semibold text-gray-900 dark:text-gray-100">${data.price || "N/A"}</p>
        </div>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">💸 Tax</label>
            <p class="text-l font-semibold text-gray-900 dark:text-gray-100">${data.tax || "N/A"}</p>
        </div>
        <div class="col-span-3 md:col-span-1">
            <label class="text-gray-600 dark:text-gray-300 text-lg font-medium">🧾 Total</label>
            <p class="text-l font-semibold text-gray-900 dark:text-gray-100">${data.total || "N/A"}</p>
        </div>
    </div>
</div>



                        `;
                        modalContent.innerHTML = html;
                        modal.classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Error fetching task details:', error); // Error handling
                    });
            });
        });

        closeModal.addEventListener('click', function() {
            modal.classList.add('hidden');
        });
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/queue.blade.php ENDPATH**/ ?>