<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'companyId',
    'title'
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'companyId',
    'title'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php if($isAdmin): ?>
<div class="p-3 bg-gradient-to-r from-gray-700 to-gray-600 text-white rounded-t-lg  flex items-center justify-between">
    <div class="grid">
        <h1 class="text-xl font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
            </svg>
            Admin View
        </h1>
        <p class="text-sm text-gray-300">You have access to all <?php echo e($title); ?> across all companies.</p>
    </div>

    <form method="GET" action="" class="inline-block" id="adminCompanyForm">
        <?php $__currentLoopData = request()->except('company_id','page'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(is_array($val)): ?>
        <?php $__currentLoopData = $val; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <input type="hidden" name="<?php echo e($key); ?>[]" value="<?php echo e($v); ?>">
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($val); ?>">
        <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <input type="hidden" name="company_id" id="selectedCompanyId" value="<?php echo e($companyId); ?>">
        
        <div class="relative min-w-[250px]">
            <label class="block text-xs font-medium text-gray-300 mb-1">Filter by Company</label>
            <input type="text" id="companySearchDisplay" 
                placeholder="Select Company..." 
                value="<?php echo e($companyId ? $companies->firstWhere('id', $companyId)?->name : ''); ?>"
                class="w-full border border-gray-400 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer bg-white text-gray-800"
                onclick="toggleCompanyDropdown()" readonly>
            <div class="absolute right-3 top-8 pointer-events-none">
                <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
            <div id="companyDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-hidden">
                <div class="p-2 border-b bg-gray-50">
                    <input type="text" id="companySearchInput" placeholder="Type to search..."
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        onkeyup="filterCompanyOptions()">
                </div>
                <div id="companyOptions" class="overflow-y-auto max-h-48">
                    <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $companySelect): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="company-option px-3 py-2 hover:bg-indigo-50 cursor-pointer text-gray-800 text-sm <?php echo e($companySelect->id == $companyId ? 'bg-indigo-100' : ''); ?>"
                        data-name="<?php echo e(strtolower($companySelect->name)); ?>"
                        onclick="selectCompany('<?php echo e($companySelect->id); ?>', '<?php echo e($companySelect->name); ?>')">
                        <span class="flex items-center gap-2">
                            <?php if($companySelect->id == $companyId): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <?php else: ?>
                            <span class="w-4"></span>
                            <?php endif; ?>
                            <?php echo e($companySelect->name); ?>

                        </span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleCompanyDropdown() {
        const dropdown = document.getElementById('companyDropdown');
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            document.getElementById('companySearchInput').focus();
        }
    }

    function filterCompanyOptions() {
        const searchValue = document.getElementById('companySearchInput').value.toLowerCase();
        const options = document.querySelectorAll('.company-option');
        
        options.forEach(option => {
            const name = option.getAttribute('data-name');
            if (name.includes(searchValue)) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        });
    }

    function selectCompany(id, name) {
        document.getElementById('selectedCompanyId').value = id;
        document.getElementById('companySearchDisplay').value = id ? name : '';
        document.getElementById('companyDropdown').classList.add('hidden');
        document.getElementById('adminCompanyForm').submit();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('companyDropdown');
        const searchDisplay = document.getElementById('companySearchDisplay');
        
        if (dropdown && searchDisplay && !dropdown.contains(event.target) && event.target !== searchDisplay) {
            dropdown.classList.add('hidden');
        }
    });
</script>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/admin-card.blade.php ENDPATH**/ ?>