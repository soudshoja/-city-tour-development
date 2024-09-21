
    <div class="bg-white rounded-lg shadow-md p-5">
        <div class="flex justify-between items-center w-full">
            <div class="bg-gray-200 p-2.5 rounded flex-grow">
                <h1><strong>Pending Task</strong></h1>
            </div>
            <a href="" class="btn btn-success ml-2">Add Item</a>
        </div>
     
        @if (session('success') || session('error'))
            <div id="flash-message" class="alert 
                @if (session('success')) alert-success 
                @elseif (session('error')) alert-danger 
                @endif
                fixed-top-right">
                {{ session('success') ?? session('error') }}
            </div>
        @endif
        @if ($message)
            @php
                $messageClasses = [
                    'success' => 'bg-green-100 border-l-4 border-green-400 text-green-700',
                    'error' => 'bg-red-100 border-l-4 border-red-400 text-red-700',
                    'warning' => 'bg-yellow-100 border-l-4 border-yellow-400 text-yellow-700',
                    'info' => 'bg-blue-100 border-l-4 border-blue-400 text-blue-700'
                ];
                $messageClass = $messageClasses[$status] ?? 'bg-gray-100 border-l-4 border-gray-400 text-gray-700';
            @endphp
            <div class="{{ $messageClass }} p-2 rounded-lg inline-block my-2">
                {{ $message }}
            </div>
        @endif

        @if (!empty($items))
            <div class="items mt-3">
                @foreach($items as $item)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Item Ref: {{ $item['item_ref'] }}</h5>
                            <p class="card-text"><strong>Description:</strong> {{ $item['description'] }}</p>
                            <p class="card-text"><strong>Item Type:</strong> {{ $item['item_type'] }}</p>
                            <p class="card-text"><strong>Total Price:</strong> {{ $item['total_price'] }}</p>
                            <a href="{{ route('items.show', $item['id']) }}" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
                @if ($message === 'Agent not found')
        <!-- Inline form to create agent profile -->
        <div class="mt-4">
            <h2>Create Agent Profile</h2>
            <form action="{{ route('create.agent.profile') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" required>
                </div>
                <div class="form-group">
                    <label for="company_id">Company ID</label>
                    <input type="text" class="form-control" id="company_id" name="company_id" required>
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="salary">Salary</option>
                        <option value="commission">Commission</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create Profile</button>
            </form>
        </div>
    @endif
</div>
