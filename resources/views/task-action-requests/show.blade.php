<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($actionRequest->action_type) }} acknowledgment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-xl w-full bg-white shadow-sm border border-gray-200 rounded-lg p-8">
        <h1 class="text-xl font-semibold text-gray-900 mb-6">
            {{ ucfirst(__('task_action_requests.action_label.' . $actionRequest->action_type)) }} acknowledgment
        </h1>

        <div class="space-y-3 text-sm text-gray-700 mb-6">
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <span class="text-gray-500">Action:</span>
                <span class="font-medium uppercase">{{ $actionRequest->action_type }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <span class="text-gray-500">Performed by:</span>
                <span class="font-medium">{{ optional($actionRequest->actorAgent)->name ?? '-' }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <span class="text-gray-500">Original agent:</span>
                <span class="font-medium">{{ optional($actionRequest->ownerAgent)->name ?? '-' }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <span class="text-gray-500">Client:</span>
                <span class="font-medium">{{ optional($actionRequest->client)->full_name ?? optional($actionRequest->client)->name ?? '-' }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <span class="text-gray-500">Submitted:</span>
                <span class="font-medium">{{ optional($actionRequest->created_at)->format('d-m-Y H:i') }}</span>
            </div>
        </div>

        <div class="mb-6">
            <p class="text-sm text-gray-500 mb-2">Tickets ({{ $tickets->count() }}):</p>
            <ul class="text-sm space-y-1 bg-gray-50 rounded p-3">
                @foreach ($tickets as $t)
                    <li class="flex justify-between">
                        <span>{{ $t->ticket_number ?? $t->reference ?? '#'.$t->id }}</span>
                        <span class="text-gray-400 text-xs uppercase">{{ $t->status }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($actionRequest->isPending())
            <div class="text-sm text-gray-600 mb-4">
                <strong>Approve</strong> credits the sale on these tickets to {{ optional($actionRequest->actorAgent)->name }}.
                <strong>Deny</strong> keeps the sale with you.
            </div>
            <div class="flex gap-3">
                <a href="{{ route('task-action-request.approve', $actionRequest->request_token) }}"
                   class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-center py-3 rounded font-medium">
                    Approve
                </a>
                <a href="{{ route('task-action-request.deny', $actionRequest->request_token) }}"
                   class="flex-1 bg-red-600 hover:bg-red-700 text-white text-center py-3 rounded font-medium">
                    Deny
                </a>
            </div>
        @else
            <div class="bg-yellow-50 border border-yellow-200 rounded p-4 text-sm text-yellow-800">
                This request has already been decided: <strong>{{ ucfirst(str_replace('_', ' ', $actionRequest->status)) }}</strong>
                @if ($actionRequest->processed_at)
                    on {{ $actionRequest->processed_at->format('d-m-Y H:i') }}.
                @endif
            </div>
        @endif
    </div>
</div>
</body>
</html>
