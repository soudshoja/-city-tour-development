<style>
    .download {
        position: fixed;
        padding-top: 10px;
        top: 10px;
        right: 10px;
        z-index: 50;
    }

    .download a {
        padding: 10px 20px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
    }

    .download a:hover {
        background-color: #0056b3;
    }
</style>
<div class="download">
    <a href="<?php echo e(route('tasks.pdf.receipt.download', ['taskId' => $task->id])); ?>" target="_blank">
        Download PDF
    </a>
</div>
<body>
    <?php echo $__env->make('tasks.pdf.receipt', ['task' => $task, 'invoiceDetail' => $invoiceDetail], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</body><?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/pdfView/receipt-view.blade.php ENDPATH**/ ?>