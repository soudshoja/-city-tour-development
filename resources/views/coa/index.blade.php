<!-- resources/views/coa/index.blade.php -->
<x-app-layout>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs" defer></script>


    <!-- Message Area -->
    <div id="message-area" class="fixed bottom-4 right-4 z-50 hidden">
        <div id="message" class="bg-green-500 text-white p-4 rounded-lg"></div>
    </div>




    <!-- Breadcrumbs -->
    <x-breadcrumbs :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'COA Settings']
                            ]" />

    <!-- ./Breadcrumbs -->

    <div class="bg-gray-100 min-h-screen">
        <!-- Top Card Section -->
        <!-- Top Card Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            @php
            // Define types and their colors
            $types = [
            'Assets' => '1A5319',
            'Liabilities' => 'FCC157',
            'Income' => '004C9E',
            'Expenses' => 'AF1740'
            ];
            @endphp

            @foreach($types as $type => $color)
            <!-- Pass `type` and `color` to both card and modal components -->
            <x-coa-card :type="$type" :color="$color" />
            <x-coa-modal :type="$type" :color="$color" />
            @endforeach
        </div>

        <!-- Accounts Overview -->
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex justify-between mb-5">
                <h3 class="font-semibold text-lg mb-4">Financial Statement</h3>
                <div class="flex gap-3 items-center">

                    <!-- Refresh Button with Rotation Animation -->
                    <button id="refreshAccountsDataButton">
                        <svg class="refreshLiabilitiesIcon " width="24" height="24" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M12.0789 3V2.25V3ZM3.67981 11.3333H2.92981H3.67981ZM3.67981 13L3.15157 13.5324C3.44398 13.8225 3.91565 13.8225 4.20805 13.5324L3.67981 13ZM5.88787 11.8657C6.18191 11.574 6.18377 11.0991 5.89203 10.8051C5.60029 10.511 5.12542 10.5092 4.83138 10.8009L5.88787 11.8657ZM2.52824 10.8009C2.2342 10.5092 1.75933 10.511 1.46759 10.8051C1.17585 11.0991 1.17772 11.574 1.47176 11.8657L2.52824 10.8009ZM18.6156 7.39279C18.8325 7.74565 19.2944 7.85585 19.6473 7.63892C20.0001 7.42199 20.1103 6.96007 19.8934 6.60721L18.6156 7.39279ZM12.0789 2.25C7.03155 2.25 2.92981 6.3112 2.92981 11.3333H4.42981C4.42981 7.15072 7.84884 3.75 12.0789 3.75V2.25ZM2.92981 11.3333L2.92981 13H4.42981L4.42981 11.3333H2.92981ZM4.20805 13.5324L5.88787 11.8657L4.83138 10.8009L3.15157 12.4676L4.20805 13.5324ZM4.20805 12.4676L2.52824 10.8009L1.47176 11.8657L3.15157 13.5324L4.20805 12.4676ZM19.8934 6.60721C18.287 3.99427 15.3873 2.25 12.0789 2.25V3.75C14.8484 3.75 17.2727 5.20845 18.6156 7.39279L19.8934 6.60721Z"
                                fill="#1C274C" />
                            <path opacity="0.5"
                                d="M11.8825 21V21.75V21ZM20.3137 12.6667H21.0637H20.3137ZM20.3137 11L20.8409 10.4666C20.5487 10.1778 20.0786 10.1778 19.7864 10.4666L20.3137 11ZM18.1002 12.1333C17.8056 12.4244 17.8028 12.8993 18.094 13.1939C18.3852 13.4885 18.86 13.4913 19.1546 13.2001L18.1002 12.1333ZM21.4727 13.2001C21.7673 13.4913 22.2421 13.4885 22.5333 13.1939C22.8245 12.8993 22.8217 12.4244 22.5271 12.1332L21.4727 13.2001ZM5.31769 16.6061C5.10016 16.2536 4.63806 16.1442 4.28557 16.3618C3.93307 16.5793 3.82366 17.0414 4.0412 17.3939L5.31769 16.6061ZM11.8825 21.75C16.9448 21.75 21.0637 17.6915 21.0637 12.6667H19.5637C19.5637 16.8466 16.133 20.25 11.8825 20.25V21.75ZM21.0637 12.6667V11H19.5637V12.6667H21.0637ZM19.7864 10.4666L18.1002 12.1333L19.1546 13.2001L20.8409 11.5334L19.7864 10.4666ZM19.7864 11.5334L21.4727 13.2001L22.5271 12.1332L20.8409 10.4666L19.7864 11.5334ZM4.0412 17.3939C5.65381 20.007 8.56379 21.75 11.8825 21.75V20.25C9.09999 20.25 6.6656 18.7903 5.31769 16.6061L4.0412 17.3939Z"
                                fill="#1C274C" />
                        </svg>
                    </button>

                    <!-- Search SVG Icon -->
                    <svg id="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg" style="cursor: pointer;">
                        <path d="M18.5 18.5L22 22" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                        <path
                            d="M6.75 3.27093C8.14732 2.46262 9.76964 2 11.5 2C16.7467 2 21 6.25329 21 11.5C21 16.7467 16.7467 21 11.5 21C6.25329 21 2 16.7467 2 11.5C2 9.76964 2.46262 8.14732 3.27093 6.75"
                            stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                    </svg>

                    <!-- Search Input Field (initially hidden) -->
                    <input type="text" id="search-input" class="rounded-lg hidden p-1" placeholder="Search..." />
                </div>

            </div>
            <div class="mb-5 search-item">@include('coa.partials.assets')</div>
            <div class="mb-5 search-item">@include('coa.partials.liabilities')</div>
            <div class="mb-5 search-item">@include('coa.partials.income')</div>
            <div class="mb-5 search-item">@include('coa.partials.expenses')</div>

        </div>
    </div>



    <!-- JavaScript for Modal and Form Handling -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const types = ["assets", "liabilities", "income", "expenses"];

        types.forEach((type) => {
            const modal = document.getElementById(`${type}-modal`);
            const form = document.getElementById(`${type}-form`);
            const openButton = document.getElementById(`create-${type}-button`);
            const closeButton = modal.querySelector(".close-modal");

            // Open modal
            openButton.addEventListener("click", () => {
                modal.classList.remove("hidden");
            });

            // Close modal
            closeButton.addEventListener("click", () => {
                modal.classList.add("hidden");
            });

            // Form submission with AJAX
            form.addEventListener("submit", (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                formData.append("type", type);

                fetch("{{ route('coa.create') }}", {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": "{{ csrf_token() }}",
                            "Accept": "application/json"
                        },
                        body: formData,
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Account created for ${type}`);
                            modal.classList.add("hidden");
                            form.reset();
                        } else {
                            alert(`Failed to create account: ${data.message}`);
                        }
                    })
                    .catch(error => console.error("Error:", error));
            });
        });
    });




    // Add an event listener to the refresh button for liabilities
    document.getElementById("refreshAccountsDataButton").addEventListener("click", function() {
        const svgIcon = document.querySelector(".refreshLiabilitiesIcon ");
        svgIcon.classList.toggle("rotate");
        // Delay to allow the rotation animation to complete before refreshing
        setTimeout(() => {
            location.reload();
        }, 300); // Match this duration with the CSS transition duration
    });

    // Add an event listener to the refresh button for liabilities
    document.getElementById('refreshAccountsDataButton').addEventListener('click', function() {
        refreshLiabilitiesData();
    });

    // Function to refresh liabilities data
    function refreshLiabilitiesData() {
        console.log("Fetching new liabilities data...");

        // Fetch new data from the server
        fetch('/api/liabilities') // Replace with your actual API endpoint
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                // Update your UI with the new data here.
                console.log("Liabilities data refreshed:", data);
                // Example: Update some HTML element with the new data
                // document.getElementById('liabilitiesInfo').innerText = data.info;
            })
            .catch(error => {
                console.error('Error fetching liabilities:', error);
            });
    }
    </script>

</x-app-layout>