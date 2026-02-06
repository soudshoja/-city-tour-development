@include('layouts.mobile-drawer')

<header x-data>
    <div class="navigation-brand">
        <p class="text-center text-background">
            CityTourApp
        </p>
    </div>
    <div class="navigation-bar">
        <button @click="$dispatch('open-mobile-drawer')" class="navigation-mobile-menu-btn">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
            
        <div class="navigation-main">
            <a href="{{ route('dashboard') }}" class="navigation-logo">
                <x-application-logo class="h-20 w-auto" />
            </a>

            <div class="hidden md:block" id="responsiveMenu">
                @include('layouts.menu')
            </div>

        </div>

        <div x-data="{
            toggle: false,
            open: false,
            iataWallet: false
            }"
            class="navigation-profile">
            @include('layouts.profile')
        </div>
    </div>
</header>

<script>
    let walletData = null;
    let walletSessionExpiry = null;
    const WALLET_SESSION_DURATION = 60000;
</script>


<style>
    .text-background {
        background-image: url("{{ asset('images/bgCity.svg') }}");
    }
</style>