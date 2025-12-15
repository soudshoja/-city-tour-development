<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Choose Payment Method') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded shadow p-6">
                <form method="POST" action="">
                    @csrf

                    @foreach($paymentMethodGroups as $group)
                        <div class="mb-6">
                            <h3 class="font-bold text-lg mb-3">{{ $group->name }}</h3>

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

</x-app-layout>