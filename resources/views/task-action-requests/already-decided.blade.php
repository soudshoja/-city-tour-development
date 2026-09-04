<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Already decided</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full bg-white shadow-sm border border-gray-200 rounded-lg p-8 text-center">
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Already decided</h1>
        <p class="text-sm text-gray-600 mb-2">
            This request was {{ str_replace('_', ' ', $actionRequest->status) }}
            @if ($actionRequest->processed_at)
                on {{ $actionRequest->processed_at->format('d-m-Y H:i') }}
            @endif.
        </p>
        @if ($actionRequest->process_note)
            <p class="text-xs text-gray-500 italic">"{{ $actionRequest->process_note }}"</p>
        @endif
    </div>
</div>
</body>
</html>
