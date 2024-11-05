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
        document.addEventListener("DOMContentLoaded", function () {
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
    </script>

</x-app-layout>