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
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Invoices Link</h2>
            <div data-tooltip="Number of invoices"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white"><?php echo e($invoices->total()); ?></span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            <a href="<?php echo e(route('invoices.create')); ?>">
                <div data-tooltip-left="Create new invoice"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <div class="panel rounded-lg">
        <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('invoices.link'),'searchParam' => 'search','placeholder' => 'Quick search for invoices link']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('invoices.link')),'searchParam' => 'search','placeholder' => 'Quick search for invoices link']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $attributes = $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $component = $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Invoice Number</th>
                            <th>Invoice Link</th>
                            <th>Payment Type</th>
                            <th>Client</th>
                            <th>Action</th>
                            <th>Amount</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($invoices->isEmpty()): ?>
                        <tr>
                            <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                        </tr>
                        <?php else: ?>
                        <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $invoiceDetail = $invoice->invoiceDetails->first();
                        ?>
                        <tr data-price="<?php echo e($invoice->total); ?>"
                            data-supplier-id="<?php echo e($invoiceDetail && $invoiceDetail->task && $invoiceDetail->task->supplier ? $invoiceDetail->task->supplier->id : ''); ?>"
                            data-branch-id="<?php echo e($invoice->agent->branch->id); ?>"
                            data-agent-id="<?php echo e($invoice->agent_id); ?>"
                            data-status="<?php echo e($invoice->status); ?>"
                            data-type="<?php echo e($invoiceDetail && $invoiceDetail->task ? $invoiceDetail->task->type : ''); ?>"
                            data-client-id="<?php echo e($invoice->client ? $invoice->client->id : null); ?>"
                            data-task-id="<?php echo e($invoice->id); ?>" class="taskRow">
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-300">
                                <?php echo e($invoice->invoice_number); ?>

                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                <?php if($invoice->status === 'paid by refund'): ?>
                                    <span class="text-gray-500 italic dark:text-gray-400">Settled by refund</span>
                                <?php elseif($invoice->payment_type): ?>
                                    <a href="<?php echo e(route('invoice.show', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number])); ?>" class="text-blue-500 hover:underline" target="_blank">
                                        <?php echo e(route('invoice.show', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number])); ?>

                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500 italic dark:text-gray-400">Invoice link available after setting payment type</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <?php echo e($invoice->payment_type ? ucwords($invoice->payment_type) : 'N/A'); ?>

                            </td>
                            <td x-data="{ editClientPhone: false}">
                                <p class="cursor-pointer text-blue-500 dark:text-blue-400 hover:underline"
                                    @click="editClientPhone = !editClientPhone" data-tooltip-left="Edit client number">
                                    <?php echo e($invoice->client->full_name); ?>

                                </p>
                                <div x-cloak x-show="editClientPhone" class="fixed bg-gray-800 inset-0 bg-opacity-75 flex items-center justify-center z-50">
                                    <div @click.away="editClientPhone = false"
                                        class="p-4 bg-white w-full max-w-md rounded relative dark:bg-gray-900">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Update Phone Number</h2>
                                                <p class="text-gray-600 dark:text-gray-400 italic text-xs mt-1">
                                                    Please update the client's phone number to ensure accurate communication</p>
                                            </div>
                                            <button @click="editClientPhone = false" class="absolute top-0 right-0 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                &times;
                                            </button>
                                        </div>
                                        <form method="POST" action="<?php echo e(route('clients.update', $invoice->client->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PUT'); ?>
                                            <input type="hidden" name="first_name" id="client" value="<?php echo e($invoice->client->first_name); ?>">
                                            <div class="mb-4 flex flex-col">
                                                <label class="block text-gray-700 mb-2" for="phone_<?php echo e($invoice->client->id); ?>">Phone Number</label>
                                                <div class="flex gap-4 mb-4">
                                                    <div class="w-2/5">
                                                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'country_code','items' => \App\Models\Country::all()->map(fn($country) => [
                                                                    'id' => $country->dialing_code,
                                                                    'name' => $country->dialing_code . ' ' . $country->name
                                                                ]),'placeholder' => 'Dial Code'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(optional($invoice->client)->country_code),'showAllOnOpen' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                                                    </div>
                                                    <div class="w-3/5">
                                                        <input
                                                            type="text"
                                                            name="phone"
                                                            id="phone_<?php echo e($invoice->client->id); ?>"
                                                            value="<?php echo e($invoice->client->phone); ?>"
                                                            class="w-full border border-gray-300 rounded px-3"
                                                            required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex justify-between mt-3 gap-2">
                                                <button type="button" @click="editClientPhone = false" class="rounded-full shadow-md border border-gray-200 hover:bg-gray-300 px-4 py-2">Cancel</button>
                                                <button type="submit" class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($invoice->status === 'paid by refund'): ?>
                                    <span class="relative inline-flex cursor-default" data-tooltip="Settled by refund">
                                        <span class="badge badge-outline-info">Paid by Refund</span>
                                    </span>
                                <?php elseif($invoice->payment_type): ?>
                                    <form action="<?php echo e(route('resayil.share-invoice-link')); ?>" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="client_id" id="client"
                                            value="<?php echo e($invoice->client->id); ?>">
                                        <input type="hidden" name="invoiceNumber"
                                            value="<?php echo e($invoice->invoice_number); ?>">
                                        <button type="submit" class="badge badge-outline-success">
                                            Share via WhatsApp
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?php echo e(route('invoice.edit', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                                    target="_blank">
                                        <button type="button" class="badge badge-outline-warning">
                                            Set payment type first
                                        </button>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <?php echo e($invoice->currency); ?>

                                <?php echo e($invoice->amount); ?>

                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <?php echo e($invoice->due_date); ?>

                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                <?php if($invoice->status === 'paid'): ?>
                                <span
                                    class="badge badge-outline-success"><?php echo e($invoice->status); ?></span>
                                <?php elseif($invoice->status === 'paid by refund'): ?>
                                <span
                                    class="badge badge-outline-info"><?php echo e($invoice->status); ?></span>
                                <?php else: ?>
                                <span
                                    class="badge badge-outline-danger"><?php echo e($invoice->status); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php $__currentLoopData = $invoice->invoicePartials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $partial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($partial->type === 'split'): ?>
                        <tr data-price="<?php echo e($partial->total); ?>"
                            data-supplier-id="<?php echo e($invoiceDetail->task->supplier->id); ?>"
                            data-branch-id="<?php echo e($invoice->agent->branch->id); ?>"
                            data-agent-id="<?php echo e($invoice->agent_id); ?>"
                            data-status="<?php echo e($partial->status); ?>"
                            data-type="<?php echo e($partial->type); ?>"
                            data-client-id="<?php echo e($invoice->client ? $invoice->client->id : null); ?>"
                            data-task-id="<?php echo e($invoice->id); ?>" class="taskRow">
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-300">
                                <?php echo e($invoice->invoice_number); ?>

                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                <a href="<?php echo e(route('invoice.split', ['invoiceNumber' => $invoice->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id])); ?>" class="text-green-500 hover:underline" target="_blank">
                                    <?php echo e(route('invoice.split', ['invoiceNumber' => $invoice->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id])); ?>

                                </a>
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <?php echo e(ucwords($partial->type)); ?>

                            </td>
                            <td x-data="{ editClientPhone: false }">
                                <p
                                    class="cursor-pointer text-blue-500 hover:underline dark:text-blue-400"
                                    @click="editClientPhone = !editClientPhone" data-tooltip-left="Edit Client Phone">
                                    <?php echo e($partial->client->full_name); ?>

                                </p>
                                <div x-cloak x-show="editClientPhone" class="fixed bg-gray-800 inset-0 bg-opacity-75 flex items-center justify-center z-50">
                                    <div @click.away="editClientPhone = false" class="p-4 bg-white w-full max-w-md rounded relative">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-bold text-gray-800">Update Phone Number</h2>
                                                <p class="text-gray-600 italic text-xs mt-1">Please update the client's phone number to ensure accurate communication</p>
                                            </div>
                                            <button @click="editClientPhone = false" class="absolute top-0 right-0 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                &times;
                                            </button>
                                        </div>
                                        <form method="POST" action="<?php echo e(route('clients.update', $partial->client->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PUT'); ?>
                                            <input type="hidden" name="first_name" id="client" value="<?php echo e($partial->client->first_name); ?>">
                                            <div class="mb-4 flex flex-col">
                                                <label class="block text-gray-700 mb-2" for="phone_<?php echo e($partial->client->id); ?>">Phone Number</label>
                                                <div class="flex gap-4 mb-4">
                                                    <div class="w-2/5">
                                                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'country_code','items' => \App\Models\Country::all()->map(fn($country) => [
                                                                    'id' => $country->dialing_code,
                                                                    'name' => $country->dialing_code . ' ' . $country->name
                                                                ]),'placeholder' => 'Dial Code'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(optional($partial->client)->country_code),'showAllOnOpen' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                                                    </div>
                                                    <div class="w-3/5">
                                                        <input type="text" name="phone"
                                                            id="phone_<?php echo e($partial->client->id); ?>" value="<?php echo e($partial->client->phone); ?>"
                                                            class="w-full border border-gray-300 rounded px-3" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex justify-between mt-3 gap-2">
                                                <button type="button" @click="editClientPhone = false" class="rounded-full shadow-md border border-gray-200 hover:bg-gray-300 px-4 py-2">Cancel</button>
                                                <button type="submit" class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <form action="<?php echo e(route('resayil.share-invoice-link')); ?>" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="client_id" id="client" value="<?php echo e($partial->client_id); ?>">
                                    <input type="hidden" name="invoiceNumber" value="<?php echo e($partial->invoice->invoice_number); ?>">
                                    <button type="submit" class="badge badge-outline-success">
                                        Share via WhatsApp
                                    </button>
                                </form>
                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <?php echo e($invoice->currency); ?> <?php echo e($partial->amount); ?>

                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <?php echo e($partial->expiry_date); ?>

                            </td>
                            <td class="p-3 text-sm font-semibold text-gray-500">
                                <?php if($partial->status === 'paid'): ?>
                                <span
                                    class="badge badge-outline-success"><?php echo e($partial->status); ?></span>
                                <?php else: ?>
                                <span
                                    class="badge badge-outline-danger"><?php echo e($partial->status); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
            <?php if (isset($component)) { $__componentOriginal41032d87daf360242eb88dbda6c75ed1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41032d87daf360242eb88dbda6c75ed1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pagination','data' => ['data' => $invoices]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoices)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $attributes = $__attributesOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $component = $__componentOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__componentOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
        </div>
    </div>

    <?php echo $__env->make('invoice.tasksjs', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <script>
        function openInvoiceModal(invoiceNumber) {
            const modal = document.getElementById("viewInvoiceModal");
            const contentDiv = document.getElementById("invoiceInvoiceContent");
            const companyId = "<?php echo e($companyId ?? ''); ?>";

            // Clear previous content
            contentDiv.innerHTML = "";

            // Open the modal
            modal.classList.remove("hidden");
            url = "<?php echo e(route('invoice.show', ['companyId' => ':companyId', 'invoiceNumber' => ':invoiceNumber'])); ?>".replace(':companyId', companyId).replace(':invoiceNumber', invoiceNumber);

            // Fetch the invoice details
            fetch(url)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.text();
                })
                .then((data) => {
                    contentDiv.innerHTML = data;

                    // Close the modal when the backdrop is clicked
                    modal.addEventListener("click", (event) => {
                        if (event.target === modal) {
                            closeInvoiceModal();
                        }
                    });


                })
                .catch((error) => {
                    console.error("Error fetching invoice details:", error);
                    contentDiv.innerHTML =
                        '<p class="text-center text-red-500">Failed to load invoice details.</p>';

                });
        }

        function closeInvoiceModal() {
            const modal = document.getElementById("viewInvoiceModal");
            modal.classList.add("hidden");
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/link.blade.php ENDPATH**/ ?>