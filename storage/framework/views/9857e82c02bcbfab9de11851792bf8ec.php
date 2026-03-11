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
     <nav>
         <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
             <li>
                 <span>Transactions</span>
             </li>
         </ul>
     </nav>
     <header class="p-2 bg-white rounded shadow text-xl font-bold mb-4">
         Transactions History
     </header>
     <main class="transaction p-4 bg-white shadow rounded">
         <body>
             <table>
                 <thead>
                     <tr>
                         <th>Transaction ID</th>
                         <th>Type</th>
                         <th>Amount</th>
                         <th>Date</th>
                         <th>Action</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                     <tr>
                         <td class="text-center"><?php echo e($transaction->id); ?></td>
                         <td class="text-center"><?php echo e($transaction->transaction_type); ?></td>
                         <td class="text-center"><?php echo e($transaction->amount); ?></td>
                         <td class="text-center"><?php echo e($transaction->created_at); ?></td>
                         <td>
                             <a href="<?php echo e(route('journal-entries.index', $transaction->id)); ?>" class="text-blue-500 hover:underline">
                                 View Ledger
                             </a>
                         </td>
                     </tr>
                     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                 </tbody>
             </table>
         </body>
     </main>
  <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/transactions/index.blade.php ENDPATH**/ ?>