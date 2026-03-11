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

    <div class="bg-slate-400 h-screen flex items-center justify-center">
        <?php if(session('status')): ?>
            <div id="sessionMessage"
                class="fixed top-0 left-0 w-full bg-green-600 text-white text-center py-3 px-4 shadow-md z-50"
                role="alert">
                <?php echo e(session('status')); ?>

            </div>

            <script>
                setTimeout(() => {
                    const msg = document.getElementById('sessionMessage');
                    if (msg) {
                        msg.style.transition = 'opacity 0.5s ease';
                        msg.style.opacity = '0';
                        setTimeout(() => msg.remove(), 500);
                    }
                }, 5000); // 5000 ms = 5 seconds
            </script>
        <?php endif; ?>


        <div class="rounded-lg flex items-stretch w-[80%] justify-center">
            <!-- Left Side - Login Form -->
            <div
                class="font-size-on-mobile-320 padding-mobile w-full lg:w-1/2 lg:p-8 md:p-8 xl:p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">

                <h2 class="text-2xl font-semibold mb-4 text-left w-3/4 max-w-sm">Let's you sign in it ...</h2>
                <p class="text-sm mb-8 text-left w-3/4 max-w-sm">It's great to have you back!!</p>

                <form method="POST" action="<?php echo e(route('login')); ?>" class="w-full flex flex-col items-center">
                    <?php echo csrf_field(); ?>
                    
                    <!-- Email -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="email" type="email" name="email" placeholder="Your Email"
                            class="form-input ps-10 placeholder:text-white-dark block w-full bg-white text-black rounded-md py-3 px-3 text-lg shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('email')" required autofocus autocomplete="username" />
                        <span class="absolute start-4 top-1/2 -translate-y-1/2">
                            <svg class="text-black w-5 h-5" viewBox="0 0 18 18" fill="none">
                                <path opacity="0.5"
                                    d="M10.65 2.25H7.35C4.23873 2.25 2.6831 2.25 1.71655 3.23851C0.75 4.22703 0.75 5.81802 0.75 9C0.75 12.182 0.75 13.773 1.71655 14.7615C2.6831 15.75 4.23873 15.75 7.35 15.75H10.65C13.7613 15.75 15.3169 15.75 16.2835 14.7615C17.25 13.773 17.25 12.182 17.25 9C17.25 5.81802 17.25 4.22703 16.2835 3.23851C15.3169 2.25 13.7613 2.25 10.65 2.25Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M14.3465 6.02574C14.609 5.80698 14.6445 5.41681 14.4257 5.15429C14.207 4.89177 13.8168 4.8563 13.5543 5.07507L11.7732 6.55931C11.0035 7.20072 10.4691 7.6446 10.018 7.93476C9.58125 8.21564 9.28509 8.30993 9.00041 8.30993C8.71572 8.30993 8.41956 8.21564 7.98284 7.93476C7.53168 7.6446 6.9973 7.20072 6.22761 6.55931L4.44652 5.07507C4.184 4.8563 3.79384 4.89177 3.57507 5.15429C3.3563 5.41681 3.39177 5.80698 3.65429 6.02574L5.4664 7.53583C6.19764 8.14522 6.79033 8.63914 7.31343 8.97558C7.85834 9.32604 8.38902 9.54743 9.00041 9.54743C9.6118 9.54743 10.1425 9.32604 10.6874 8.97558C11.2105 8.63914 11.8032 8.14522 12.5344 7.53582L14.3465 6.02574Z"
                                    fill="currentColor"></path>
                            </svg>
                        </span>
                    </div>
                    <!-- Email Error -->
                    <?php if($errors->has('email')): ?>
                        <div class="flex rounded py-1 text-[#f27474] mt-2">
                            <span class="my-2">
                                <strong>Error:</strong> <?php echo e($errors->first('email')); ?>

                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Password -->
                    <div class="relative text-white-dark mt-5 w-3/4 max-w-sm">
                        <input id="password" type="password" name="password" placeholder="Your Password"
                            class="form-input ps-10 placeholder:text-white-dark block w-full bg-white text-black rounded-md py-3 px-3 text-lg shadow focus:ring-2 focus:ring-blue-500"
                            required autocomplete="current-password" />
                        <span class="absolute start-4 top-1/2 -translate-y-1/2">
                            <svg class="text-black w-5 h-5" viewBox="0 0 18 18" fill="none">
                                <path opacity="0.5"
                                    d="M1.5 12C1.5 9.87868 1.5 8.81802 2.15901 8.15901C2.81802 7.5 3.87868 7.5 6 7.5H12C14.1213 7.5 15.182 7.5 15.841 8.15901C16.5 8.81802 16.5 9.87868 16.5 12C16.5 14.1213 16.5 15.182 15.841 15.841C15.182 16.5 14.1213 16.5 12 16.5H6C3.87868 16.5 2.81802 16.5 2.15901 15.841C1.5 15.182 1.5 14.1213 1.5 12Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M6 12.75C6.41421 12.75 6.75 12.4142 6.75 12C6.75 11.5858 6.41421 11.25 6 11.25C5.58579 11.25 5.25 11.5858 5.25 12C5.25 12.4142 5.58579 12.75 6 12.75Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M9 12.75C9.41421 12.75 9.75 12.4142 9.75 12C9.75 11.5858 9.41421 11.25 9 11.25C8.58579 11.25 8.25 11.5858 8.25 12C8.25 12.4142 8.58579 12.75 9 12.75Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M12.75 12C12.75 12.4142 12.4142 12.75 12 12.75C11.5858 12.75 11.25 12.4142 11.25 12C11.25 11.5858 11.5858 11.25 12 11.25C12.4142 11.25 12.75 11.5858 12.75 12Z"
                                    fill="currentColor"></path>
                                <path
                                    d="M5.0625 6C5.0625 3.82538 6.82538 2.0625 9 2.0625C11.1746 2.0625 12.9375 3.82538 12.9375 6V7.50268C13.363 7.50665 13.7351 7.51651 14.0625 7.54096V6C14.0625 3.20406 11.7959 0.9375 9 0.9375C6.20406 0.9375 3.9375 3.20406 3.9375 6V7.54096C4.26488 7.51651 4.63698 7.50665 5.0625 7.50268V6Z"
                                    fill="currentColor"></path>
                            </svg>
                        </span>
                        <span class="absolute end-4 top-1/2 -translate-y-1/2 cursor-pointer"
                            onclick="togglePasswordVisibility()">
                            <svg id="eyeIcon" class="w-5 h-5 text-black dark:text-white" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </span>
                    </div>
                    <!-- Password Error -->
                    <?php if($errors->has('password')): ?>
                        <div class="flex rounded py-1 text-[#f27474] mt-2">
                            <span class="my-2">
                                <strong>Error:</strong> <?php echo e($errors->first('password')); ?>

                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Forgot Password -->
                    <?php if(Route::has('password.request')): ?>
                        <div class="w-3/4 max-w-sm text-right mt-2 mb-2">
                            <a class="text-sm text-white underline hover:text-gray-200"
                                href="<?php echo e(route('password.request')); ?>">
                                <?php echo e(__('Forgot your password?')); ?>

                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Remember Me -->
                    <div class="flex items-center my-4 w-3/4 max-w-sm">
                        <input id="remember_me" type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            name="remember">
                        <label for="remember_me" class="ml-2 text-sm text-white">
                            <?php echo e(__('Keep me logged in')); ?>

                        </label>
                    </div>

                    <!-- Sign In Button -->
                    <div class="mb-4 w-3/4 max-w-sm flex">
                        <button type="submit"
                            class="justify-center text-center text-gray-700 bg-[#F7BE38] hover:bg-[#F7BE38]/90 focus:ring-4 focus:outline-none focus:ring-[#F7BE38]/50 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-[#F7BE38]/50 City-me-2 mb-2 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                            <?php echo e(__('Login')); ?>

                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side - Image/Illustration -->
            <div
                class="hide-on-mobile sm:rounded-lg lg:flex w-full lg:w-1/2 bg-white items-center justify-center custom-rounded-right">
                <img src="<?php echo e(asset('images/LoginPic550px.png')); ?>" alt="Illustration"
                    class="-mt-10 object-contain max-h-[600px]">
            </div>
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/auth/login.blade.php ENDPATH**/ ?>