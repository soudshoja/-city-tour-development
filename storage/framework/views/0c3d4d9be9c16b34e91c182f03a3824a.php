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
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 px-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="<?php echo e(route('dashboard')); ?>" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <a href="<?php echo e(route('suppliers.index')); ?>" class="customBlueColor hover:underline">Suppliers List</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <a href="<?php echo e(route('suppliers.tbo.index')); ?>" class="customBlueColor hover:underline">TBO Holidays</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <a href="<?php echo e(route('suppliers.tbo.prebook.index')); ?>" class="customBlueColor hover:underline">Prebook</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>Prebook for <?php echo e($tboPreBook->booking_code); ?></span>
        </li>
    </ul>
    <div class="bg-white rounded-md p-4 mb-4">
        Fill out the necessary information to proceed with the booking
    </div>
    <form method="POST" action="<?php echo e(route('suppliers.tbo.book')); ?>" class="bg-white rounded-md p-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="tbo_id" value="<?php echo e($tboPreBook->id); ?>">
        <input type="hidden" name="booking_code" value="<?php echo e($tboPreBook->booking_code); ?>">
    
        <?php $__currentLoopData = $tboPreBook->rooms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roomKey => $room): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="text-lg font-bold p-2 my-2" > Rooms <?php echo e($loop->iteration); ?> </div>
        <hr>
        <?php if($room->adult_quantity >0): ?>
        <div class="p-2 my-2">
            <h2 class="text-lg font-bold">Adults</h2>
            <hr>
            <?php for($i = 0; $i < $room->adult_quantity; $i++): ?>
                <div class="border border-gray-600 rounded-md p-2 my-2">
                    <h3 class="text-base font-bold">Adult <?php echo e($i + 1); ?></h3>
                    <div class="mb-4">
                        <label for="title" class="block">Title</label>
                        <select name="rooms[<?php echo e($roomKey); ?>][adults][<?php echo e($i); ?>][title]" class="w-full border rounded-md p-2">
                            <option value="Mr" <?php echo e(old('rooms.'.$roomKey.'.adults.'.$i.'.title') == 'Mr' ? 'selected' : ''); ?>>Mr</option>
                            <option value="Mrs" <?php echo e(old('rooms.'.$roomKey.'.adults.'.$i.'.title') == 'Mrs' ? 'selected' : ''); ?>>Mrs</option>
                            <option value="Ms" <?php echo e(old('rooms.'.$roomKey.'.adults.'.$i.'.title') == 'Ms' ? 'selected' : ''); ?>>Ms</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="firstName" class="block">First Name</label>
                        <input type="text" id="firstName" name="rooms[<?php echo e($roomKey); ?>][adults][<?php echo e($i); ?>][first_name]" value="<?php echo e(old('rooms.'.$roomKey.'.adults.'.$i.'.first_name')); ?>" class="w-full border rounded-md p-2">
                    </div>
                    <div class="mb-4">
                        <label for="lastName" class="block">Last Name</label>
                        <input type="text" id="lastName" name="rooms[<?php echo e($roomKey); ?>][adults][<?php echo e($i); ?>][last_name]" value="<?php echo e(old('rooms.'.$roomKey.'.adults.'.$i.'.last_name')); ?>" class="w-full border rounded-md p-2">
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php if($room->child_quantity > 0): ?>
        <div class="p-2 my-2">
            <h2 class="text-lg font-bold">Children</h2>
            <?php for($i = 0; $i < $room->child_quantity; $i++): ?>
                <div class="border border-gray-600 rounded-md p-2 my-2">
                    <h3 class="text-base font-bold">Child <?php echo e($i + 1); ?></h3>
                    <div class="mb-4">
                        <label for="title" class="block">Title</label>
                        <select id="title" name="rooms[<?php echo e($roomKey); ?>][children][<?php echo e($i); ?>][title]" class="w-full border rounded-md p-2">
                            <option value="Mr" <?php echo e(old('rooms.'.$roomKey.'.children.'.$i.'.title') == 'Mr' ? 'selected' : ''); ?>>Mr</option>
                            <option value="Mrs" <?php echo e(old('rooms.'.$roomKey.'.children.'.$i.'.title') == 'Mrs' ? 'selected' : ''); ?>>Mrs</option>
                            <option value="Ms" <?php echo e(old('rooms.'.$roomKey.'.children.'.$i.'.title') == 'Ms' ? 'selected' : ''); ?>>Ms</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="firstName" class="block">First Name</label>
                        <input type="text" id="firstName" name="rooms[<?php echo e($roomKey); ?>][children][<?php echo e($i); ?>][first_name]" value="<?php echo e(old('rooms.'.$roomKey.'.children.'.$i.'.first_name')); ?>" class="w-full border rounded-md p-2">
                    </div>
                    <div class="mb-4">
                        <label for="lastName" class="block">Last Name</label>
                        <input type="text" id="lastName" name="rooms[<?php echo e($roomKey); ?>][children][<?php echo e($i); ?>][last_name]" value="<?php echo e(old('rooms.'.$roomKey.'.children.'.$i.'.last_name')); ?>" class="w-full border rounded-md p-2">
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <div class="mb-4">
                <label for="clientReferenceId" class="block">Client Reference ID</label>
                <input type="text" id="clientReferenceId" name="client_reference_id" value="<?php echo e(old('client_reference_id')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="bookingReferenceId" class="block">Booking Reference ID</label>
                <input type="text" id="bookingReferenceId" name="booking_reference_id" value="<?php echo e(old('booking_reference_id')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="totalFare" class="block">Total Fare</label>
                <input type="number" step="0.01" id="totalFare" name="total_fare" value="<?php echo e($tboPreBook->total_fare); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="emailId" class="block">Email ID</label>
                <input type="email" id="emailId" name="email_id" value="<?php echo e(old('email_id')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="phoneNumber" class="block">Phone Number</label>
                <input type="tel" id="phoneNumber" name="phone_number" value="<?php echo e(old('phone_number')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="bookingType" class="block">Booking Type</label>
                <input type="text" id="bookingType" name="booking_type" value="Voucher" readonly class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="paymentMode" class="block">Payment Mode</label>
                <select id="paymentMode" name="payment_mode" class="w-full border rounded-md p-2">
                    <option value="Limit" <?php echo e(old('payment_mode') == 'Limit' ? 'selected' : ''); ?>>Limit</option>
                    <option value="SavedCard" <?php echo e(old('payment_mode') == 'SavedCard' ? 'selected' : ''); ?>>SavedCard</option>
                    <option value="NewCard" <?php echo e(old('payment_mode') == 'NewCard' ? 'selected' : ''); ?>>NewCard</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="cvvNumber" class="block">CVV Number</label>
                <input type="text" id="cvvNumber" name="cvv" value="<?php echo e(old('cvv')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardNumber" class="block">Card Number</label>
                <input type="text" id="cardNumber" name="card_number" value="<?php echo e(old('card_number')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardExpirationMonth" class="block">Card Expiration Month</label>
                <input type="text" id="cardExpirationMonth" name="expired_month" value="<?php echo e(old('expired_month')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardExpirationYear" class="block">Card Expiration Year</label>
                <input type="text" id="cardExpirationYear" name="expired_year" value="<?php echo e(old('expired_year')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardHolderFirstName" class="block">Card Holder First Name</label>
                <input type="text" id="cardHolderFirstName" name="card_first_name" value="<?php echo e(old('card_first_name')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="cardHolderLastName" class="block">Card Holder Last Name</label>
                <input type="text" id="cardHolderLastName" name="card_last_name" value="<?php echo e(old('card_last_name')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="billingAmount" class="block">Billing Amount</label>
                <input type="number" step="0.01" id="billingAmount" name="billing_amount" value="<?php echo e($tboPreBook->total_fare); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="billingCurrency" class="block">Billing Currency</label>
                <input type="text" id="billingCurrency" name="billing_currency" value="<?php echo e(old('billing_currency')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="addressLine1" class="block">Address Line 1</label>
                <input type="text" id="addressLine1" name="address_line_1" value="<?php echo e(old('address_line_1')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="addressLine2" class="block">Address Line 2</label>
                <input type="text" id="addressLine2" name="address_line_2" value="<?php echo e(old('address_line_2')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="city" class="block">City</label>
                <input type="text" id="city" name="card_city" value="<?php echo e(old('card_city')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="postalCode" class="block">Postal Code</label>
                <input type="text" id="postalCode" name="card_postal_code" value="<?php echo e(old('card_postal_code')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div class="mb-4">
                <label for="countryCode" class="block">Country Code</label>
                <input type="text" id="countryCode" name="card_country_code" value="<?php echo e(old('card_country_code')); ?>" class="w-full border rounded-md p-2">
            </div>
            <div>
                <button type="submit" class="bg-blue-500 text-white rounded-md p-2">Submit</button>
            </div>
            <div class="w-full fixed left-0 bottom-0 bg-white p-4 text-center shadow-lg border-t border-gray-200">
                <button type="submit" class="bg-black text-white rounded-md p-2 w-80">Book</button>
            </div>
    </form>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/tbo/book/prebook-show.blade.php ENDPATH**/ ?>