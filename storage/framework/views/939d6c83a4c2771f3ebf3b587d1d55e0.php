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
    <div>
        <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => ['breadcrumbs' => [
            ['url' => route('dashboard'), 'label' => 'Dashboard'],
            ['url' => route('clients.index'), 'label' => 'Clients List'],
            ['label' => 'Update Client']
        ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
            ['url' => route('dashboard'), 'label' => 'Dashboard'],
            ['url' => route('clients.index'), 'label' => 'Clients List'],
            ['label' => 'Update Client']
        ])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
        <div class="bg-white rounded-md shadow-md p-4 m-2">
            <form action=" <?php echo e(route('clients.update', $client->id)); ?> " method="POST" class="w-full flex flex-col gap-2 justify-start items-center" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="text" name="name" id="name" value="<?php echo e($client->full_name); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Name">
                <input type="email" name="email" id="email" value="<?php echo e($client->email); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Email">
                <input type="text" name="phone_number" id="phone_number" value="<?php echo e($client->phone); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Phone Number">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('clientAgent', App\Models\Client::class)): ?>
                <select name="agent_id" id="agent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($agent->id); ?>" <?php echo e($client->agent_id == $agent->id ? 'selected' : ''); ?>>
                        <?php echo e($agent->name); ?>

                    </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php endif; ?>
                <textarea name="address" id="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" placeholder="Address"><?php echo e($client->address); ?></textarea>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" name="passport_no" id="passport_no" value="<?php echo e($client->passport_no ?? 'N\A'); ?>" disabled>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" name="civil_no" id="civil_no" value="<?php echo e($client->civil_no ?? 'N\A'); ?>" disabled>
                <div
                    id="file-container"
                    class="border-2 border-dashed border-gray-400 rounded-md w-full w-full flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48"
                    ondrop="dropHandler(event);"
                    ondragover="dragOverHandler(event);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5" stroke="#1C274C" stroke-width="1.5" />
                        <path d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z" stroke="#1C274C" stroke-width="1.5" />
                    </svg>
                    <input type="file" name="file" id="file" class="hidden">
                    <p id="file-name">
                        You can drag and drop a file here
                    </p>
                    <label for="file" class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500">
                        Upload File
                    </label>
                </div>
                <div class="w-full flex justify-end">
                    <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => ['class' => 'font-semibold']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'font-semibold']); ?>
                        Update Client
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <script>
        const file = document.getElementById('file');
        const fileName = document.getElementById('file-name');
        file.addEventListener('change', (e) => {
            fileName.textContent = e.target.files[0].name;
            file.innerHTML = '';
            let img = document.createElement('img');
            img.src = URL.createObjectURL(e.target.files[0]);
            console.log(img.src);  
            img.width = 100;
            img.height = 100;
            file.appendChild(img);
        });

        dropHandler = (e) => {
            e.preventDefault();
            file.files = e.dataTransfer.files;
            fileName.textContent = e.dataTransfer.files[0].name;
        }

        dragOverHandler = (e) => {
            console.log('File in drop area');
            e.preventDefault();
        }
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/clients/edit.blade.php ENDPATH**/ ?>