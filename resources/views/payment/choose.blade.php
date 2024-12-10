<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6 bg-white shadow rounded-md">
        <h1 class="text-2xl font-bold mb-4">Choose Payment Gateway</h1>

        <!-- Invoice Information -->
        <div class="mb-6">
            <p><strong>Invoice Number:</strong> {{ $invoice['number'] }}</p>
            <p><strong>Total Amount:</strong> ${{ number_format($invoice['amount'], 2) }}</p>
        </div>

        <!-- Full or Partial Payment -->
        <div class="mb-6">
            <label class="block mb-2 font-bold">Payment Type:</label>
            <div class="flex gap-4">
                <label>
                    <input type="radio" name="payment_type" value="full" checked class="mr-2">
                    Full Payment
                </label>
                <label>
                    <input type="radio" name="payment_type" value="partial" class="mr-2">
                    Partial Payment
                </label>
            </div>
        </div>

        <!-- Partial Payment Breakdown -->
        <div id="partial-payment-container" class="hidden">
            <label class="block mb-2 font-bold">Partial Payment Breakdown:</label>
            <div id="partial-payments" class="space-y-2">
                <div class="flex items-center">
                    <input type="number" placeholder="Enter Amount" class="border border-gray-300 p-2 rounded w-full">
                    <button type="button" class="ml-2 px-4 py-2 bg-red-500 text-white rounded remove-partial">Remove</button>
                </div>
            </div>
            <button type="button" id="add-partial" class="mt-4 px-4 py-2 bg-green-500 text-white rounded">Add Another</button>
        </div>

        <!-- Payment Gateway Selection -->
        <div class="mb-6">
            <label class="block mb-2 font-bold">Choose Payment Gateway:</label>
            <select name="payment_gateway" class="border border-gray-300 p-2 rounded w-full">
                @foreach($paymentGateways as $gateway)
                    <option value="{{ $gateway }}">{{ $gateway }}</option>
                @endforeach
            </select>
        </div>

        <!-- Submit Button -->
        <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded">Proceed to Payment</button>
    </div>

    <script>
        // Toggle partial payment container
        document.querySelectorAll('input[name="payment_type"]').forEach((radio) => {
            radio.addEventListener('change', function () {
                const partialContainer = document.getElementById('partial-payment-container');
                if (this.value === 'partial') {
                    partialContainer.classList.remove('hidden');
                } else {
                    partialContainer.classList.add('hidden');
                }
            });
        });

        // Add partial payment input
        document.getElementById('add-partial').addEventListener('click', function () {
            const container = document.getElementById('partial-payments');
            const newPartial = `
                <div class="flex items-center">
                    <input type="number" placeholder="Enter Amount" class="border border-gray-300 p-2 rounded w-full">
                    <button type="button" class="ml-2 px-4 py-2 bg-red-500 text-white rounded remove-partial">Remove</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', newPartial);
        });

        // Remove partial payment input
        document.getElementById('partial-payments').addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-partial')) {
                event.target.parentElement.remove();
            }
        });
    </script>
</body>
</html>
     