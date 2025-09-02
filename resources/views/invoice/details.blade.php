<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css'])
    <script src="//unpkg.com/alpinejs" defer></script>
</head>
<body class="overflow-y-auto font-nunito antialiased bg-gray-100">
    <div class="min-h-screen bg-gray-100 dark:bg-slate-900 p-4 sm:p-6 lg:p-8">
        <div class="max-w-5xl mx-auto bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
            <header class="px-8 py-6 bg-slate-50 dark:bg-slate-900/50 border-b border-gray-200 dark:border-slate-700">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Invoice</h1>
                        <p class="text-gray-600 dark:text-slate-400 mt-1">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div class="text-center">
                        <img class="h-14 w-auto" src="{{ $company->logo ? Storage::url($company->logo) : asset('images/UserPic.svg') }}" alt="Company logo"/>
                        <p class="text-sm font-semibold mt-2">{{ $company->name }}</p>
                    </div>
                </div>
                <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                    <div>
                        <p class="font-semibold text-gray-600 dark:text-slate-300">Billed To:</p>
                        <p class="text-gray-800 dark:text-white font-bold">{{ $invoice->client->name }}</p>
                        <p class="text-gray-600 dark:text-slate-400">{{ $invoice->client->email }}</p>
                        <p class="text-gray-600 dark:text-slate-400">{{ $invoice->client->phone }}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-600 dark:text-slate-300">Handled By:</p>
                        <p class="text-gray-800 dark:text-white font-bold">{{ $invoice->agent->name }}</p>
                        <p class="text-gray-600 dark:text-slate-400">{{ $invoice->agent->email }}</p>
                    </div>
                    <div>
                        <p><span class="font-semibold text-gray-600 dark:text-slate-300">Invoice Date:</span> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</p>
                        <p><span class="font-semibold text-gray-600 dark:text-slate-300">Due Date:</span> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</p>
                        <p><span class="font-semibold text-gray-600 dark:text-slate-300">Paid Date:</span> {{ \Carbon\Carbon::parse($invoice->paid_date)->format('d M Y') }}</p>
                        @php
                            $status = strtolower($invoice->status ?? '');
                            $classes = [
                                'paid'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                'unpaid'  => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                'partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                            ][$status] ?? 'bg-gray-100 text-gray-800 dark:bg-slate-800/70 dark:text-slate-200';
                        @endphp
                        <span class="mt-2 inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $classes }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </div>
                </div>
            </header>
            <section class="px-8 py-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Invoice Items</h2>
                <div class="border border-gray-300 dark:border-slate-600 rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 dark:bg-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Task Details</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Final Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                @foreach($invoice->invoiceDetails as $index => $item )
                                    <tr x-data="{ open: false }">
                                        <td colspan="3" class="p-0">
                                            <div class="cursor-pointer" @click="open = !open">
                                                <div class="flex items-center px-6 py-4 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                                    <div class="w-8 text-sm text-gray-500 dark:text-slate-400">{{ $index + 1 }}</div>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->task->reference }}</p>
                                                        <p class="text-xs text-gray-600 dark:text-slate-300">{{ $item->task_description ?? 'No Ref' }}</p>
                                                    </div>
                                                    <div class="w-32 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($item->task_price, 2) }}</div>
                                                    <div class="w-8 text-right pl-2">
                                                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </div>
                                                </div>
                                            </div>
                                            <div x-show="open" x-transition class="bg-slate-50 dark:bg-slate-800/50 p-6 border-t border-gray-200 dark:border-slate-700">
                                                <h4 class="font-bold text-md mb-3 text-gray-800 dark:text-white">Task Breakdown</h4>
                                                @if($item->task)
                                                    <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                                                        <div class="sm:col-span-2">
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Passenger Name</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->passenger_name ?: 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Client Name</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->client_name ?: 'N/A' }}</dd>
                                                        </div>
                                                        <hr class="sm:col-span-2 md:col-span-3 border-gray-200 dark:border-slate-700">
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Supplier</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->supplier->name ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">GDS Reference</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->gds_reference ?: 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Airline Reference</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->airline_reference ?: 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Issued Date</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->issued_date ? $item->task->issued_date->format('d M Y') : 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Supplier Price</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ number_format($item->supplier_price, 2) }} KWD</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Markup</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ number_format($item->markup_price, 2) }} KWD</dd>
                                                        </div>
                                                    </dl>
                                                @else
                                                    <p class="text-gray-500 dark:text-slate-400">No associated task found for this item.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="px-8 py-6 bg-slate-100 dark:bg-slate-900/60">
                <div class="flex justify-end">
                    <div class="w-full max-w-sm">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Subtotal:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->sub_amount, 2) }} KWD</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Service Charges:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->invoicePartials->sum('service_charge') ?? 0, 2) }} KWD</dd>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-slate-700">
                                <dt class="text-base font-semibold text-gray-900 dark:text-white">Total Amount:</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format($invoice->amount, 2) }} KWD</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>
            <section class="px-8 py-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Financial Ledger</h2>
                <div class="overflow-x-auto border border-gray-200 dark:border-slate-700 rounded-lg">
                    <table class="w-full">
                        <thead class="bg-gray-100 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Debit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            @foreach($journalEntries as $entry)
                                @php
                                    $date = $entry->transaction_date ?? $entry->created_at;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($entry->date)->format('d M, Y') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-800 dark:text-slate-200">{{ $entry->description ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <span class="font-semibold {{ $entry->debit > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-slate-400' }}">
                                            {{ number_format($entry->debit, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <span class="font-semibold {{ $entry->credit > 0 ? 'text-green-700 dark:text-green-300' : 'text-gray-600 dark:text-slate-400' }}">
                                            {{ number_format($entry->credit, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-bold {{ $entry->running_balance >= 0 ? 'text-green-700 dark:text-green-300' : 'text-gray-900 dark:text-slate-100' }}">
                                        {{ $entry->running_balance !== null ? number_format($entry->running_balance, 2) : 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            <footer class="px-8 py-6 text-center text-sm text-gray-500 dark:text-slate-400 border-t border-gray-200 dark:border-slate-700">
                <p>Thank you for your business!</p>
                <p class="mt-1">If you have any questions, please contact us at {{ $company->email }}</p>
            </footer>
        </div>
    </div>
    </body>
</html>
