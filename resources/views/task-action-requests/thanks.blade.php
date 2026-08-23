<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($decision) }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full bg-white shadow-sm border border-gray-200 rounded-lg p-8 text-center">
        @if ($decision === 'approved')
            <div class="text-emerald-600 text-5xl mb-4">&check;</div>
            <h1 class="text-xl font-semibold text-gray-900 mb-2">Approved</h1>
            <p class="text-sm text-gray-600">
                Sale credited to {{ optional($actionRequest->actorAgent)->name }}. We've notified them.
            </p>
        @else
            <div class="text-red-600 text-5xl mb-4">&times;</div>
            <h1 class="text-xl font-semibold text-gray-900 mb-2">Denied</h1>
            <p class="text-sm text-gray-600">
                {{ count($reverted_task_ids ?? []) }} task(s) have been reverted to {{ optional($actionRequest->ownerAgent)->name }}.
                {{ optional($actionRequest->actorAgent)->name }}, admin and accounting have been notified.
            </p>
        @endif
    </div>
</div>
</body>
</html>
