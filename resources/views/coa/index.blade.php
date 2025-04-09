<x-app-layout>
    <!-- page title -->
    <div 
        id="coa-container" 
        data-branches = "{{ $branches }}"
        data-agents = "{{ $agents }}"
        data-clients = "{{ $clients }}"
        class="flex justify-between items-center gap-5 my-3 ">


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
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.new-liabilities')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.new-income')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.new-expenses')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.new-equity')</div>

    </div>
    <!-- ./accounts view -->

    <!-- ./page content -->





    <!-- ./refresh page script -->


    <script>
      const branches = JSON.parse(document.getElementById('coa-container').getAttribute('data-branches'));
      const agents = JSON.parse(document.getElementById('coa-container').getAttribute('data-agents'));
      const clients = JSON.parse(document.getElementById('coa-container').getAttribute('data-clients'));

      const entitySelects = document.querySelectorAll('.entitySelect');

      function handleEntityChange(event) {
        console.log('Entity changed:', event.target.value);
          const entitySelect = event.target;
          const level = entitySelect.dataset.level;
          const accountId = entitySelect.dataset.accountId;
          const selectedValue = entitySelect.value;

          const entityContainer = document.getElementById(`entity-container-${accountId}`);
          entityContainer.innerHTML = ''; // Clear previous content

          if (!selectedValue) return;

          const label = document.createElement('label');
          label.classList.add('block', 'text-sm', 'font-medium', 'mb-1');
          label.innerHTML = `${selectedValue.charAt(0).toUpperCase() + selectedValue.slice(1)} Name<span class="text-red-500"> *</span>`;
          entityContainer.appendChild(label);

          let selectOptions = [];
          if (selectedValue === 'agent') selectOptions = agents;
          else if (selectedValue === 'client') selectOptions = clients;
          else if (selectedValue === 'branch') selectOptions = branches;

          if (selectOptions.length > 0) {
              const select = createSelectElement(
                  [{
                      id: '',
                      name: `Select ${selectedValue}`
                  }, ...selectOptions], {
                      name: selectedValue,
                      id: selectedValue,
                      required: 'required',
                      autocomplete: 'off'
                  },
                  ['w-full', 'border', 'rounded', 'text-sm', 'px-3', 'py-2', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-300']
              );
              entityContainer.appendChild(select);
          }
      }

      // Attach change event listeners to entity selects
      console.log(entitySelects);
      entitySelects.forEach(entitySelect => {
          entitySelect.addEventListener('change', handleEntityChange);
      });
        const toggleBtn = document.getElementById('toggleBtn');
        const contentBox = document.getElementById('contentBox');


        toggleBtn.addEventListener('click', () => {
            contentBox.classList.toggle('AddNewSamePageVisible');
        });
    </script>
</x-app-layout>