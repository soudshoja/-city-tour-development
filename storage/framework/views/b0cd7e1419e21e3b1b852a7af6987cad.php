<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
    </style>

        <div class="rounded-lg flex items-stretch w-[80%] justify-center">
            <!-- Left Side - Login Form -->
            <div
                class="font-size-on-mobile-320 padding-mobile w-full lg:w-1/2 lg:p-8 md:p-8 xl:p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">

                <h2 class="text-2xl font-semibold mb-4 text-left w-3/4 max-w-sm">Admin Only</h2>

                <h2>Login</h2>
        <form action="<?php echo e(route('version.index')); ?>" method="GET">
            <input type="text" placeholder="Username" required>
            <input type="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

            </div>

            <!-- Right Side - Image/Illustration -->
            <div
                class="hide-on-mobile sm:rounded-lg lg:flex w-full lg:w-1/2 bg-white items-center justify-center custom-rounded-right">
                <img src="<?php echo e(asset('images/LoginPic550px.png')); ?>" alt="Illustration"
                    class="-mt-10 object-contain max-h-[600px]">
            </div>
        </div>


    <script>
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.add('feather-eye-off');
                eyeIcon.classList.remove('feather-eye');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.add('feather-eye');
                eyeIcon.classList.remove('feather-eye-off');
            }
        }
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/version/login.blade.php ENDPATH**/ ?>