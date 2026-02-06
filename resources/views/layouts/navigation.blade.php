<header>
    <div class="navigation-brand">
        <p class="text-center text-background">
            CityTourApp
        </p>
    </div>
    <div class="navigation-bar">
        <div class="navigation-main">
            <a href="{{ route('dashboard') }}" class="navigation-logo">
                <x-application-logo class="h-20 w-auto" />
            </a>

            <div class="hidden md:block" id="responsiveMenu">
                @include('layouts.menu')
            </div>

            <div class="block md:invisible" id="menu-icon">
                <i class="fa fa-bars"></i>
            </div>

        </div>

        <!-- Right Section -->

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

    $(document).ready(function() {
        $('#menu-icon').click(function() {
            $('#responsiveMenu').toggle();
        });

        // Position fixed dropdown menus relative to their parent menuitem
        // $('.navigation-main nav > menu > menuitem').on('mouseenter', function() {
        //     const $menuitem = $(this);
        //     const $dropdown = $menuitem.children('menu');

        //     if ($dropdown.length) {
        //         const rect = $menuitem[0].getBoundingClientRect();
        //         $dropdown.css({
        //             top: rect.bottom + 'px',
        //             left: rect.left + 'px',
        //             width: 'auto',
        //             minWidth: rect.width + 'px'
        //         });
        //     }
        // });
    });

</script>


<style>
    .top5Up {
        top: -4.5rem;
    }

    .text-background {

        padding: 0 !important;
        margin: 0 !important;
        background-image: url("{{ asset('images/bgCity.png') }}");
        opacity: 0.4;
        background-size: cover;
        background-position: center;
        color: transparent;
        font-size: 9rem;
        font-weight: bold;
        text-transform: uppercase;
        font-family: 'Archivo Black', sans-serif;
        letter-spacing: 2.5rem;
        -webkit-background-clip: text;
        background-clip: text;
        text-align: center;

    }

    /* Tablet and Mobile Specific Styles */
    @media (max-width: 768px) {
        .text-background {
            font-size: 3rem;
            /* Adjust font size for tablets */
            letter-spacing: 1.5rem;
            /* Adjust letter spacing for tablets */
        }
    }

    @media (max-width: 640px) {
        .text-background {
            font-size: 2.5rem;
            /* Adjust font size for mobile */
            letter-spacing: 1rem;
            /* Adjust letter spacing for mobile */
        }
    }
</style>