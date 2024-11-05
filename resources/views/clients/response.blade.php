<x-guest-layout>
    @if($status == 'success')
        <div class="alert alert-success" role="alert">
            {{ $message }}
        </div>
    @else
        <div class="alert alert-danger" role="alert">
            {{ $message }}
        </div>
    @endif
</x-guest-layout>