<x-app-layout>
    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Chart Of Account</h2>
        </div>
        <!-- add new task & refresh page -->
        <div class="flex items-center gap-5">
    <!-- Reload Button -->
    <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
            <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
        </svg>
    </div>

    <!-- Transaction Records Button -->
    <form action="{{ route('coa.transaction') }}" method="GET">
        <button type="submit" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition">
            Transaction Records
        </button>
    </form>
</div>



    </div>
    <!-- ./page title -->



    <!-- page content -->

    <!-- add accounts top bar -->
    <div id="contentBox" class="AddNewSamePage">
        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-4 my-8">
            @php
            // Define types and their colors
            $types = [
            'Assets' => '00ab55',
            'Liabilities' => 'ffc107',
            'Income' => '1e40af',
            'Expenses' => 'AF1740',
            'Equity' => '9744ad' 

            ];
            @endphp

            @foreach($types as $type => $color)
            <!-- Pass `type` and `color` to both card and modal components -->
            <x-coa-card :type="$type" :color="$color" />
            <x-coa-modal :type="$type" :color="$color" />
            @endforeach
        </div>
    </div>
    <!-- ./add accounts top bar -->

    <!-- accounts view -->
    <div class="rounded-lg w-full">
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.assets')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.liabilities')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.income')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.expenses')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.equity')</div>

    </div>
    <!-- ./accounts view -->

    <!-- ./page content -->





    <!-- ./refresh page script -->


    <script>
        const toggleBtn = document.getElementById('toggleBtn');
        const contentBox = document.getElementById('contentBox');

        toggleBtn.addEventListener('click', () => {
            contentBox.classList.toggle('AddNewSamePageVisible');
        });
    </script>
</x-app-layout>