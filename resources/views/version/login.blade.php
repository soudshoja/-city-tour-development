<x-guest-layout>
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
        <form action="{{ route('version.index') }}" method="GET">
            <input type="text" placeholder="Username" required>
            <input type="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

            </div>

            <!-- Right Side - Image/Illustration -->
            <div
                class="hide-on-mobile sm:rounded-lg lg:flex w-full lg:w-1/2 bg-white items-center justify-center custom-rounded-right">
                <img src="{{ asset('images/LoginPic550px.png') }}" alt="Illustration"
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
</x-guest-layout>