<x-app-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header my-2 flex justify-between align-items-center">
                        <h4 class="font-bold text-lg mb-0">Proforma Invoice</h4>
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('invoice.proforma.pdf', $invoice->invoice_number) }}"
                                class="btn btn-primary">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                            <a href="{{ url()->previous() }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Invoice Content -->
                        <div class="invoice-container">
                            <!-- Header -->
                            <div class="row mb-4">
                                <div class="col-5">
                                    <img src="{{ $companyLogoSrc }}" alt="Company Logo" style="max-height: 80px;">
                                    <h3 class="mt-2">{{ $company->name }}</h3>
                                    <p class="mb-0">{{ $company->address }}</p>
                                    <p class="mb-0">{{ $company->phone }}</p>
                                    <p class="mb-0">{{ $company->email }}</p>
                                </div>
                                <div class="col-5 text-end">
                                    <h2 class="text-primary">PROFORMA INVOICE</h2>
                                    <p class="mb-1"><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
                                    <p class="mb-1"><strong>Date:</strong> {{ $invoice->created_at->format('d/m/Y') }}</p>
                                    <p class="mb-1"><strong>Agent:</strong> {{ $invoice->agent->name }}</p>
                                </div>
                            </div>

                            <!-- Client Information -->
                            <div class="row mb-4">
                                <div class="col-5">
                                    <h5>Bill To:</h5>
                                    <strong>{{ $invoice->client->name }}</strong><br>
                                    {{ $invoice->client->address }}<br>
                                    <span>{{$invoice->client->country_code}}</span>{{ $invoice->client->phone }}<br>
                                </div>
                            </div>

                            <!-- Invoice Details Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Details</th>
                                            <th>Supplier</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $grandTotal = 0; @endphp
                                        @foreach($invoiceDetails as $index => $detail)
                                        @php $grandTotal += $detail->task_price; @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            @if($detail->task->flightDetails)
                                            <td>
                                                <strong>Flight:</strong> {{ $detail->task->flightDetails->ticket_number }}<br>
                                                <p>
                                                    {{ $detail->task->reference}}
                                                </p>
                                                <p>
                                                    {{ $detail->task->additional_info ?? 'No additional information available' }}
                                                </p>
                                            </td>
                                            @elseif($detail->task->hotelDetails)
                                            <td>
                                                <strong>Hotel:</strong> {{ $detail->task->hotelDetails->hotel->name }}<br>
                                                <strong>Check-in:</strong> {{ $detail->task->hotelDetails->readable_check_in }}<br>
                                                <strong>Check-out:</strong> {{ $detail->task->hotelDetails->readable_check_out }}<br>
                                                <strong>Room Name:</strong> {{ $detail->task->hotelDetails->room_name }}<br>
                                                <strong>Nights:</strong> {{ $detail->task->hotelDetails->nights }}
                                            </td>
                                            @else
                                            <td>
                                                <p>-</p>
                                            </td>
                                            @endif
                                            <td>{{ $detail->task->supplier->name ?? 'N/A' }}</td>
                                            <td>KWD{{ number_format($detail->task_price, 3) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Grand Total:</th>
                                            <th>KWD{{ number_format($grandTotal, 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6>Terms and Conditions:</h6>
                                    <small class="text-muted">
                                        This is a proforma invoice and serves as a quotation. Payment terms and conditions apply as per company policy.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
<style>
    .invoice-container {
        background: white;
        padding: 20px;
        border-radius: 5px;
    }

    @media print {

        .card-header,
        .btn {
            display: none !important;
        }
    }
</style>
