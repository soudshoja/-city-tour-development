<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    Register
                </div>
                <hr class="border-b border-gray-100">
                @if($errors->any())
                    <div class="col-md-12">
                        <div class="alert alert-danger">
                            <strong>
                                {{$errors->first()}}
                            </strong>
                        </div>
                    </div>
                @endif
                <div class="p-6">
                    <form class="form-horizontal" method="POST" action="{{ route('test') }}">
                        {{ csrf_field() }}

                        <div class="form-group">
                            <p class="col-md-12 text-gray-900 dark:text-gray-100">
                                Please enter the <strong>OTP</strong>
                                generated on your Authenticator App. <br>
                                Ensure you submit the current one because it refreshes every 30 seconds.
                            </p>
                            <br>
                            <label for="one_time_password" class="col-md-4 control-label text-gray-900 dark:text-gray-100">One Time Password</label>
                            <div class="col-md-6">
                                <input id="one_time_password" type="number" class="form-control" name="one_time_password" required autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4  mt-3">
                                <x-primary-button>Login</x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
