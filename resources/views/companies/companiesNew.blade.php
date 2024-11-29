<x-app-layout>

    <!-- Form and Image Section -->
    <div class="flex justify-center items-center overflow-y-auto">
        <div
            class="mt-10 flex flex-col lg:flex-row justify-between items-stretch w-full max-w-7xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-96 lg:h-auto">
                <img src="{{ asset('images/registeruser.jpg') }}" alt="User Registration"
                    class="w-full h-full object-cover" />
            </div>

            <!-- Form Section -->
            <div class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Add New
                        Company</h2>

                    <!-- Registration Form -->

                    <form method="POST" action="{{ route('companies.store') }}">
                        @csrf

                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="code">Company Code</label>
                            <input type="text" name="code" id="code" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="nationality_id">Nationality ID</label>
                            <input type="text" name="nationality_id" id="nationality_id" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" name="address" id="address" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="email">Company Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Register Company</button>
                    </form>



                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            var passwordField = document.getElementById('password');
            var eyeIcon = document.getElementById('eyeIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                // Change the icon to indicate password is visible
            } else {
                passwordField.type = 'password';
                // Change the icon back to indicate password is hidden
            }
        }
    </script>
</x-app-layout>