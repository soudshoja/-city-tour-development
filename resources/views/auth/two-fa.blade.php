<x-guest-layout>
<div class="card">
    <div class="card-header">2-factors authentication</div>

    <div class="card-body">
        <p>
          2-factors authentication is currently
          <span class='badge bg-warning'>disabled</span>. To enable:
        </p>

        <ol class="list-left-align">
            <li>Open your OTP app and <b>scan the following QR-code</b>
                <p class="text-center">
                {!! $qr_code !!}
                </p>
            </li>

            <li>Generate a One Time Password (OTP) and enter the value below.

                <form action="{{ route('twofa-enable') }}" method="POST"
                      class="form-inline text-center">
                    @csrf
                    <input name="otp" class="form-control mr-1{{ $errors->has('otp') ? ' is-invalid' : '' }}"
                           type="number" min="0" max="999999" step="1"
                           required autocomplete="off">
                    <button type="submit" class="form-control btn-sm btn-primary">Submit</button>
                    @if ($errors->has('otp'))
                    <span class="invalid-feedback text-left">
                        <strong>{{ $errors->first('otp') }}</strong>
                    </span>
                    @endif
                </form>
            </li>
        </ol>

    </div>
</div>
</x-guest-layout>