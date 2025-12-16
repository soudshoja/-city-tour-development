<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Choose Payment Method') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded shadow p-6">
                <form method="POST" action="{{ route('payment-method.set-group') }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $companyId }}">

                    @foreach($paymentMethodGroups as $group)
                        <div class="mb-6">
                            <div class="flex justify-between items-center">
                            <h3 class="font-bold text-lg mb-3">{{ $group->name }}</h3>
                            @if(isset($choiceIds[$group->id]))
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                        data-choice-id="{{ $choiceIds[$group->id] }}" 
                                        data-group-id="{{ $group->id }}" 
                                        class="sr-only peer payment-toggle"
                                        {{ ($enabledGroups[$group->id] ?? false) ? 'checked' : '' }}>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-900">Enabled</span>
                                </label>
                            @else
                                <span class="text-xs text-gray-500">Select a method to enable</span>
                            @endif
                            </div>

                            @php
                                $methodsByGateway = $group->paymentMethods->groupBy(function($method) {
                                    return $method->charge ? $method->charge->name : 'Unknown';
                                });
                                
                                $hasOnlyOne = $methodsByGateway->count() === 1;
                                $selectedMethodId = $selectedMethods[$group->id] ?? null;
                            @endphp

                            <div class="space-y-2">
                                @foreach($methodsByGateway as $gatewayName => $methods)
                                    @php
                                        $method = $methods->first();
                                        $methodId = $method->id;
                                        $isActive = $method->is_active;
                                        $isChecked = ($hasOnlyOne && $isActive) || $selectedMethodId == $methodId;
                                    @endphp
                                    
                                    <label class="flex items-center gap-2 p-3 border rounded {{ $isActive ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-100 cursor-not-allowed opacity-60' }}">
                                        <input 
                                            type="radio" 
                                            name="payment_method_group_{{ $group->id }}" 
                                            value="{{ $methodId }}"
                                            {{ $isChecked ? 'checked' : '' }}
                                            {{ !$isActive ? 'disabled' : '' }}
                                        >
                                        <span>{{ $gatewayName }}</span>
                                        @if(!$isActive)
                                            <span class="text-xs text-red-600 ml-auto">(Inactive - Activate first)</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                        Submit
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('.payment-toggle');
            
            toggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const choiceId = this.dataset.choiceId;
                    const groupId = this.dataset.groupId;
                    const url = "{{ route('payment-method.toggle-enable', ['id' => 'CHOICE_ID']) }}".replace('CHOICE_ID', choiceId);
                    
                    fetch( url , {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Toggle successful:', data);
                        } else {
                            console.error('Toggle failed:', data);
                            this.checked = !this.checked;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.checked = !this.checked;
                    });
                });
            });
        });
    </script>

</x-app-layout>