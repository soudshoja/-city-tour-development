<x-app-layout>
    <style>
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            background-size: cover;
            background-image: url("data:image/svg+xml;base64,{{ $qrCode }}");
        }
    </style>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="row">
            <div class="col-md-12">
                <div class="card card-default">
                    <h4 class="card-heading text-center mt-4 text-gray-900 dark:text-gray-100">
                        Set Up Authenticator
                    </h4>

                    <div class="card-body text-center">
                        <p class="p-6 text-gray-900 dark:text-gray-100">
                            Set up your two factor authentication by scanning the barcode below. Alternatively, you can use the code: <br>
                            <strong>
                                {{$secret}}
                            </strong>
                        </p>
                        <div class="m-3">
                            <div class="qr-code">
                            </div>
                        </div>
                        <p class="p-6 text-gray-900 dark:text-gray-100">
                            You must set up your Google Authenticator app before continuing. You will be unable to login otherwise.
                        </p>
                        <div class="mb-4">
                            <x-primary-a href="{{route('enable2fa')}}">Complete Authentication</x-primary-a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

</x-app-layout>
