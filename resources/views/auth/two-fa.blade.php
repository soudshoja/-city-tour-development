<x-guest-layout>
<div class="container">
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
                    <div class="d-flex justify-content-center m-3">
                        {!! $qr_code !!}
                    </div>
                    <p class="p-6 text-gray-900 dark:text-gray-100">
                        You must set up your Google Authenticator app before continuing. You will be unable to login otherwise.
                    </p>
                    <div>
                        <x-primary-a href="{{route('welcome')}}">Complete Registration</x-primary-a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-guest-layout>
