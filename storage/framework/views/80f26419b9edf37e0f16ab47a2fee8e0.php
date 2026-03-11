<div id="edit-procedure" class="my-2 p-2">
    <form method="POST" action="<?php echo e(route('supplier-procedures.store', $supplier->id)); ?>" id="procedure-form">
        <?php echo csrf_field(); ?>

        <?php if($supplierCompany): ?>
            <!-- Hidden field for supplier_company_id -->
            <input type="hidden" name="supplier_company_id" value="<?php echo e($supplierCompany->id); ?>">
            
            <div class="mb-4">
                <label for="procedure-name" class="block text-sm font-medium text-gray-700 mb-2">Procedure Name</label>
                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="procedure-name" name="name" value="" required>
            </div>

            <div class="mb-4">
                <label for="procedure-content" class="block text-sm font-medium text-gray-700 mb-2">Procedure Content</label>
                <div id="quill-editor" style="height: 300px;" class="bg-white border border-gray-300 rounded-md"></div>
                <input type="hidden" id="procedure-content" name="procedure" />
            </div>
            
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Save Procedure</button>
        <?php else: ?>
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mb-2"></i>
                <p class="text-yellow-800 font-medium">Supplier Not Active</p>
                <p class="text-yellow-600 text-sm">This supplier is not activated for your company. Please contact an administrator to activate this supplier.</p>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Quill.js CSS and JS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
// Initialize Quill editor
var quill = new Quill('#quill-editor', {
    theme: 'snow',
    placeholder: 'Enter your procedure content here...',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'font': [] }],
            [{ 'align': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'indent': '-1'}, { 'indent': '+1' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ]
    }
});

// Update hidden input when form is submitted
document.getElementById('procedure-form').addEventListener('submit', function(e) {
    var procedureInput = document.getElementById('procedure-content');
    var content = quill.root.innerHTML;
    
    // Don't submit if content is empty or just the placeholder
    if (content === '<p><br></p>' || content === '<p>Enter your procedure content here...</p>' || content.trim() === '') {
        e.preventDefault();
        alert('Please enter procedure content before submitting.');
        return false;
    }
    
    procedureInput.value = content;
    console.log('Submitting procedure content:', content); // For debugging
});

// Set initial content
quill.root.innerHTML = '<p></p>';
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/partials/add_procedure.blade.php ENDPATH**/ ?>